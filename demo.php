<?php
/**
 * 简易IP地址信息转换查询（演示）
 * 快速查询IP地址对应的信息描述，比如所属国家、城市、县市及网络服务商等。
 *
 * @author hightman <hightman@twomice.net>
 * @link http://www.hightman.cn/
 * @copyright Copyright &copy; 2018- Twomice Studio
 */

// usage for cli
if (php_sapi_name() === 'cli' && !isset($_SERVER['argv'][1])) {
    echo 'Usage: ', $_SERVER['argv'][0], ' <IP or hostname>', PHP_EOL;
    exit(-1);
}

// start script
require __DIR__ . '/Query.php';
$ipc = new \hightman\ipconv\Query();

// get IP
if (isset($_SERVER['argv'][1])) {
    $ip = $_SERVER['argv'][1];
} elseif (isset($_GET['ip'])) {
    $ip = $_GET['ip'];
} elseif (isset($_SERVER['REMOTE_ADDR'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
} else {
    $ip = '';
}
if ($ip !== '') {
    try {
        $timeBegin = microtime(true);
        $res = $ipc->query($ip);
        $timeCost = microtime(true) - $timeBegin;

        $output = '<hr>"' . htmlspecialchars($ip) . '" 的查询结果：<br><br>';
        if ($res === false) {
            $output .= '<span color="red" data-cli="31">*NotFound*</span>';
        } else {
            $output .= '<span color="blue" data-cli="36">' . implode(' ', $res) . '</span>';
        }
        $output .= '<br><small>(耗时' . $timeCost . '秒)</small>';
    } catch (\Exception $e) {
        $error = $e->getMessage();
        $output = '<hr>*ERROR*<br><br>' . htmlspecialchars($error);
    }
}

// cli
if (php_sapi_name() == 'cli') {
    function cli_output($str)
    {
        $str = str_replace('<hr>', '-----------------------------------' . PHP_EOL, $str);
        $str = str_replace('<br>', PHP_EOL, $str);
        $str = preg_replace('#<span .+?data-cli="(\d+)">(.+?)</span>#s', "\033[1;\\1m\\2\033[m", $str);
        $str = html_entity_decode(trim(strip_tags($str))) . PHP_EOL;
        return $str;
    }

    ob_start('cli_output');
    echo $output;
    exit(0);
}
?>
<!doctype html>
<html>
<head>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8">
    <title>MIC(MyIPConv) Demo</title>
</head>
<body>
<h1>IPConv - demo</h1>
<div>数据库版本：<?php echo $ipc->version() ?></div>
<form method="get">
    要查的IP/域名：<input type="text" name="ip" size=20 onmouseover="this.select()" value="<?php echo htmlspecialchars($ip) ?>">
    <input type="submit" value=" 查询! "><br>
</form>
<?php echo $output ?>
</body>
</html>
