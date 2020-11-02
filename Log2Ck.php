<?php

/**
 * Class Log2Ck
 * @author tanszhe 1018595261@qq.com
 */

class Log2Ck
{
    public $max_flow_during_time = 3;
    public $min_block_len        = 100;
    public $max_cache_data_len   = 100000;

    protected $last_time = 0;
    protected $data      = [];
    /**
     * @var null|OneCk\Client
     */
    protected $ck = null;
    /**
     * @var null|\Closure
     */
    protected $log_fn       = null;
    protected $table_name   = '';
    protected $table_fields = [];

    public function __construct($table, array $fields)
    {
        $this->table_name   = $table;
        $this->table_fields = $fields;
        $this->getCk(1);
    }

    public function regLogFn($call)
    {
        $this->log_fn = $call;
        return $this;
    }

    protected function listenBlock($fd)
    {
        while (true) {
            yield fgets($fd);
        }
    }

    protected function listen($fd)
    {
        do {
            $read   = [$fd];
            $write  = [];
            $except = [];
            $result = stream_select($read, $write, $except, 0);
            echo time() . PHP_EOL;
            if ($result === 0) {
                sleep(1);
                $result = 2;
                yield null;
            } else {
                yield fgets($fd);
            }
        } while ($result);

    }

    public function run()
    {
        foreach ($this->listenBlock(STDIN) as $info) {
            if ($info) {
                $fn = $this->log_fn;
                $row = $fn($info);
                if ($row !== false) {
                    $this->data[] = $row;
                }
            }
            $this->save();
        }
    }

    protected function getCk($i = 0)
    {
        $arr      = getopt('h:u:p:d:');
        $args     = [];
        $args[]   = (isset($arr['h']) && $arr['h']) ? $arr['h'] : 'tcp://127.0.0.1:9000';
        $args[]   = (isset($arr['u']) && $arr['u']) ? $arr['u'] : 'default';
        $args[]   = (isset($arr['p']) && $arr['p']) ? $arr['p'] : '';
        $args[]   = (isset($arr['d']) && $arr['d']) ? $arr['d'] : 'default';
        $this->ck = new \OneCk\Client(...$args);
        if ($i) {
            $this->start();
        }
    }

    protected function end()
    {
        try {
            $this->ck->writeEnd();
            echo 'write end' . PHP_EOL;
        } catch (\Exception $e) {
            $this->getCk();
            echo "write end fail \n";
        }
        $this->start(0);
    }


    protected function start($i = 0)
    {
        try {
            $this->ck->writeStart($this->table_name, $this->table_fields);
            echo 'write start' . PHP_EOL;
        } catch (\Exception $e) {
            echo 'start Exception:' . $e->getMessage() . PHP_EOL;
            if ($i === 0) {
                $this->getCk();
                usleep(100000);
                $this->start(++$i);
            } else {
                echo "write start fail \n";
            }
        }
    }

    protected function write($i = 0)
    {
        try {
            $this->ck->writeBlock($this->data);
            echo 'write count:' . count($this->data) . PHP_EOL;
        } catch (\Exception $e) {
            echo 'write Exception:' . $e->getMessage() . PHP_EOL;
            if ($i === 0) {
                $this->getCk(1);
                usleep(100000);
                $this->write(++$i);
            } else {
                $this->data = array_slice($this->data, -$this->max_cache_data_len);
            }
        }
    }


    protected function save()
    {
        if (count($this->data) >= $this->min_block_len) {
            $this->write();
            $this->data = [];
        }

        if (($this->last_time + $this->max_flow_during_time) < time()) {
            $this->last_time = time();
            if ($this->data) {
                $this->write();
                $this->data = [];
            }
            $this->end();
        }
    }
}

