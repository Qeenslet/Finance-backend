<?php
require_once ('Model.php');
require_once ('Logger.php');

class Controller
{
    private $model;
    private $settings;

    public function __construct()
    {
        $this->settings = require_once ('config.php');
        $this->settings['database']['host'] = getenv('DATABASE_HOST') ? : $this->settings['database']['host'];
        $this->settings['database']['type'] = getenv('DATABASE_TYPE') ? : $this->settings['database']['type'];
        $this->settings['database']['port'] = getenv('DATABASE_PORT') ? : $this->settings['database']['port'];
        $this->settings['database']['user'] = getenv('DATABASE_USER') ? : $this->settings['database']['user'];
        $this->settings['database']['pass'] = getenv('DATABASE_PASS') ? : $this->settings['database']['pass'];
        $this->settings['database']['db'] = getenv('DATABASE_NAME') ? : $this->settings['database']['db'];
        try {
            $this->model = Model::getConnection($this->settings);
        } catch (Exception $e) {
            $this->handleError($e);
            return;
        }
    }


    public function actionIndex()
    {
        //$aaa = $this->model->fetchAll("SELECT * FROM chunks");
        //echo '<pre>'; print_r($aaa);
        echo file_get_contents('index.html');
    }

    /**
     * @param Request $request
     */
    public function actionEntries(Request $request)
    {
        if (!empty($request->getPost())){
            $saved = 0;
            $notValidated = [];
            $alreadyExists = 0;
            try {
                foreach ($request->getPost() as $entry) {
                    if ($this->model->validateExpense($entry)){
                        $check = $this->model->fetchOne("SELECT expense_id FROM expenses
                                                                WHERE external_key = :key
                                                                AND expense_id = :id", ['key' => $request->apiKey,
                            'id' => $entry['expense_id']]);
                        if (!$check) {
                            $entry['external_key'] = $request->apiKey;
                            $this->model->insert('expenses', $entry);
                            $saved++;
                        } else {
                            $alreadyExists++;
                        }
                    } else {
                        $notValidated[] = $entry;
                    }
                }
                $result['saved'] = $saved;
                $result['rejected'] = $notValidated;
                $result['skiped'] = $alreadyExists;
                $this->output($this->wrapResult('result', $result, $request->apiKey));
            } catch (Exception $e){
                $this->handleError($e);
            }

            return;
        } elseif (!empty($request->getDelete())) {
            $errors = [];
            if (is_array($request->getDelete())) {
                foreach ($request->getDelete() as $k => $id) {
                    try{
                        $this->model->delete('expenses', ['expense_id', 'external_key'], [$id['expense_id'], $request->apiKey]);
                        $check = $this->model->fetchOne("SELECT expense_id FROM deleted
                                                                WHERE external_key = :key
                                                                AND expense_id = :id", ['key' => $request->apiKey,
                            'id' => $id['expense_id']]);
                        if (!$check) {
                            $id['external_key'] = $request->apiKey;
                            $this->model->insert('deleted', $id);
                        }

                    } catch (Exception $e) {
                        $errors[] = $e->getMessage();
                    }

                }
            } else {
                $errors[] = 'Wrong request format';
            }
            if ($errors) {
                $this->output($this->wrapResult('errors', $errors, $request->apiKey));
                return;
            }

        }
        $data = $this->model->fetchAll("SELECT expense_id,
                                                     expense_date,
                                                     expense_categ,
                                                     expense_descr,
                                                     expense_sum
                                              FROM expenses 
                                              WHERE external_key = :key", $request->getQueryKey());
        $data2 = $this->model->fetchAll("SELECT expense_id, 
                                                      delete_day 
                                               FROM deleted 
                                               WHERE external_key = :key", $request->getQueryKey());
        $result = ['real' => $data, 'deleted' => $data2];
        $this->output($this->wrapResult('entries', $result, $request->apiKey));
    }


    /**
     * @param $apiKey
     * @param array $post
     * @throws Exception
     */
    public function actionTotal(Request $request)
    {
        if (!empty($request->post)) {
            throw new Exception('Wrong HTTP API method', 400);
        }
        $data = $this->model->fetchOne('SELECT COUNT(expense_id) AS total 
                                               FROM expenses 
                                               WHERE external_key = :key', $request->getQueryKey());
        $this->output($this->wrapResult('total', intval($data), $request->apiKey));
    }


    /**
     * @param $apiKey
     * @param array $post
     * @throws Exception
     */
    public function actionAllIds(Request $request)
    {
        if (!empty($request->post)) {
            throw new Exception('Wrong API method', 400);
        }
        $data = $this->model->fetchAll('SELECT expense_id 
                                               FROM expenses 
                                               WHERE external_key = :key', $request->getQueryKey());
        $this->output($this->wrapResult('ids', $data, $request->apiKey));
    }


    public function actionSummary(Request $request)
    {
        $total = $this->model->fetchOne("SELECT COUNT(expense_id) 
                                                FROM expenses 
                                                WHERE external_key = :key", $request->getQueryKey());
        $sumRaw = $this->model->fetchAll("SELECT expense_sum FROM expenses WHERE external_key = :key", $request->getQueryKey());
        $sum = 0;
        foreach ($sumRaw as $raw) {
            $sum += floatval($raw['expense_sum']);
        }
        $data['entries'] = intval($total);
        $data['sum'] = !empty($sum) ? $sum : 0;
        $this->output($this->wrapResult('summary', $data, $request->apiKey));
    }


    public function handleError(Exception $e)
    {
        $data['error'] = $e->getMessage();
        $data['trace'] = $e->getTrace();
        $data['code'] = $e->getCode();
        $this->output($data);
    }

    /**
     * @param $responseKeyName
     * @param $responseData
     * @param $apiKey
     * @return array
     */
    private function wrapResult($responseKeyName, $responseData, $apiKey)
    {
        $data = [];
        $data[$responseKeyName] = $responseData;
        $data['api'] = $apiKey;
        return $data;
    }


    /**
     * @param array $results
     */
    private function output(Array $results)
    {
        Logger::log('Outputting...' . serialize($results));
        header('Content-Type: application/json');
        echo json_encode($results);
    }



    public function generateAPIKey()
    {
        echo 'HERE I do generate API key!!! Key is: ' . $this->generateRandomKey();
        //$this->model->delete('deleted', ['expense_id', 'external_key'], ['atdphiod5o2019-07-17', 'mytestenvironment2019-07-11']);
    }


    /**
     * @param int $length
     * @return string
     * @throws Exception
     */
    private function generateRandomKey($length = 24)
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length));
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length));
        }
        $date = new DateTime();
        $rand = rand(500, 6000);
        return md5(md5($date->format('Y-m-d H:i:s') . ($rand * $rand) . $date->format('l \t\h\e jS')));
    }

    /**
     * @throws Exception
     */
    public function generateKeyJson()
    {
        $result = ['api_key' => $this->generateRandomKey()];
        $this->output($result);
    }



    public function actionClearRepo(Request $request)
    {
        if (!empty($request->getDelete())) {
            if ($this->model->truncateRepo($request->apiKey)) {

                $this->output($this->wrapResult('result', 'ok', $request->apiKey));
                return;
            } else {
                $this->handleError(new Exception('some fucking problem happened', 666));
                return;
            }
        }
        $this->actionSummary($request);

    }


    public function actionOperations(Request $request)
    {
        if (!empty($request->getPost())) {
            try{
                $chunk_key = $this->generateRandomKey(32);
                foreach ($request->getPost() as $operation) {
                    $operation['chunk_key'] = $chunk_key;
                    $operation['external_key'] = $request->apiKey;
                    $raw = json_decode($operation['operation_data'], true);
                    $operation['operation_data'] = json_encode($raw);
                    $this->model->insert('operations', $operation);
                    $this->model->executeOpearion($operation);
                }
                $this->model->insert('chunks', ['chunk_key' => $chunk_key, 'external_key' => $request->apiKey]);
                $this->output($this->wrapResult('chunk_key', $chunk_key, $request->apiKey));
                return;
            } catch (Exception $e){
                $this->handleError($e);
                return;
            }

        }
        $data = $this->model->fetchAll('SELECT * FROM operations WHERE external_key = :key', $request->getQueryKey());
        $this->output($this->wrapResult('operations', $data, $request->apiKey));
    }


    /**
     * @param Request $request
     */
    public function actionChunk(Request $request)
    {
        $param = $request->apiParam;
        if ($param) {
            $data = $this->model->fetchAll('SELECT command,
                                                          chunk_key,
                                                          operation_data
                                                  FROM operations WHERE external_key = :key
                                                  AND chunk_key = :chunk', array_merge($request->getQueryKey(), ['chunk' => $param]));
            $this->output($this->wrapResult('operations', $data, $request->apiKey));
            return;
        }
        $this->handleError(new Exception('Wrong API call params', 400));
    }

    /**
     * @param Request $request
     */
    public function actionNextChunks(Request $request)
    {
        $param = $request->apiParam;
        if ($param) {
            $data = $this->model->fetchAll('SELECT chunk_key 
                                                  FROM chunks 
                                                  WHERE id > (SELECT id 
                                                              FROM chunks 
                                                              WHERE chunk_key = :chunk
                                                              AND external_key = :key1) AND external_key = :key2
                                                              ORDER BY id', ['chunk' => $request->apiParam, 'key1' => $request->apiKey, 'key2' => $request->apiKey]);
            $this->output($this->wrapResult('chunks', $data, $request->apiKey));
            return;
        }
        $this->handleError(new Exception('Wrong API call params', 400));
    }



    public function actionChunks(Request $request)
    {
        $data = $this->model->fetchAll('SELECT chunk_key 
                                                  FROM chunks
                                               WHERE external_key = :key
                                                 ORDER BY id', $request->getQueryKey());
        $this->output($this->wrapResult('chunks', $data, $request->apiKey));
        return;
    }
}