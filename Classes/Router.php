<?php

require_once ('Request.php');
require_once ('Logger.php');
class Router
{
    private $uri;
    private $api_routes = [];
    private $routes = [];
    private $controller;
    private $api_routes_id = [];
    private $api_key = null;

    public function __construct(Controller $controller)
    {
        $this->uri = $_SERVER['REQUEST_URI'];
        $this->controller = $controller;
        if (!empty($_SERVER['HTTP_FINANCE_KEY'])) $this->api_key = $_SERVER['HTTP_FINANCE_KEY'];


    }

    public function addAPIRoute($route, $action)
    {
        if (strpos($route, '{ID}')){
            $route = str_replace('/', '\/', $route);
            $route = str_replace('{ID}', '(.*)', $route);
            $route = '/^' . $route . '$/';
            $this->api_routes_id[$route] = $action;
        } else {
            $route = str_replace('/', '\/', $route);
            $route = '/^' . $route . '$/';
            $this->api_routes[$route] = $action;
        }

    }

    public function addRoute($route, $action)
    {
        $this->routes[$route] = $action;
    }

    /**
     * @throws Exception
     */
    private function parseRoute()
    {
        foreach ($this->api_routes_id as $rt => $act) {
            if (preg_match($rt, $this->uri, $matches) && $this->api_key) {
                Logger::log('Incoming: ' . $this->uri);
                $this->controller->{$act}(new Request($this->api_key, $matches[1]));
                return;
            }
        }
        foreach ($this->api_routes as $route => $action) {
            if (preg_match($route, $this->uri) && $this->api_key) {
                $this->controller->{$action}(new Request($this->api_key));
                return;
            }
        }
        foreach ($this->routes as $r => $a) {
            if ($this->uri === $r) {
                $this->controller->{$a}();
                return;
            }
        }
        throw new Exception('Wrong API call: ' . $this->uri, 404);
    }


    /**
     *
     */
    public function __destruct()
    {
        try {
            $this->parseRoute();
        } catch (Exception $e) {
            $this->controller->handleError($e);
        }

    }
}