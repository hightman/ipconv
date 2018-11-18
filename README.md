简易IP地址信息转换查询
===================

这是一个用 [PHP](http://php.net) 编写的 IPv4 地址信息转换查询库。
常用作于快速将 IP 地址转换为所在省市等地理位置、网络服务提供商等信息，
以便针对用户提供本地化的定制应用。

同时还包含一份基于纯真网络IP库的信息整理国内省市信息查询数据，可将 IPv4
地址转换为国内的省市及网络商信息，以及海外的国家名称。

- 纯 PHP 编写无其它依赖模块
- 采用二分查找，查询性能高
- 提供数据生成工具，方便定制数据
- 免费开源，基于 MIT license


Requirements
-------------

PHP >= 5.4.0


Install
-------

### Install from an Archive File

Extract the archive file downloaded from [ipconv-master.zip](https://github.com/hightman/ipconv/archive/master.zip)
to your project. And then add the library file into your program:

```php
require '/path/to/Query.php';
```

### Install via Composer

If you do not have [Composer](http://getcomposer.org/), you may install it by following the instructions
at [getcomposer.org](http://getcomposer.org/doc/00-intro.md#installation-nix).

You can then install this library using the following command:

~~~
php composer.phar require "hightman/ipconv:*"
~~~


Usage
-------

### 查询

使用非常简单，只需在实例化 `\hightman\ipconv\Query` 类后，调用 `Query()` 方法即可。
示范代码如下：

```php
use hightman\ipconv\Query;

// 1. 实例化查询对象
// 默认会加载自带的 ipconv.dat，提供国内城市信息检索
// 如需指定自定义数据文件，请调用 open 方法
$ipc = new Query();
//$ipc->open('/path/to/file.dat');

// 2. 执行查询，支持 IPv4 地址、域名
// 如果有数据匹配，返回结果为两段信息描述组成的数组
// 如果没有匹配的数据返回 false ，如果出错则抛出 \InvalidArgumentException 异常
try {
     var_dump($ipc->query('210.32.1.1'));
     var_dump($ipc->query('haiman.io'));
} catch (\InvalidArgumentException $e) {
}

// 3. 打印版本信息
echo $ipc->version();
```


### Demo

支持命令行、网页形式访问内置的 `demo.php`，无需多说，跑起来就懂。


### 定制创建数据

你可以通过附带的 `build-data.php` 根据自己的需求打造数据，实现通过 IP 地址快速查询任意信息。
用法参考如下：

```
php build-data.php <input file> [output file]
```

  - 必填参数 nput file: IP 信息数据文本文件，格式详见后述
  - 选填参数 output file: 输出文件存储位置，默认同输入路径并将后缀改为 .dat


### 用于输入的数据格式说明

格式为纯文本文件，每行为一条记录，每条记录由 4 个字段组成，字段时间用任意空格或制表符分隔，
空格数量任意均可，纯真 IP 数据导出后存为 UTF-8 即可直接使用。因此，仅最后那个字段内容可以包含空格。

对数据无排序要求，即使地址范围重叠、断档亦无所谓，转换程序会自动进行修正，这对于编写信息文件将十分方便。
四个字段的含义如下：

```
<起始IP>  <结束IP>  <信息描述1，常为国家或省市>  <信息描述2，允许为空>
```

如果起始IP信息表述为 CIDR 的形式，则忽略结束IP字段，每个信息描述字段最多可达 255 字节。用法举例如下：

```
218.10.6.45     218.10.6.57     黑龙江省齐齐哈尔市 网通
218.10.6.58     218.10.6.58     黑龙江省齐齐哈尔市 梅里斯区雅尔塞镇欣欣网吧
218.10.6.75     218.10.6.75     黑龙江省齐齐哈尔市拜泉县 超人网吧
218.10.6.59     218.10.6.74     黑龙江省齐齐哈尔市 网通

210.32.0.0/16                   浙江省杭州市  浙江大学
```


### About dev-data

...


Contact me
-----------

If you have any questions, please report on github [issues](https://github.com/hightman/iconv/issues)
