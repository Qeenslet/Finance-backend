<?php
require_once ('Classes/Controller.php');
require_once ('Classes/Router.php');

$router = new Router(new Controller());
$router->addRoute('/entries', 'actionEntries');
$router->addRoute('/total', 'actionTotal');
$router->addRoute('/entries/ids', 'actionAllIds');
$router->addRoute('/summary', 'actionSummary');
//echo '<pre>'; print_r($router);