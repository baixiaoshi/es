<?php

namespace Db;


/**
 * mysql PDO操作类
 * @author Wander
 */
class MySqlDb
{
    public static $instances;
    /**
     * 当前SQL指令
     */
    private $sql = '';

    /**
     * 当前使用的连接
     */
    private $linkId = null;

    /**
     * 主库连接
     */
    private $mLinkId = null;
    /**
     * 从库连接
     */
    private $sLinkId = null;

    // 当前查询ID
    private $queryId = null;

    /**
     * 数据库连接参数配置
     */
    private $config = array();

    /**
     * 是否多库
     */
    private $multiServer = false;
    
    /**
     * 编码
     */
    private $charset = 'utf8';
    
    /**
     * 是否抛异常
     * @var bool
     */
    private $showException = true;

    // 事务层级数
    protected $transactionCounter = 0;

    /**
     * 架构函数
     * 数据库连接参数 主库master, 从库slave
     * MySqlDb constructor.
     * @param string $realBizDb
     */
    public function __construct()
    {
    

        $config = [];

        //线上数据库，拉线上数据的时候打开
        // $config['host'] = '119.29.123.26';
        // $config['port'] = '3306';
        // $config['usr'] = 'qiaozhi';
        // $config['pwd'] = 'eEZYwJHkwvr8euid';
        // $config['dbname'] = 'weixin';
        // $config['charset'] = 'utf8';
        // 
        $config['host'] = '172.16.17.155';
        $config['port'] = '3306';
        $config['usr'] = 'root';
        $config['pwd'] = '123456';
        $config['dbname'] = 'shunlian2';
        $config['charset'] = 'utf8';

        $this->config['slave'] = $config;
        $this->multiServer = false;
        $this->charset = 'utf8';
    }

    
    /**
     * @param $sql 执行的SQL语句
     * @param array $params 参数
     * @param bool $master 是否主库
     * @return null
     */
    public function query($sql, $params = array(), $master = true)
    {
        $this->sql = $sql;
        try {
            $this->initConnect($master);
            $this->queryId = $this->linkId->prepare($sql);
            $this->queryId->execute($params);
            return $this->queryId;
        } catch (Exception $e) {
            //$errorInfo = $e instanceof PDOException ? $e->errorInfo : null;
            $message = $e->getMessage();
            $this->show($message, $sql, $params);
        }
    }

    /**
     * 执行sql
     * @param string $sql 执行的SQL语句
     * @param array $params 参数
     * @param bool $master 是否主库
     * @return mixed
     */
    public function queryNew($sql, $params = array(), $master = true)
    {
        $this->sql = $sql;
        try {
            $this->initConnect($master);
            $this->queryId = $this->linkId->prepare($sql);
            return $this->queryId->execute($params);
        } catch (Exception $e) {
            //$errorInfo = $e instanceof PDOException ? $e->errorInfo : null;
            $message = $e->getMessage();
            $this->show($message, $sql, $params);
        }
    }

    /**
     * 插入数据并返回lastInsertId
     * @param string $tableName
     * @param array $params
     * @param bool $master
     * @return bool|string
     */
    public function insert($tableName, $params = [], $master = true)
    {
        if (empty($tableName) || empty($params)) {
            return false;
        }

        $keysArr = $valsArr = [];
        foreach ($params as $_field => $_val) {
            $keysArr[] = "`$_field`";

            if (is_null($_val)) {
                $valsArr[] = "NULL";
            } else {
                $valsArr[] = ":$_field";
            }
        }

        $sql = "INSERT INTO `$tableName` (". implode(', ', $keysArr) .") VALUES (". implode(', ', $valsArr) .")";

        $lastInsertId = '';

        $this->sql = $sql;
        try {
            $this->initConnect($master);
            $this->queryId = $this->linkId->prepare($sql);
            $flag = $this->queryId->execute($params);
            if ($this->queryId) {
                $lastInsertId = $this->linkId->lastInsertId();
                if ($lastInsertId == 0) {//表中没有自增的主键ID，返回是否成功
                    return $flag;
                }
            }
        } catch (Exception $e) {
            //$errorInfo = $e instanceof PDOException ? $e->errorInfo : null;
            $message = $e->getMessage();
            $this->show($message, $sql, $params);
            return false;
        }

        return $lastInsertId;
    }

