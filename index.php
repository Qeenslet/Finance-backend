<?php
require_once ('Classes/Controller.php');
require_once ('Classes/Router.php');

$router = new Router(new Controller());
//API-specific routes
$router->addAPIRoute('/entries', 'actionEntries');
$router->addAPIRoute('/total', 'actionTotal');
$router->addAPIRoute('/entries/ids', 'actionAllIds');
$router->addAPIRoute('/summary', 'actionSummary');
$router->addAPIRoute('/clear', 'actionClearRepo');
$router->addAPIRoute('/operations', 'actionOperations');
$router->addAPIRoute('/chunk/{ID}', 'actionChunk');
$router->addAPIRoute('/next_chunks/{ID}', 'actionNextChunks');
$router->addAPIRoute('/chunks', 'actionChunks');
$router->addAPIRoute('/settings', 'actionSettings');
//General routes
$router->addRoute('/', 'actionIndex');
$router->addRoute('/generate', 'generateAPIKey');
$router->addRoute('/generate/new/json', 'generateKeyJson');
