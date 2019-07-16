<?php
require_once ('Classes/Controller.php');
require_once ('Classes/Router.php');

$router = new Router(new Controller());
//API routes
$router->addAPIRoute('/entries', 'actionEntries');
$router->addAPIRoute('/total', 'actionTotal');
$router->addAPIRoute('/entries/ids', 'actionAllIds');
$router->addAPIRoute('/summary', 'actionSummary');
//Page Routes
$router->addRoute('/', 'actionIndex');
$router->addRoute('/generate', 'generateAPIKey');
