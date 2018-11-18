#!/usr/bin/env php
<?php
/**
 * 简易IP地址信息转换查询（数据生成器）
 * 快速查询IP地址对应的信息描述，比如所属国家、城市、县市及网络服务商等。
 *
 * 输出二进制文件格式备忘：
 *
 *
 * 1. [header] 8bytes
 *    fixed tag string: CTIP
 *    records num: 32bit unsigned int, little endian
 *
 * 2. [index zone] 12bytes * records num, sort from small to large
 *    ... [begin IP][value1 offset][value2 offset] ...
 *    ...
 *    ... [last IP][length of values1][timestamp]
 *
 * 3. [values1] 1bytes + value_length
 *
 * 4. [values2] 1bytes + value_length
 *
 * @author hightman <hightman@twomice.net>
 * @link http://www.hightman.cn/
 * @copyright Copyright &copy; 2018- Twomice Studio
 */
ini_set('memory_limit', '1024M');
set_time_limit(0);

// arguments
if (!isset($_SERVER['argv'][1])) {
    echo 'Usage: ', $_SERVER['argv'][0], ' <input file> [output file]', PHP_EOL;
    exit(-1);
}
$input = $_SERVER['argv'][1];
if (($fr = @fopen($input, 'r')) === false) {
    echo 'ERROR: failed to open input file.', PHP_EOL;
    exit(0);
}
if (isset($_SERVER['argv'][2])) {
    $output = $_SERVER['argv'][2];
} else {
    $pos = strrpos($input, '.');
    $output = $pos === false ? $input : substr($input, 0, $pos);
    $output .= '.dat';
}
if (($fw = @fopen($output, 'wb')) === false) {
    echo 'ERROR: failed to open output file.', PHP_EOL;
    exit(0);
}

//
$rec = $xrec = [];
echo 'Start building IP data (', $input, ' -> ', $output, ') ...', PHP_EOL;
echo '  > loading data from input file ...', PHP_EOL;

$l = 0;
while ($line = fgets($fr, 1024)) {
    $l++;
    $off = 0;
    $bit = -1;
    $ip1 = _next_part($line, $off);
    if ($pos = strpos($ip1, '/')) {
        // parse CIDR:  210.0.0.0/8
        $bit = intval(substr($ip1, $pos + 1));
        $ip1 = substr($ip1, 0, $pos);
        if ($bit < 1 || $bit > 32) {
            $bit = 32;
        }
        $bit = 32 - $bit;
    }
    $ip1 = _ip2long($ip1);
    if ($ip1 === false) {
        echo '  >> SKIP[', $l, ']: ', $line;
        continue;
    }
    if ($bit > 0) {
        $ip2 = $ip1 + (1 << $bit);
    } else {
        $ip2 = _ip2long(_next_part($line, $off)) + 1;
    }
    if ($ip2 < $ip1) {
        echo '  >> SKIP[', $l, ']: ', $line;
        continue;
    }

    // get area1, area2
    $area1 = _next_part($line, $off);
    $area2 = trim(substr($line, $off));
    if ($area1 == 'CZ88.NET' || $area1 == '[未知IP0801]') {
        $area1 = 'APNIC';
    }
    if ($area2 == 'CZ88.NET') {
        $area2 = '';
    }

    // check overwrite
    $xk = dechex($ip1) . '-' . dechex($ip2);
    if (isset($xrec[$xk])) {
        $xk = $xrec[$xk];
        if ($area1 != $rec[$xk][2]) {
            echo '  >> OVER[', $l, ']: ', $line, ' (~~', $rec[$xk][2], ' ', $rec[$xk][3], '~~)', PHP_EOL;
        }
        $rec[$xk] = [$ip1, $ip2, $area1, $area2];
    } else {
        $xrec[$xk] = count($rec);
        $rec[] = [$ip1, $ip2, $area1, $area2];
    }
}
fclose($fr);
unset($xrec);

// sort the rec first time
echo '  > OK, total records: ' . count($rec), PHP_EOL;
echo '  > sorting records ...';
usort($rec, '_rec_cmp');

echo ' OK', PHP_EOL, '  > trying to fixed bad offset ...';
$count = count($rec);
$stack = [];
$level = 0;
$stack[$level] = [0xffffffff + 1, '', ''];

// first record
if ($rec[0][0] > 0) {
    $rec[] = [0, $rec[0][0], $stack[0][1], $stack[0][2]];
}