    /**
     *
     * 更新记录，返回影响行数
     * @param $tableName
     * @param array $params
     * @param array $where
     * @return int|mixed
     */
    public function update($tableName, $params = array(), $where = array())
    {
        $affectedRows = 0;
        if (empty($params) || empty($where)) {
            return $affectedRows;
        }

        $bindData = [];
        $setStr = '';
        foreach ($params as $_field => $_val) {
            if (is_array($_val)) {
                if (strtolower($_val[0]) == 'exp') {
                    $setStr .= "`$_field`= " . $_val[1];
                } else {
                    return false; //无选项
                }
            } else {
                $setStr .= "`$_field`= ? , ";
                $bindData[] = $_val;
            }
        }
        $setStr = rtrim($setStr, ', ');
        $whereStr = '';
        foreach ($where as $_field => $_val) {
            $whereStr .= "`$_field`= ? , ";
            $bindData[] = $_val;
        }
        $whereStr = rtrim($whereStr, ', ');
        $sql = "UPDATE $tableName SET $setStr WHERE $whereStr";
        return $this->getAffectedRows($sql, $bindData, true);
    }

    /**
     * 删除记录，返回影响的条数
     * @param string $tableName
     * @param array $where
     * @param bool $master
     * @return bool|mixed
     */
    public function delete($tableName, $where = [], $master = true)
    {
        if (empty($where)) {
            return false;
        }

        $whereStr = '';
        foreach ($where as $_field => $_val) {
            $whereStr .= $_field . '=:' . $_field;
        }

        $sql = "DELETE FROM `$tableName` WHERE $whereStr";

        return $this->getAffectedRows($sql, $where, $master);
    }

    /**
     * 执行SQL语句并返回影响的行数
     * @param $sql
     * @param array $params
     * @param bool $master
     * @return mixed
     */
    public function getAffectedRows($sql, $params = array(), $master = true)
    {
        $this->sql = $sql;
        try {
            $this->initConnect($master);
            $this->queryId = $this->linkId->prepare($sql);
            $this->queryId->execute($params);
            return $this->queryId->rowCount();
        } catch (Exception $e) {
            //$errorInfo = $e instanceof PDOException ? $e->errorInfo : null;
            $message = $e->getMessage();
            $this->show($message, $sql, $params);
        }
    }

    /**
     * 获取单个数据
     * @param $sql
     * @param array $params
     * @param bool $master
     * @return mixed
     */
    public function getOne($sql, $params = array(), $master = true)
    {
        return $this->query($sql, $params, $master)->fetchColumn();
    }

    /**
     * 获取一行数据
     * @param $sql
     * @param array $params
     * @param bool $master
     * @return mixed
     */
    public function getRow($sql, $params = array(), $master = true)
    {
        return $this->query($sql, $params, $master)->fetch();
    }

    /**获取所有数据
     * @param $sql
     * @param array $params
     * @param bool $master
     * @return mixed
     */
    public function getList($sql, $params = array(), $master = true)
    {
        return $this->query($sql, $params, $master)->fetchAll();
    }

    /**
     * 获取所有数据
     * @param $sql
     * @param array $params
     * @param bool $master
     * @return mixed
     */
    public function getAll($sql, $params = array(), $master = true)
    {
        return $this->query($sql, $params, $master)->fetchAll();
    }

    /**
     * 分页数据(sql语句后面自动加上limit)
     * @param $sql
     * @param array $params
     * @param int $page
     * @param int $pageSize 默认是10
     * @param bool $master
     * @return array|mixed
     */
    public function getLimitList($sql, $params = array(), $page = 1, $pageSize = 10, $master = true)
    {
        if ($page < 1) {
            //throw new Exception('您所要查看的链接不存在。');
            return array();
        }
        
        $sql .= ' LIMIT ' . ($page - 1) * $pageSize . ', ' . $pageSize;
        return $this->getList($sql, $params, $master);
    }

