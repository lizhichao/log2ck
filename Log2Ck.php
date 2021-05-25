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

    protected $last_time      = 0;
    protected $data           = [];
    protected $last_fail_time = 0;

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
    protected $db_conf      = [];

    public function __construct($db_conf, $table, array $fields)
    {
        $this->table_name   = $table;
        $this->table_fields = $fields;
        $this->db_conf      = $db_conf;
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
                $fn  = $this->log_fn;
                $row = $fn($info);
                if ($row !== false) {
                    $this->data[] = $row;
                }
            }
            if ($this->last_fail_time < time() - 3) {
                $this->save();
            }
        }
    }

    protected function getCk($i = 0)
    {
        try {
            $this->ck = new \OneCk\Client(
                $this->db_conf['host'],
                $this->db_conf['username'],
                $this->db_conf['password'],
                $this->db_conf['database']
            );
        } catch (\Exception $e) {
            echo 'error: ' . $e->getMessage() . PHP_EOL;
            return false;
        }
        if ($i) {
            return $this->start();
        }
        return true;
    }

    protected function end()
    {
        try {
            $this->ck->writeEnd();
            echo 'write end' . PHP_EOL;
        } catch (\Exception $e) {
            if ($this->getCk() === false) {
                echo "write end fail \n";
                return false;
            }
        }
        return $this->start(0);
    }


    protected function start($i = 0)
    {
        try {
            $this->ck->writeStart($this->table_name, $this->table_fields);
            echo 'write start' . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            echo 'start Exception:' . $e->getMessage() . PHP_EOL;
            if ($i === 0) {
                usleep(10000);
                if ($this->getCk()) {
                    return $this->start(++$i);
                }
            }
            echo "write start fail \n";
            return false;
        }
    }


    protected function write($i = 0)
    {
        try {
            $this->ck->writeBlock($this->data);
            echo 'write count:' . count($this->data) . PHP_EOL;
            return true;
        } catch (\Exception $e) {
            $this->last_fail_time = time();
            echo 'write Exception:' . $e->getMessage() . PHP_EOL;
            if ($i === 0) {
                usleep(10000);
                if ($this->getCk(1)) {
                    return $this->write(++$i);
                }
            }
            $this->data = array_slice($this->data, -$this->max_cache_data_len);
            echo 'date len:' . count($this->data) . PHP_EOL;
            return false;
        }
    }


    protected function save()
    {
        if (count($this->data) >= $this->min_block_len) {
            if ($this->write()) {
                $this->data = [];
            } else {
                return false;
            }
        }

        if (($this->last_time + $this->max_flow_during_time) < time()) {
            $this->last_time = time();
            if ($this->data) {
                if ($this->write()) {
                    $this->data = [];
                } else {
                    return false;
                }
            }
            return $this->end();
        }
        return true;
    }
}

