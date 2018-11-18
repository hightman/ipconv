<?php
/**
 * 公共函数信息
 *
 * @author hightman <hightman@twomice.net>
 * @link http://www.hightman.cn/
 * @copyright Copyright &copy; 2018- Twomice Studio
 */

/**
 * Quote array value
 * @param mixed $value
 * @return string
 */
function quote_value($value)
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    } elseif (is_int($value)) {
        return strval($value);
    } elseif (is_array($value)) {
        // placeholder
        return 'true';
    } else {
        return "'" . str_replace("'", "\\'", $value) . "'";
    }
}

/**
 * Draw an array with good looking
 * @param $name
 * @param $array
 * @return string
 */
function draw_array($name, $array)
{
    $child = $base = '';
    $last_key = 0;
    foreach ($array as $key => $value) {
        if (is_array($value) && count($value) == 0) {
            $value = false;
        }
        if (is_numeric($key) && $key == $last_key) {
            $base .= quote_value($value) . ', ';
            $last_key++;
        } elseif (!is_array($value)) {
            $base .= '\'' . $key . '\' => ' . quote_value($value) . ', ';
        }
        if (is_array($value)) {
            $key = $name . '[' . quote_value($key) . ']';
            $child .= draw_array($key, $value);
        }
    }
    $out = '$' . $name . ' = [';
    if ($base != '') {
        $out .= substr($base, 0, -2);
    }
    $out .= '];' . PHP_EOL . $child;
    return $out;
}
