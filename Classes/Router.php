<?php

require_once ('Request.php');
require_once ('Logger.php');
class Router
{
    private $uri;
    private $routes = [];
    private $controller;

    public function __construct(Controller $controller)
    {
        $this->uri = $_SERVER['REQUEST_URI'];
        $this->controller = $controller;
    }

    public function addRoute($route, $action)
    {
        $route = str_replace('/', '\/', $route);
        $route = '/^\/(.*)' . $route . '$/';
        $this->routes[$route] = $action;
    }


    /**
     * @throws Exception
     */
    private function parseRoute()
    {
        foreach ($this->routes as $route => $action) {
            if (preg_match($route, $this->uri, $matches)) {
                Logger::log('Incoming: ' . $this->uri);
                $this->controller->{$action}(new Request($matches[1]));
                return;
            }
        }
        if ($this->uri === '/') $this->controller->actionIndex();
        else
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