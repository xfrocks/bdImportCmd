<?php

define('DEFERRED_CMD', true);

/* start parsing command line options */
$getoptShort = '';
$getoptLong = array();
// action list
$getoptShort .= 'l';
$getoptLong[] = 'list';
// action run
$getoptShort .= 'r::';
$getoptLong[] = 'run::';
// action run params
$getoptShort .= 'p::';
$getoptLong[] = 'params::';
// parse options
$opt = getopt($getoptShort, $getoptLong);
// verify requested action via options
$action = '';
if (isset($opt['l']) || isset($opt['list'])) {
    $action = 'list';
} elseif (!empty($opt['r']) || !empty($opt['run'])) {
    $action = 'run';
}
/* finished parsing command line options */

/* start bootstrap-ing XenForo */
$fileDir = getcwd();
$autoloaderPath = $fileDir . '/library/XenForo/Autoloader.php';
if (!file_exists($autoloaderPath)) {
    die("The current directory must be XenForo root.\n");
}
require($autoloaderPath);
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');
XenForo_Application::initialize($fileDir . '/library', $fileDir);
$dependencies = new XenForo_Dependencies_Public();
$dependencies->preLoadData();

/** @var XenForo_Model_Deferred $deferredModel */
$deferredModel = XenForo_Model::create('XenForo_Model_Deferred');
/* finished bootstrap-ing XenForo */

switch ($action) {
    case 'list':
        $runnable = $deferredModel->getRunnableDeferreds(true);
        echo("Available tasks:\n");
        foreach ($runnable as $_runnable) {
            echo(sprintf('Task %2$d %3$s: `php %1$s --run=%4$s`', $argv[0],
                $_runnable['deferred_id'],
                $_runnable['execute_class'],
                $_runnable['unique_key']));
            echo("\n");

            $executeData = unserialize($_runnable['execute_data']);
            if (!empty($executeData['batch'])) {
                echo(sprintf("  batch=%d\n", $executeData['batch']));
            }
            if (!empty($executeData['start'])) {
                echo(sprintf("  start=%d\n", $executeData['start']));
            }

            if ($_runnable['execute_class'] === 'SearchIndex'
                && !empty($executeData['extra_data'])
            ) {
                if (!empty($executeData['extra_data']['current_type'])) {
                    echo(sprintf("  extra_data[current_type]=%s\n", $executeData['extra_data']['current_type']));
                }
                if (!empty($executeData['extra_data']['type_start'])) {
                    echo(sprintf("  extra_data[type_start]=%d\n", $executeData['extra_data']['type_start']));
                }
            }

            echo("\n");
        }
        echo(sprintf("No more tasks (shown %d).\n", count($runnable)));
        break;
    case 'run':
        @set_time_limit(0);
        $uniqueKey = '';
        if (!empty($opt['r'])) {
            $uniqueKey = $opt['r'];
        } elseif (!empty($opt['run'])) {
            $uniqueKey = $opt['run'];
        }

        $runnable = $deferredModel->getRunnableDeferreds(true);
        $deferred = null;
        foreach ($runnable as $_runnable) {
            if ($_runnable['unique_key'] === $uniqueKey) {
                $deferred = $_runnable;
                break; // foreach
            }
        }
        if (empty($deferred)) {
            echo(sprintf("Task %s could not be found.\n", $uniqueKey));
            break;
        }

        /* start overwriting task execute data */
        $params = '';
        if (!empty($opt['p'])) {
            $params = $opt['p'];
        } elseif (!empty($opt['params'])) {
            $params = $opt['params'];
        }
        parse_str($params, $params);
        if (!empty($params)) {
            $executeData = unserialize($deferred['execute_data']);
            $executeData = XenForo_Application::mapMerge($executeData, $params);
            $deferred['execute_data'] = serialize($executeData);
        }
        /* finished overwriting task execute data */

        echo(sprintf("Running task %s...\n", $uniqueKey));
        $i = 0;
        while (true) {
            $response = $deferredModel->runDeferred($deferred, 0, $status, $canCancel);
            if (is_numeric($response)) {
                // run again
                $deferred = $deferredModel->getDeferredById($response);

                if ($i % 200 === 0) {
                    if (preg_match('#\((?<number>[^\)]+)\)$#', $status, $matches)) {
                        echo($matches['number']);
                    } else {
                        echo(sprintf("\n%s\n", $status));
                    }
                } else {
                    echo('.');
                }
            } elseif ($response === false) {
                echo("Done!\n");
                break;
            } else {
                echo(sprintf("Unknown response: %s", var_export($response, true)));
                break; // while(true)
            }

            $i++;
        }
        break;
    default:
        echo("Action could not be recognized...\n");
        echo("\n");
        echo("--list to list available tasks\n");
        echo("\n");
        echo("--run=task to run specified task, also available:\n");
        echo("  --params: specify extra params to run task\n");
        echo("\n");
        break;
}

