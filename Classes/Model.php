<?php

class Model
{
    private $pdo;
    private static $instance;

    public static function getConnection(Array $settings)
    {
        if (empty(self::$instance)) {
            self::$instance = new self($settings);
        }
        return self::$instance;
    }

    private function __construct(Array $settings)
    {
        if ($settings['database'] && $settings['database']['useme']) {
            $sets = $settings['database'];
            if (!empty($sets['host']) &&
                !empty($sets['db']) &&
                !empty($sets['user']) &&
                !empty($sets['pass']) &&
                !empty($sets['charset'])) {
                $this->connect($sets);
            } else {
                throw new Exception('DB settings not specified');
            }
        } else if ($settings['sqlite'] && $settings['sqlite']['useme']) {
            $this->connect($settings['sqlite'], true);
        }

    }

    /**
     * @param array $settings
     * @param bool $sqlite
     */
    private function connect(Array $settings, $sqlite = false)
    {
        if (!$sqlite) {
            if (!empty($settings['port'])) {
                $dsn = "mysql:host={$settings['host']};port={$settings['port']};dbname={$settings['db']};charset={$settings['charset']}";
            } else {
                $dsn = "mysql:host={$settings['host']};dbname={$settings['db']};charset={$settings['charset']}";
            }

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                $this->pdo = new PDO($dsn, $settings['user'], $settings['pass'], $options);
            } catch (\PDOException $e) {
                throw new \PDOException('Fuck! ' . $e->getMessage(), (int)$e->getCode());
            }
        } else {
            try {
                $this->pdo = new PDO('sqlite:' . $settings['filename'] ? : 'database.db');
            } catch (\PDOException $e) {
                throw new \PDOException($e->getMessage(), (int)$e->getCode());
            }
        }

        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS expenses (
                                                               external_key TEXT NOT NULL, 
                                                               expense_id TEXT, 
                                                               expense_date TEXT NOT NULL, 
                                                               expense_categ TEXT NOT NULL, 
                                                               expense_descr TEXT, 
                                                               expense_sum TEXT NOT NULL)');
        } catch (PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }


    }


    /**
     * @param $query
     * @param array $params
     * @return mixed
     */
    public function fetchAll($query, $params = [])
    {
        if (!$params){
            $st3 = $this->pdo->query($query, PDO::FETCH_ASSOC);
            return $st3->fetchAll();
        } else {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        }
    }



    public function fetchOne($query, $params = [])
    {
        if (!$params){
            $st3 = $this->pdo->query($query);
            $res = $st3->fetch(PDO::FETCH_NUM);
        } else {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $res = $stmt->fetch(PDO::FETCH_NUM);
        }
        if (!empty($res[0])) return $res[0];
        return null;
    }


    protected function prepareInsert(Array $array)
    {
        $part1 = [];
        $part2 = [];
        foreach ($array as $key => $value) {
            $part1[] = $key;
            $part2[] = ':' . $key;
        }
        $p1 = implode(', ', $part1);
        $p2 = implode(', ', $part2);
        return ('(' . $p1 . ') VALUES (' . $p2 . ')');
    }


    public function insert($table, Array $insertion)
    {
        try {
            $SQL = 'INSERT INTO `' . $table . '` ' . $this->prepareInsert($insertion);
            $this->pdo->prepare($SQL)->execute($insertion);
            return 0;
        } catch (Exception $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }


    public function validateExpense(Array & $entry)
    {
        return (!empty($entry['expense_id']) &&
            !empty($entry['expense_date']) &&
            !empty($entry['expense_sum']) &&
            !empty($entry['expense_categ'])) ? true : false;
    }


    /**
     * @param $table
     * @param $id
     * @return bool
     * @throws Exception
     */
    public function delete($table, $field, $id){
        try {
            if ($id !== 'all') {
                $sql = "DELETE FROM `" . strval($table) . "` WHERE {$field} = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
            } else {
                $sql = "DELETE FROM `" . strval($table) . "` WHERE {$field} IS NOT NULL";
                $this->pdo->exec($sql);
            }
            return true;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }
}