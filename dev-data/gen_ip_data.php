<?php
/**
 * 生成国内IP转换省市信息
 *
 * Usage:
 * php gen_area.php <type> [input file]
 *   - input file: default to ../raw/cz88.txt
 *
 * @author hightman <hightman@twomice.net>
 * @link http://www.hightman.cn/
 * @copyright Copyright &copy; 2018- Twomice Studio
 */
ini_set('memory_limit', '1024M');
set_time_limit(0);

include 'runtime/area.inc.php';
include 'runtime/country.inc.php';

// load input file
$file = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : __DIR__ . '/raw/cz88.txt';
if (($fr = @fopen($file, 'r')) === false) {
    fwrite(fopen('php://stderr', 'w'), 'ERROR: cannt open input file.' . PHP_EOL);
    exit(-1);
}

// record skipped
$fs = @fopen('runtime/ip2area.skip.txt', 'w');

// init country data
if (($fd = fopen('runtime/ip_country.txt', 'r')) !== false) {
    while ($line = fgets($fd, 1024)) {
        $row = explode("\t", trim($line));
        if (count($row) === 3) {
            if (isset($COUNTRIES[$row[2]])) {
                $row[2] = $COUNTRIES[$row[2]];
            }
            print_line($row[0], $row[1], $row[2]);
        } else {
            $fs && fwrite($fs, $line);
        }
    }
    fclose($fd);
}

// init scws object
$scws = scws_new();
$scws->set_charset('utf8');
$scws->set_dict('runtime/area_dict.txt', SCWS_XDICT_TXT);

define('_ATTR_PROV', 0x01);
define('_ATTR_CITY', 0x02);
define('_ATTR_XIAN', 0x04);

// areainfo
$_COUNTRIES = array_flip($COUNTRIES);
$country = '中国';
$areainfo = $AREAINFO['CN'];

// convert the data
while ($line = fgets($fr, 1024)) {
    $off = 0;
    $ip1 = _next_part($line, $off);
    if (strpos($ip1, '/') === false) {
        $ip2 = _next_part($line, $off);
    } else {
        $ip2 = '';
    }
    $area1 = $area11 = _next_part($line, $off);
    $area2 = $area22 = trim(substr($line, $off));
    if (empty($area1) || empty($ip1)) {
        continue;
    }

    // check is foreign
    if (!strstr($area1, '香港') && !strstr($area1, '台湾') && !strstr($area1, '澳门')) {
        if (isset($_COUNTRIES[$area1])) {
            print_line($ip1, $ip2, $area1);
            continue;
        } elseif (is_foreign($ip1)) {
            $fs && fwrite($fs, $line);
            continue;
        }
    }

    // get words
    $area1 = fix_area1($area1);
    $words = [];
    $scws->send_text($area1); // . ' ' . $area2);
    while ($ret = $scws->get_result()) {
        foreach ($ret as $tmp) {
            if (strlen($tmp['word']) < 6) {
                continue;
            }
            $attr = intval($tmp['attr']);
            if (!$attr) {
                continue;
            }
            if (!isset($words[$tmp['word']])) {
                $words[$tmp['word']] = $attr;
            } else {
                $words[$tmp['word']] |= $attr;
            }
        }
    }

    // fetch the prov
    $prov = _get_word($words, _ATTR_PROV);

    // fetch the city
    do {
        $city = _get_word($words, _ATTR_CITY);
    } while ($city !== '' && !_check_city($city, $prov));

    // fetch the xian
    do {
        $xian = _get_word($words, _ATTR_XIAN);
    } while ($xian !== '' && !_check_xian($xian, $city, $prov));

    // result
    $area2 = '';
    if ($prov != '' || $area1 == '中国') {
        $area1 = $country;
        if ($prov != '') {
            $area1 .= '/' . $prov;
            if ($city != '') {
                $area1 .= '/' . $city;
                if ($xian != '') {
                    //$area1 .= '/' . $xian;
                }
            }
        }
        $area2 = _get_isp($area11 . ' ' . $area22);
        // output IP/xx = 18??
        print_line($ip1, $ip2, $area1, $area2);
    } else {
        $fs && fwrite($fs, $line);
    }
}
fclose($fr);
$fs && fclose($fs);

// --- local func ---
function is_foreign($ip)
{
    static $ipc = null;
    if (is_null($ipc)) {
        require_once '../Query.php';
        $ipc = new \hightman\ipconv\Query('runtime/ip2country.dat');
    }
    if ($pos = strpos($ip, '/')) {
        $ip = substr($ip, 0, $pos);
    }
    try {
        $ret = $ipc->query($ip);
        if (!isset($ret[0]) || in_array($ret[0], ['HK', 'TW', 'CN', 'MO', 'AP'])) {
            return false;
        }
    } catch (\Exception $e) {

    }
    return true;
}

