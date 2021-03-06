<?php

/**
 * Joracle Operation Class
 * Joracle IS USED TO OPERAT ORACLE DATABASE
 * Joracle.class.php是一个oracle数据库操作类，需要PHP的oci拓展支持
 *
 * @package     xxtime/Joracle.class.php
 * @author      joe@xxtime.com
 * @link        https://github.com/thendfeel/xxtime
 * @link        http://git.oschina.net/thendfeel/xxtime
 * @example     http://dev.xxtime.com
 * @copyright   xxtime.com
 * @since       2014-06-18
 * @update      2014-11-13
 */

//$config = array(
//    'host' => '127.0.0.1',
//    'port' => 1521,
//    'database' => 'db',
//    'username' => 'user',
//    'password' => '123456',
//);
//$db = new Joracle($config);
//$db->findByPk('user', 1);


class Joracle
{

    public $host = '127.0.0.1';

    public $port = 1521;

    public $database = '';

    public $username = '';

    public $password = '';

    public $charset = 'utf8';

    public $pk = 'id';

    public $debug = TRUE;

    public $callback = '';

    public $data = '';

    // 输出信息array json
    private $output = array();

    private $db;

    private $sql;

    private $stmt;

    public function __construct($config = array())
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
        $this->init();
    }

    public function __destruct()
    {
        $this->debug();
    }

    public function init()
    {
        $constring = $this->host . ':' . $this->port . '/' . $this->database;
        try {
            $this->db = @oci_connect($this->username, $this->password, $constring, $this->charset);
            if (!$this->db) {
                $e = oci_error();
                $this->output = array(
                    'error' => 1,
                    'msg' => $e['message']
                );
                die($this->output['msg']);
                throw new Exception($e['message']);
            }
        } catch (Exception $e) {
            exit();
        }
    }

    // 查询 @根据主键查询
    public function findByPk($table, $id = 0)
    {
        $sql = "SELECT * FROM $table WHERE $this->pk = '$id'";
        return $this->query($sql);
    }

    // 查询一条记录
    public function findOne($table, $conditions = array(), $field = array())
    {
        $where = $this->where($conditions) . ' AND ROWNUM = 1';
        $field = $this->field($field);
        $sql = "SELECT " . $field . " FROM $table WHERE " . $where;
        return $this->query($sql);
    }

    // 查询全部
    public function findAll($table, $conditions = array(), $order = '', $field = array())
    {
        $where = $this->where($conditions);
        $field = $this->field($field);
        $order = $this->order($order);
        $sql = "SELECT " . $field . " FROM $table WHERE " . $where . $order;
        return $this->query($sql);
    }

    // 分页查询
    public function find($table, $conditions = array(), $page = 1, $size = 20, $order = '', $field = array())
    {
        $offset = ($page - 1) * $size;
        $skip = $page * $size;
        $where = $this->where($conditions) . " AND ROWNUM >= $offset AND ROWNUM <= $skip";
        $field = $this->field($field);
        $order = $this->order($order);
        $sql = "SELECT " . $field . " FROM $table WHERE " . $where . $order;
        return $this->query($sql);
    }

    // 字典
    public function lists($table, $field1 = '', $field2 = '')
    {
        if (!$field2) {
            $sql = "SELECT $field1 FROM $table";
        } else {
            $sql = "SELECT $field1, $field2 FROM $table";
        }
        $ret = $this->query($sql);
        $result = array();
        if ($ret) {
            if (!$field2) {
                foreach ($ret as $value) {
                    $result[] = $value[$field1];
                }
            } else {
                foreach ($ret as $value) {
                    $result[$value[$field1]] = $value[$field2];
                }
            }
        }
        return $result;
    }

    // 添加记录
    public function add($table, $data = array(), $type)
    {
        $field = '';
        $value = '';
        if (!is_array($data)) {
            return FALSE;
        }
        foreach ($data as $k => $v) {
            $field .= "$k" . ',';
            if (isset($type[$k])) {
                if ($type[$k] == 'date') {
                    $value .= "to_date('$v','yyyy-mm-dd hh24:mi:ss'),";
                }
            } else {
                $value .= "'$v',";
            }
        }
        $field = trim($field, ',');
        $value = trim($value, ',');
        $sql = "INSERT INTO $table ($field) VALUES ($value)";
        return $this->execute($sql);
    }

    // 更新
    public function update($table, $id = 0, $newData = array())
    {
        $sql = "UPDATE $table SET " . $this->built($newData, ',') . " WHERE $this->pk = '$id'";
        return $this->execute($sql);
    }

    // 删除记录
    public function delete($table, $id = 0)
    {
        $sql = "DELETE FROM $table WHERE $this->pk = '$id'";
        return $this->execute($sql);
    }

    // 查询SQL
    public function query($sql)
    {
        $result = array();
        $this->executeStatements($sql);
        $rows = oci_fetch_all($this->stmt, $result, 0, -1, OCI_FETCHSTATEMENT_BY_ROW);
        if (count($result) == 1) {
            return $result[0];
        }
        return $result;
    }

    // 执行SQL
    public function execute($sql)
    {
        $this->executeStatements($sql);
        return oci_commit($this->db);
    }

    private function executeStatements($sql)
    {
        $this->sql = $sql;
        $this->stmt = oci_parse($this->db, $sql);
        return oci_execute($this->stmt);
    }


    /**
     * 构造SQL
     *
     * @param array $conditions
     * @param string $split
     * @param string $pre
     * @return string
     */
    private function built($conditions = array(), $split = 'AND', $pre = '')
    {
        $sql = '';
        if ($pre) {
            foreach ($conditions as $k => $v) {
                $sql .= " $pre.$k = '$v' " . $split;
            }
        } else {
            foreach ($conditions as $k => $v) {
                $sql .= " $k = '$v' " . $split;
            }
        }
        return rtrim($sql, $split);
    }

    /**
     * 构造查询字段field
     *
     * @param array|string $field
     * @return string
     */
    private function field($field)
    {
        if (empty($field)) {
            return '*';
        }
        if (is_array($field)) {
            $field = '' . implode(',', $field) . '';
        } else {
            $field = $field;
        }
        return $field;
    }

    /**
     * 构造条件where
     *
     * @param array|string $conditions
     * @return string
     */
    private function where($conditions)
    {
        if (empty($conditions)) {
            $where = '1 = 1';
        } elseif (is_int($conditions)) {
            $where = "$this->pk = '$conditions'";
        } elseif (is_array($conditions)) {
            $where = $this->built($conditions);
        } else {
            $where = $conditions;
        }
        return $where;
    }

    /**
     * 构造order
     *
     * @param array|string $order
     * @return string
     */
    private function order($conditions)
    {
        $order = ' ORDER BY ';
        if (empty($conditions)) {
            $order = '';
        } elseif (is_string($conditions)) {
            $order .= $conditions;
        } else {
            $order = '';
        }
        return $order;
    }

    /**
     * 回调函数
     */
    private function callback($param = NULL)
    {
        if ($param) {
            $this->callback_param = $param;
        }
        if (!$this->callback) {
            return FALSE;
        }
        $call = preg_split('/[\:]+|\-\>/i', $this->callback);
        if (count($call) == 1) {
            $this->data = call_user_func($call['0'], $this->callback_param);
        } else {
            $this->data = call_user_func_array(array(
                $call['0'],
                $call['1']
            ), array(
                $this->callback_param
            ));
        }
    }

    /**
     * 调试模式
     */
    private function debug()
    {
        if ($this->debug) {
            echo '<pre>';
            echo $this->sql;
            echo '<pre>';
        }
    }
}