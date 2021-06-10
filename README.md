
A simple log collector, Monitor files through `tail -F ` command, write to the Clickhouse.
Very low occupancy of resources, It can process more than 100 thousand log information per second

一个简单的日志收集器，占用极低的资源(比 `Logstash`，`Fluentd`，`Logtail`占用资源更少)，每秒可以轻松收集几十万以上的日志信息。

## install

`composer require lizhichao/log2ck`

## example

`tail -F apapche/access.log | php test.php`

```php
$db_conf             = [];
$db_conf['host']     = 'tcp://192.168.23.129:9091';
$db_conf['username'] = 'default';
$db_conf['password'] = '123456';
$db_conf['database'] = 'test1';

$table       = 'web_log';
$server_name = 'web1';

$ck = new Log2Ck(
    $db_conf, // 
    $table, // table 
    [
    'host', 'ip', 'duration', 
    'create_time', 'method', 'url', 'path', 'code', 'size', 
    'refer', 'refer_host', 'user_agent', 
    'server_name'
    ] //field name
);
$ck->regLogFn(function($row){
    // 自己解析 $row
    return $array; //和上面的字段对应
})->run();

```


