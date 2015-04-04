<?php

define('IMPORT_CMD', true);

$paramsDefault = array(
    'fork' => 0,
    'forkContinue' => 0,
    'stepStart' => 0,
    'mergeFork' => 0,
    'deleteForks' => '',
);
$params = $paramsDefault;
if (!empty($argv[1])) {
    parse_str($argv[1], $params);
    if (!empty($params)) {
        $params = array_merge($paramsDefault, $params);
    }
}
define('IMPORT_CMD_FORK', intval($params['fork']));
define('IMPORT_CMD_STEP_START', intval($params['stepStart']));

$adminPath = getcwd() . '/admin.php';
if (!file_exists($adminPath)) {
    die("The current directory must be XenForo root.\n");
}

require(dirname(__FILE__) . '/XenForo/Db.php');
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

if (IMPORT_CMD_FORK > 0) {
    // make sure the fork is good
    $fork = XenForo_Application::getDb()->fetchOne('
        SELECT data_value
        FROM xf_data_registry
        WHERE data_key = ?
    ', 'importSession' . IMPORT_CMD_FORK);

    if (empty($fork)) {
        if (IMPORT_CMD_STEP_START > 0) {
            // no data and stepStart is set, good
        } else {
            die("New fork, `stepStart` is required.\n");
        }
    } else {
        if (!empty($params['forkContinue'])) {
            // has data and forkContinue is set, good
        } else {
            die("Fork data already exists but no `forkContinue` is set.\n");
        }
    }
} elseif (!empty($params['mergeFork'])) {
    $fork = XenForo_Application::getDb()->fetchOne('
        SELECT data_value
        FROM xf_data_registry
        WHERE data_key = ?
    ', 'importSession' . $params['mergeFork']);
    if (empty($fork)) {
        die("Requested fork not found to merge.\n");
    }

    /** @var XenForo_Model_DataRegistry $dataRegistryModel */
    $dataRegistryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
    $allForks = XenForo_Application::getDb()->fetchAll('
        SELECT data_key, data_value
        FROM xf_data_registry
        WHERE data_key LIKE "importSession%"
    ');
    $otherForkIds = array();
    foreach ($allForks as $allFork) {
        $allForkId = intval(substr($allFork['data_key'], -1));
        if ($allForkId == 0) {
            continue;
        }
        if ($allForkId == $params['mergeFork']) {
            continue;
        }

        $otherForkIds[] = $allForkId;
    }
    sort($otherForkIds);
    if (!empty($otherForkIds)) {
        if (empty($params['deleteForks'])) {
            die("Other forks have been found, `deleteForks` is required.\n");
        } else {
            // if there are forks (other than the main one and the one being merged)
            // `deleteForks` must be specified to confirm the deletion
            // we can of course delete them all but doing so seems to be dangerous
            $deleteForks = explode(',', $params['deleteForks']);
            $deleteForks = array_map('intval', $deleteForks);
            sort($deleteForks);

            if (serialize($otherForkIds) !== serialize($deleteForks)) {
                die("`deleteForks` must acknowledge all other forks. Use `forks=1` to check.\n");
            }

            foreach ($otherForkIds as $otherForkId) {
                $dataRegistryModel->delete('importSession' . $otherForkId);
            }
        }
    }

    $forkData = unserialize($fork);
    $oldData = $dataRegistryModel->get('importSession');
    $mergedData = XenForo_Application::mapMerge($oldData, $forkData);
    $dataRegistryModel->set('importSession', $mergedData);
    $dataRegistryModel->delete('importSession' . $params['mergeFork']);

    die("Merged fork " . $params['mergeFork'] . "\n");
} elseif (!empty($params['forks'])) {
    $forks = XenForo_Application::getDb()->fetchAll('
        SELECT data_key, data_value
        FROM xf_data_registry
        WHERE data_key LIKE "importSession%"
    ');

    foreach ($forks as $fork) {
        $forkData = unserialize($fork['data_value']);

        echo(sprintf(
            "Fork #%d (since %d): step %s at %d\n",
            intval(substr($fork['data_key'], -1)),
            !empty($forkData['_bdImportCmd_stepStart']) ? $forkData['_bdImportCmd_stepStart'] : 0,
            !empty($forkData['step']) ? $forkData['step'] : 'N/A',
            $forkData['stepStart']
        ));
    }

    die("No more forks.\n");
}

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