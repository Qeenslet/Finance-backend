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
            $autoIncremet = 'INT AUTO_INCREMENT';
            if (!empty($settings['port'])) {
                $dsn = "{$settings['type']}:host={$settings['host']};port={$settings['port']};dbname={$settings['db']}";
            } else {
                $dsn = "{$settings['type']}:host={$settings['host']};dbname={$settings['db']}";
            }
            if ($settings['charset'] && $settings['type'] !== 'pgsql') {
                $dsn .= ";charset={$settings['charset']}";
            }
            if ($settings['type'] === 'pgsql') {
                $autoIncremet = 'SERIAL';
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
            $this->pdo->exec('CREATE TABLE if NOT EXISTS deleted (expense_id TEXT NOT NULL, delete_day TEXT NOT NULL, external_key TEXT NOT NULL )');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS chunks (id ' . $autoIncremet . ' PRIMARY KEY, 
                                                                          chunk_key TEXT NOT NULL,
                                                                          external_key TEXT NOT NULL, 
                                                                          date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS operations (
                                                                   external_key TEXT NOT NULL,
                                                                   command TEXT NOT NULL,
                                                                   chunk_key TEXT NOT NULL, 
                                                                   operation_data TEXT NOT NULL)');
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
            $SQL = 'INSERT INTO ' . $table . ' ' . $this->prepareInsert($insertion);
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
            if (is_array($field) && is_array($id) && count($field) == count($id) && isset($field[0])) {
                $sql = "DELETE FROM " . strval($table) . " WHERE";
                foreach ($field as $k => $one) {
                    if ($k) {
                        $sql .= " AND {$one} = ?";
                    } else {
                        $sql .= " {$one} = ?";
                    }

                }
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($id);
            } else {
                if ($id !== 'all') {
                    $sql = "DELETE FROM " . strval($table) . " WHERE {$field} = :id";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                } else {
                    $sql = "DELETE FROM " . strval($table) . " WHERE {$field} IS NOT NULL";
                    $this->pdo->exec($sql);
                }
            }
            return true;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }


    public function truncateRepo($externalKey)
    {
        try{
            $this->delete('deleted', 'external_key', $externalKey);
            $this->delete('expenses', 'external_key', $externalKey);
            return true;
        } catch (Exception $e) {
            return false;
        }

    }


    public function executeOpearion(Array & $operation)
    {
        $raw = json_decode($operation['operation_data'], true);
        $raw['external_key'] = $operation['external_key'];
        if ($operation['command'] === 'ADD') {
            unset($raw['chunk_key']);
            $this->insert('expenses', $raw);
        } elseif ($operation['command'] === 'DEL') {
            $this->delete('expenses', ['expense_id', 'external_key'], [$raw['expense_id'], $raw['external_key']]);
        }
    }
}