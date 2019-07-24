<?php

require_once ('Logger.php');
class Request
{

    private $apiKey;
    private $post = [];
    private $delete = [];
    private $idParam = null;

    public function __construct($apiKey, $idParam = null)
    {
        $this->apiKey = $apiKey;
        if (!empty($_POST)) $this->post = $_POST;

        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

        if ($contentType === "application/json") {
            //Receive the RAW post data.
            $content = trim(file_get_contents("php://input"));
            $decoded = json_decode($content, true);
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->post = $decoded;
            }
            else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                $this->delete = $decoded;
            }

        }
        $this->idParam = $idParam;
        $this->logRequest();

    }
    public function getPost()
    {
        return $this->post;
    }


    public function getDelete()
    {
        return $this->delete;
    }

    public function __get($name)
    {
       if ($name === 'apiKey') return $this->apiKey;
       elseif ($name === 'apiParam') return $this->idParam;
       else return '';
    }


    public function getQueryKey()
    {
        return ['key' => $this->apiKey];
    }


    private function logRequest()
    {
       Logger::log($_SERVER['REQUEST_METHOD'] . ' ' . $this->apiKey . ' && ' . $this->idParam);
    }
}