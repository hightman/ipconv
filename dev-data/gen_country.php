<?php
/**
 * 生成全球国家缩写及全称信息
 *
 * Usage:
 * php gen_country.php [input file]
 *   - input file: default to ../raw/country.3166
 *
 * @author hightman <hightman@twomice.net>
 * @link http://www.hightman.cn/
 * @copyright Copyright &copy; 2018- Twomice Studio
 */

// load input file
$file = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : __DIR__ . '/raw/country.3166';
if (($fd = @fopen($file, 'r')) === false) {
    fwrite(fopen('php://stderr', 'w'), 'ERROR: cannt open input file.' . PHP_EOL);
    exit(-1);
}

$data = [];
while ($row = fgetcsv($fd)) {
    $abbr = $row[0];
    //if ($abbr === 'TW' || $abbr === 'MO' || $abbr === 'HK') {
    //  continue;
    //}
    $full = isset($row[2]) && !empty($row[2]) ? $row[2] : $row[1];
    $data[$abbr] = $full;
}
fclose($fd);

echo '<?php', PHP_EOL;
echo '// countries list from: ', $file, PHP_EOL;
echo '// updated: ', date('Y/m/d H:i:s'), PHP_EOL;
echo '$COUNTRIES = ', var_export($data, true), ';', PHP_EOL;
