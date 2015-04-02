<?php

$adminPath = getcwd() . '/admin.php';
if (!file_exists($adminPath)) {
    die("The current directory must be XenForo root.\n");
}

require(dirname(__FILE__) . '/XenForo/Dependencies/Admin.php');
require(dirname(__FILE__) . '/XenForo/FrontController.php');
require(dirname(__FILE__) . '/XenForo/Session.php');
require($adminPath);

/** @var bdImportCmd_XenForo_FrontController $fc */
$fc->setup();
$fc->setRequestPaths();
$fc->getDependencies()->preLoadData();

$routeMatch = $fc->route();
$viewRenderer = $fc->getViewRenderer();

set_time_limit(0);
$fc->getResponse()->headersSentThrowsException = false;

while (true) {
    $controllerResponse = $fc->dispatch($routeMatch);
    $fc->renderView($controllerResponse, $viewRenderer);
}