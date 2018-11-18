<?php
/**
 * 国内分级地区信息转换
 *
 * Usage:
 * php gen_area.php <type> [input file]
 *   - type: output type, can be php, dict
 *   - input file: default to ../raw/area_list.txt
 *
 * @author hightman <hightman@twomice.net>
 * @link http://www.hightman.cn/
 * @copyright Copyright &copy; 2018- Twomice Studio
 */

$type = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : 'php';

// load input file
$file = isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : __DIR__ . '/raw/area_list.txt';
if (($fd = @fopen($file, 'r')) === false) {
    fwrite(fopen('php://stderr', 'w'), 'ERROR: cannt open input file.' . PHP_EOL);
    exit(-1);
}

$data = [];
$keys = ['CN'];
while ($line = fgets($fd, 256)) {
    for ($i = 0; $line[$i] == "\t"; $i++) {
        ;
    }
    $key = trim($line);
    if ($key == '') {
        continue;
    }
    while ($i < (count($keys) - 1)) {
        array_pop($keys);
    }
    $rec = &$data;
    foreach ($keys as $tmp) {
        if (!isset($rec[$tmp])) {
            $rec[$tmp] = [];
        }
        $tmp = &$rec[$tmp];
        unset($rec);
        $rec = &$tmp;
        unset($tmp);
    }
    if (!isset($rec[$key])) {
        $rec[$key] = [];
    }
    unset($rec);
    array_push($keys, $key);
}
fclose($fd);

// skip foreign data
unset($data['CN']['海外']);

// result
if ($type === 'dict') {
    $dict = [];
    add_dict($dict, $data['CN']);
    foreach ($dict as $key => $attr) {
        $idf = $tf = 1;
        // 层级越上去优先级越高，省 > 市 > 县
        if ($attr & 2) {
            $tf |= 2;
        }
        if ($attr & 1) {
            $tf |= 4;
        }
        echo $key, "\t", $tf, "\t", $idf, "\t", $attr, PHP_EOL;
    }
} else {
    // add other countries
    require 'common.inc.php';
    include 'runtime/country.inc.php';
    foreach ($COUNTRIES as $abbr => $full) {
        if ($abbr !== 'CN') {
            $data[$abbr] = false;
        }
    }
    // show result
    echo "<?php\n";
    echo "// area info from: $file\n";
    echo "// updated: " . date("Y/m/d H:i:s") . "\n";
    echo draw_array('AREAINFO', $data);
}

// --- local functions ---
function add_dict(&$dict, $data, $level = 1)
{
    foreach ($data as $key => $value) {
        if (!isset($dict[$key])) {
            $dict[$key] = 0;
        }
        $dict[$key] |= $level;
        if (is_array($value)) {
            add_dict($dict, $value, ($level << 1));
        }
    }
}
