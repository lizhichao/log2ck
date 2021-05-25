<?php
require __DIR__ . '/vendor/autoload.php';
function getLogInfo($str)
{
    $str = trim($str);
    $i   = strpos($str, '"');
    if ($i === false) {
        return [[], $str];
    }
    $arr = explode(' ', substr($str, 0, $i));
    return [$arr, substr($str, $i + 1)];
}

function array_get($arr, $k, $d = null)
{
    return isset($arr[$k]) ? $arr[$k] : $d;
}

// LogFormat "%{Host}i %{X-Forwarded-For}i-%h %{ms}T %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined
function get_row($str)
{
    $data = [
        'host'        => '',
        'ip'          => '0.0.0.0',
        'duration'    => null,
        'create_time' => null,
        'method'      => 'no',
        'url'         => '',
        'path'        => '',
        'code'        => '',
        'size'        => '',
        'refer'       => '',
        'refer_host'  => '',
        'user_agent'  => ''
    ];
    list($arr, $str) = getLogInfo($str);
    $data['host'] = array_get($arr, 0);
    $data['ip']   = array_get($arr, 1);
    if (strpos($data['ip'], '--') === 0) {
        $data['ip'] = substr($data['ip'], 2);
    } else {
        $i = strpos($data['ip'], ',');
        if ($i !== false) {
            $data['ip'] = substr($data['ip'], 0, $i);
        } else {
            $data['ip'] = substr($data['ip'], 0, strpos($data['ip'], '-'));
        }
    }
    $data['duration']    = array_get($arr, 2);
    $t                   = trim(array_get($arr, 3, ''), '[ ') . ' ' . trim(array_get($arr, 4), ' ]');
    $data['create_time'] = date('Y-m-d H:i:s', strtotime($t));

    list($arr, $str) = getLogInfo($str);
    $data['method'] = trim(array_get($arr, 0));
    $data['url']    = trim(array_get($arr, 1));
    $ur             = parse_url($data['url']);
    $data['path']   = $ur['path'];

    $i = stripos($data['path'], '.');
    if ($i) {
        $ext  = substr($data['path'], $i + 1);
        $exts = [
            'css' => 1,
            'js'  => 1,
            'txt' => 1,
            'png' => 1,
            'jpg' => 1,
            'gif' => 1
        ];
        if (isset($exts[$ext])) {
            return false;
        }
    }

    list($arr, $str) = getLogInfo($str);
    $data['code'] = trim(array_get($arr, 0));
    $data['size'] = trim(array_get($arr, 1));

    list($arr, $str) = getLogInfo($str);
    $data['refer'] = isset($arr[0]) ? trim($arr[0]) : '';
    if ($data['refer']) {
        $ur                 = parse_url($data['refer']);
        $data['refer_host'] = isset($ur['host']) ? $ur['host'] : '';
        $data['user_agent'] = trim($str, ' "');
    }
    foreach ($data as $k => $val) {
        if ($val === null) {
            return false;
        }
    }
    return $data;
}

$db_conf             = [];
$db_conf['host']     = 'tcp://192.168.23.129:9091';
$db_conf['username'] = 'default';
$db_conf['password'] = '123456';
$db_conf['database'] = 'test1';

$table       = 'web_log';
$server_name = 'web1';

$ck = new Log2Ck(
    $db_conf,
    $table,
    ['host', 'ip', 'duration', 'create_time',
        'method', 'url', 'path', 'code', 'size', 'refer',
        'refer_host', 'user_agent',
        'server_name']
);

$ck->min_block_len      = 5;
$ck->max_cache_data_len = 100;
$i                      = 0;

error_reporting(0);
$ck->regLogFn(function ($str) use ($table, &$i) {
    $arr = get_row($str);
    if ($arr) {
        $arr['server_name'] = ++$i;
    }
    return $arr;
})->run();