function fix_area1($area1)
{
    static $fixes = [];
    if (empty($area1)) {
        return $area1;
    }
    if (isset($fixes[$area1])) {
        return $fixes[$area1];
    }
    if (empty($fixes)) {
        if (($fd = @fopen('raw/area_fixed.txt', 'r')) !== false) {
            while ($line = fgets($fd, 1024)) {
                $off = 0;
                $old = _next_part($line, $off);
                $new = _next_part($line, $off);
                if (empty($old) || empty($new)) {
                    continue;
                }
                $fixes[$old] = $new;
            }
            fclose($fd);
        }
        $fixes['IANA机构'] = '';
        $fixes['CZ88.NET'] = '';
        $fixes['纯真网络'] = '';
        $fixes['IANA保留地址'] = '';
        $fixes['保留地址'] = '';
        $fixes['珊瑚虫版IP数据库'] = '';
    }
    foreach ($fixes as $key => $value) {
        if (strstr($area1, $key) !== false) {
            return $value;
        }
    }
    return $area1;
}

function print_line($ip1, $ip2, $area1, $area2 = '')
{
    echo trim(sprintf('%-20.20s %-16.16s %s %s', $ip1, $ip2, $area1, $area2)) . PHP_EOL;
}

function _next_part($buf, &$start)
{
    while (strlen($buf) > $start) {
        $char = substr($buf, $start, 1);
        if (strpos(" \0\t\r\n", $char) === false) {
            break;
        }
        $start++;
    }
    $end = $start;
    while (strlen($buf) > $end) {
        $char = substr($buf, $end, 1);
        if (strpos(" \0\t\r\n", $char) !== false) {
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

function _get_word(&$words, $flag)
{
    foreach ($words as $word => $attr) {
        if ($attr & $flag) {
            unset($words[$word]);
            return $word;
        }
    }
    return '';
}

function _check_city($city, &$prov)
{
    global $areainfo;
    if ($prov != '' && isset($areainfo[$prov])) {
        return isset($areainfo[$prov][$city]);
    }
    foreach ($areainfo as $prov2 => $cities) {
        if (is_array($cities) && isset($cities[$city])) {
            //echo "# $city -> $prov2\n";
            $prov = $prov2;
            return true;
        }
    }
    return false;
}

function _check_xian($xian, &$city, &$prov)
{
    global $areainfo;
    // city != ''
    if ($city != '') {
        return isset($areainfo[$prov][$city][$xian]);
    }
    // city == '' && prov != ''
    if ($prov != '') {
        if (!is_array($areainfo[$prov])) {
            return false;
        }
        foreach ($areainfo[$prov] as $city2 => $xians) {
            if (is_array($xians) && isset($xians[$xian])) {
                $city = $city2;
                return true;
            }
        }
        return false;
    }
    // city == '' && prov == ''    
    foreach ($areainfo as $prov2 => $cities) {
        if (!is_array($cities)) {
            continue;
        }
        foreach ($cities as $city2 => $xians) {
            if (is_array($xians) && isset($xians[$xian])) {
                //echo "#$xian -> $city2 -> $prov2\n";
                $prov = $prov2;
                $city = $city2;
                return true;
            }
        }
    }
    return false;
}

// 电信, 网通, 铁通, 联通, 有线通, 教育网, 移动, 广电网, 科技网, 网吧, 长城宽带, 视讯宽带
function _get_isp($str)
{
    if (strstr($str, '电信通')) {
        return '电信通';
    } elseif (strstr($str, '电信')) {
        return '电信';
    } elseif (strstr($str, '网通')) {
        return '网通';
    } elseif (strstr($str, '长城宽带')) {
        return '长城宽带';
    } elseif (strstr($str, '视讯宽带')) {
        return '视讯宽带';
    } elseif (strstr($str, '铁通')) {
        return '铁通';
    } elseif (strstr($str, '联通')) {
        return '联通';
    } elseif (strstr($str, '有线通')) {
        return '有线通';
    } elseif (strstr($str, '教育网')) {
        return '教育网';
    } elseif (strstr($str, '移动')) {
        return '移动';
    } elseif (strstr($str, '实验室') || strstr($str, '高中') || strstr($str, '中学')
        || strstr($str, '学院') || strstr($str, '学校') || strstr($str, '科学楼')
        || strstr($str, '小学') || strstr($str, '大学') || strstr($str, '一中')
        || strstr($str, '二中') || strstr($str, '三中') || strstr($str, '四中')
        || strstr($str, '五中') || strstr($str, '六中') || strstr($str, '七中')
        || strstr($str, '八中') || strstr($str, '九中') || strstr($str, '十中')) {
        return '教育网';
    } elseif (strstr($str, '广电网')) {
        return '广电网';
    } elseif (strstr($str, '科技网')) {
        return '科技网';
    } elseif (strstr($str, '网吧') || strstr($str, '宾馆') || strstr($str, '网络会所')
        || strstr($str, '网络广场') || strstr($str, '号机')) {
        return '网吧';
    } else {
        return '';
    }
}

