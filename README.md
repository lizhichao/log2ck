
A simple log collector, Monitor files through `tail -F ` command, write to the Clickhouse.
Very low occupancy of resources, It can process more than 100 thousand log information per second

一个简单的日志收集器，占用极低的资源(比 `Logstash`，`Fluentd`，`Logtail`占用资源更少)，每秒可以轻松收集几十万以上的日志信息。

## install

`composer require lizhichao/log2ck`

## example

`tail -F apapche/access.log | php apache_log.php -h tcp://127.0.0.1:9000 -u default -p 123456 -d logs -t apache_log`

options : 

- `-h` clickhouse host port
- `-u` clickhouse user name
- `-p` clickhouse password
- `-d` clickhouse database name
- `-t` clickhouse table name

