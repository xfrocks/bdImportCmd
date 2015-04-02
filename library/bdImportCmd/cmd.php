<?php

define('IMPORT_CMD', true);

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
    $jobStart = microtime(true);

    $controllerResponse = $fc->dispatch($routeMatch);

    if ($controllerResponse instanceof bdImportCmd_ControllerResponse_PossibleSteps) {
        $controllerResponse = $controllerResponse->dispatch($fc);
    }

    $fc->renderView($controllerResponse, $viewRenderer);

    $jobTime = microtime(true) - $jobStart;
    $memoryUsage = memory_get_usage() / 1048576;
    $memoryUsagePeak = memory_get_peak_usage() / 1048576;
    bdImportCmd_Helper_Terminal::log(
        "\t job time = %.6f, mem = %.2fM/%.2fM",
        $jobTime,
        $memoryUsage, $memoryUsagePeak
    );
}