// other records
for ($i = 0; $i < $count; $i++) {
    $j = $i + 1;
    $n = ($j == $count ? $stack[0][0] : $rec[$j][0]);
    if ($rec[$i][1] < $n) {
        while ($level > 0 && ($stack[$level][0] <= $rec[$i][1])) {
            unset($stack[$level]);
            $level--;
        }
        if ($level == 0 || ($stack[$level][0] > $n)) {
            $rec[] = [$rec[$i][1], $n, $stack[$level][1], $stack[$level][2]];
        } else {
            $f = $rec[$i][1];
            while ($level > 0 && $stack[$level][0] <= $n) {
                $rec[] = [$f, $stack[$level][0], $stack[$level][1], $stack[$level][2]];
                $f = $stack[$level][0];
                unset($stack[$level]);
                $level--;
            }
            if ($f < $n) {
                $rec[] = [$f, $n, $stack[$level][1], $stack[$level][2]];
            }
        }
    } elseif ($rec[$i][1] > $n) {
        $level++;
        $stack[$level] = [$rec[$i][1], $rec[$i][2], $rec[$i][3]];
        usort($stack, '_rec_cmp2');

        if ($n > $rec[$i][0]) {
            $rec[$i][1] = $n;
        } else {
            unset($rec[$i]);
        }
    } else {
        // cleanup some stacks
        while ($level > 0 && ($stack[$level][0] <= $n)) {
            unset($stack[$level]);
            $level--;
        }
        if ($j < $count && ($rec[$j][2] == $rec[$i][2] && $rec[$j][3] == $rec[$i][3])) {
            // merged them
            $rec[$j][0] = $rec[$i][0];
            unset($rec[$i]);
        }
    }
}

// OK ,resort them
echo ' OK, new records: ', count($rec), PHP_EOL;
echo '  > resorting the records ...';
usort($rec, '_rec_cmp');
echo '  OK', PHP_EOL;
echo '  > saving to output file ...';

// plain
if (isset($_SERVER['argv'][3]) && $_SERVER['argv'][3] == 'plain') {
    foreach ($rec as $tmp) {
        $ip1 = long2ip($tmp[0]);
        $ip2 = long2ip($tmp[1] - 1);
        fprintf($fw, "%-16.16s%-16.16s %s %s\n", $ip1, $ip2, $tmp[2], $tmp[3]);
    }
} else {
    // header
    fwrite($fw, 'CTIP' . pack('V', count($rec)), 8);
    // index zone
    $a1 = $a2 = [];
    $b1 = $b2 = ' ';
    $o1 = $o2 = 1;
    foreach ($rec as $tmp) {
        // area1
        if ($tmp[2] == '') {
            $_o1 = 0;
        } else {
            if (!isset($a1[$tmp[2]])) {
                $_o1 = $o1;
                $o1 += strlen($tmp[2]) + 1;
                $a1[$tmp[2]] = $_o1;
                $b1 .= chr(strlen($tmp[2])) . $tmp[2];
            } else {
                $_o1 = $a1[$tmp[2]];
            }
        }
        // area2
        if ($tmp[3] == '') {
            $_o2 = 0;
        } else {
            if (!isset($a2[$tmp[3]])) {
                $_o2 = $o2;
                $o2 += strlen($tmp[3]) + 1;
                $a2[$tmp[3]] = $_o2;
                $b2 .= chr(strlen($tmp[3])) . $tmp[3];
            } else {
                $_o2 = $a2[$tmp[3]];
            }
        }
        // write index
        fwrite($fw, pack('VVV', $tmp[0], $_o1, $_o2), 12);
    }

    // last line (endip + len of a1 + time);
    fwrite($fw, pack('VVV', $tmp[1], $o1, time()), 12);

    // area1, area2
    fwrite($fw, $b1, $o1);
    fwrite($fw, $b2, $o2);
}

fclose($fw);
echo ' DONE.', PHP_EOL;

// --- local functions ---
function _rec_cmp($a, $b)
{
    if ($a[0] == $b[0]) {
        return 0;
        /*if ($a[1] == $b[1]) {
            return 0;
        } elseif ($a[1] > $b[1]) {
            return 1;
        } else {
            return -1;
        }*/
    } elseif ($a[0] > $b[0]) {
        return 1;
    } else {
        return -1;
    }
}

function _rec_cmp2($a, $b)
{
    if ($a[0] == $b[0]) {
        return 0;
    } elseif ($a[0] > $b[0]) {
        return -1;
    } else {
        return 1;
    }
}

function _next_part($buf, &$start)
{
    while (strlen($buf) > $start) {
        $char = substr($buf, $start, 1);
        if (strpos(" \t\r\n", $char) === false) {
            break;
        }
        $start++;
    }
    $end = $start;
    while (strlen($buf) > $end) {
        $char = substr($buf, $end, 1);
        if (strpos(" \t\r\n", $char) !== false) {
            break;
        }
        $end++;
    }
    if ($end > $start) {
        $part = substr($buf, $start, $end - $start);
        $start = $end;
    } else {
        $part = '';
    }
    return $part;
}

function _ip2long($ip)
{
    $tmp = explode('.', $ip);
    if (count($tmp) != 4) {
        return false;
    }
    $ret = intval($tmp[3]);
    $ret += intval($tmp[2]) * 0x100;
    $ret += intval($tmp[1]) * 0x10000;
    $ret += intval($tmp[0]) * 0x1000000;
    return $ret;
}


