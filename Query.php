<?php
/**
 * 简易IP地址信息转换查询（查询类）
 * 快速查询IP地址对应的信息描述，比如所属国家、城市、县市及网络服务商等。
 *
 * @author hightman <hightman@twomice.net>
 * @link http://www.hightman.cn/
 * @copyright Copyright &copy; 2018- Twomice Studio
 */
namespace hightman\ipconv;

/**
 * IP信息描述查询类
 *
 * ```php
 * $ipq = new \hightman\ipconv\Query();
 * $ipq->open('file.dat');
 * var_dump($ipq->query('210.32.1.1'));
 * var_dump($ipq->query('www.baidu.com'));
 * ```
 */
class Query
{
    const TAG = 'CTIP';
    private $_fd, $_meta, $_file;

    /**
     * 构造函数
     * @param string $file 数据文件
     */
    public function __construct($file = null)
    {
        if ($file !== null) {
            $this->_file = $file;
        } else {
            $this->_file = __DIR__ . DIRECTORY_SEPARATOR . 'ipconv.dat';
        }
    }

    /**
     * 析构关闭数据
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * 打开数据
     * 如果之前已打开过其它数据会自动将其关闭
     * @param string $file 数据文件
     * @throws \InvalidArgumentException
     */
    public function open($file = null)
    {
        if ($file === null) {
            $file = $this->_file;
        }
        if (($fd = @fopen($file, 'rb')) === false) {
            throw new \InvalidArgumentException('Unable to open data file.');
        }
        // check data file
        fseek($fd, 0, SEEK_SET);
        $tmp = unpack('a4flag/Vnum', fread($fd, 8));
        fseek($fd, $tmp['num'] * 12 + 8, SEEK_SET);
        $buf = fread($fd, 12);
        if ($tmp['flag'] != self::TAG || strlen($buf) != 12) {
            fclose($fd);
            throw new \InvalidArgumentException('Data file is incorrect or corrupt.');
        }
        $meta = [
            'flag' => $tmp['flag'],
            'num' => $tmp['num'],
            'off1' => $tmp['num'] * 12 + 20,
        ];
        $tmp = unpack('V3', $buf);
        $meta['off2'] = $meta['off1'] + $tmp[2];
        $meta['timestamp'] = $tmp[3];

        $this->close();
        $this->_fd = $fd;
        $this->_meta = $meta;
        $this->_file = $file;
    }

    /**
     * 检查并关闭已打开数据
     */
    public function close()
    {
        if ($this->_fd !== null) {
            fclose($this->_fd);
            $this->_fd = $this->_meta = null;
        }
    }

    /**
     * 获取查询结果
     * @param string $ip IPv4 地址或域名
     * @return array|bool 成功返回两段描述信息数组，不存在返回 false，出错则抛出异常
     * @throws \InvalidArgumentException
     */
    function query($ip)
    {
        $ip0 = ip2long($ip);
        if ($ip0 === false) {
            $ip0 = gethostbyname($ip);
            if ($ip0 === false || ($ip0 = ip2long($ip0)) === false) {
                throw new \InvalidArgumentException('Given IP address is invalid.');
            }
        }
        if ($this->_fd === null) {
            $this->open();
        }

        // binary search
        if ($ip0 < 0) {
            $ip0 = (float) sprintf('%u', $ip0);
        }
        $low = 0;
        $high = $this->_meta['num'] - 1;
        while ($low <= $high) {
            $mid = ($low + $high) >> 1;
            //echo 'ip0=', $ip0, ', low=', $low, ', high=', $high, ', mid=', $mid, PHP_EOL;
            $off = $mid * 12 + 8;
            fseek($this->_fd, $off, SEEK_SET);
            $buf = fread($this->_fd, 16);
            if (strlen($buf) != 16) {
                break;
            }
            $tmp = unpack('V4', $buf);
            // compare them
            if ($ip0 < $tmp[1]) {
                $high = $mid - 1;
            } elseif ($ip0 >= $tmp[4]) {
                $low = $mid + 1;
            } else {
                $res = ['', ''];
                if ($tmp[2] > 0) {
                    fseek($this->_fd, $this->_meta['off1'] + $tmp[2], SEEK_SET);
                    $len = ord(fread($this->_fd, 1));
                    $res[0] = fread($this->_fd, $len);
                }
                if ($tmp[3] > 0) {
                    fseek($this->_fd, $this->_meta['off2'] + $tmp[3], SEEK_SET);
                    $len = ord(fread($this->_fd, 1));
                    $res[1] = fread($this->_fd, $len);
                }
                return $res;
            }
        }
        return false;
    }

    /**
     * 获取数据版本信息
     * @return string
     */
    function version()
    {
        if ($this->_fd === null) {
            $this->open();
        }
        return sprintf('%s(RecordsNum=%d, BuildDate=%s)',
            $this->_meta['flag'], $this->_meta['num'], date('Y/m/d H:i:s', $this->_meta['timestamp']));
    }
}