    /**
     * @return mixed 影响行数
     */
    public function getRowCount()
    {
        return $this->linkId->rowCount();
    }

    /**
     * 开始事务
     * @param bool $master 主-从库要分清楚
     */
    public function begin($master = true)
    {
        $this->showException = true;
        $this->initConnect($master);
        ++$this->transactionCounter;
        if ($this->transactionCounter == 1) {
            $this->linkId->beginTransaction();
        }
    }

    /**
     * 提交事务
     */
    public function commit()
    {
        if ($this->linkId) {
            if ($this->transactionCounter == 1) {
                $this->linkId->commit();
            }
            --$this->transactionCounter;
            $this->showException = false;
        }
    }

    /**
     * 事务回滚
     */
    public function rollback()
    {
        if ($this->linkId) {
            if ($this->transactionCounter == 1) {
                $this->transactionCounter = 0;
                $this->linkId->rollBack();
            } else {
                --$this->transactionCounter;
            }
            $this->showException = false;
        } else {
            $this->show('rollback error');
        }
    }

    /**
     * 初始化数据库连接
     * @param bool $master
     */
    private function initConnect($master = true)
    {
        if ($master || ! $this->multiServer) {
            if ($this->mLinkId) {
                $this->linkId = $this->mLinkId;
            } else {
                $this->connect($master);
                $this->mLinkId = $this->linkId;
            }
        } else {
            if ($this->sLinkId) {
                $this->linkId = $this->sLinkId;
            } else {
                $this->connect($master);
                $this->sLinkId = $this->linkId;
            }
        }
    }
    /**
     * 连接数据库
     * @param bool $master
     */
    private function connect($master = true)
    {
        try {
            $this->linkId = $this->initPDO($master);
            if ($this->charset !== null) {
                $this->linkId->exec('set names ' . $this->charset);
            }
        } catch (\PDOException $e) {
            //$errorInfo = $e instanceof \PDOException ? $e->errorInfo : null;
            $message = $e->getMessage();
            $this->show($message);
        }
    }
    /**
     * 强制重新连接数据库方法
     */
    public function reConnect($master = true)
    {
        if ($master || ! $this->multiServer) {
            $this->connect($master);
            $this->mLinkId = $this->linkId;
        } else {
            $this->connect($master);
            $this->sLinkId = $this->linkId;
        }
    }

    /**
     * 实例化pdo连接对象
     * @param bool $master
     * @return \PDO
     */
    private function initPDO($master = false)
    {
        $dsn = 'mysql:host=%s;dbname=%s;port=%s';
        $username = '';
        $password = '';
        //$configMaster = $this->config['master'];
        $configSlave = $this->config['slave'];
        $master = false;
        if ($master) {
            $dsn = sprintf($dsn, $configMaster['host'], $configMaster['dbname'], $configMaster['port']);
            $username = $configMaster['usr'];
            $password = $configMaster['pwd'];
        } else {
            $dsn = sprintf($dsn, $configSlave['host'], $configSlave['dbname'], $configSlave['port']);
            $username = $configSlave['usr'];
            $password = $configSlave['pwd'];
        }

        $params = array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_CASE => \PDO::CASE_LOWER,
            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            \PDO::ATTR_AUTOCOMMIT => true
        );
        return new \PDO($dsn, $username, $password, $params);
    }

    /**
     * 打印错误
     * @param $message
     * @param string $sql
     * @param null $params
     * @return bool
     */
    protected function show($message, $sql = '', $params = null)
    {
        if ($this->showException) {
            throw new \Exception("message:$message\r\n sql:$sql\r\n");
        } else {
            return false;
        }
    }
    //析构函数是在对象销毁时调用的代码
    public function __destruct()
    {
        unset($this->linkId);
        unset($this->sLinkId);
        unset($this->mLinkId);
        unset($this->queryId);
    }
}
