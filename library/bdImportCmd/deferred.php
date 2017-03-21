<?php

define('DEFERRED_CMD', true);

$config = array(
    'runsPerReport' => 20,
    'eachRunSeconds' => 30,
    'memoryLimit' => 100 * 1024 * 1014, // 100M
);

/* start parsing command line options */
$getoptShort = 'c::';
$getoptLong = array('config::');
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

require('./bootstrap.php');

if (XenForo_Application::isRegistered('_bdCloudServerHelper_readonly')) {
    die('Cannot run with [bd] Cloud Server Helper READONLY mode enabled.');
}

/** @var XenForo_Model_Deferred $deferredModel */
$deferredModel = XenForo_Model::create('XenForo_Model_Deferred');

$configXF = XenForo_Application::getConfig()->get('bdImportCmd');
if (!empty($configXF)) {
    $config = array_merge($config, is_array($configXF) ? $configXF : $configXF->toArray());
}
if (!empty($opt['c']) || !empty($opt['config'])) {
    $configOpt = !empty($opt['c']) ? $opt['c'] : $opt['config'];
    parse_str($configOpt, $configOpt);
    if (!empty($configOpt)) {
        $config = array_merge($config, $configOpt);
    }
}
foreach ($config as $configKey => $configValue) {
    echo(sprintf("\$config[%s] = %s\n", $configKey, var_export($configValue, true)));
}

switch ($action) {
    case 'list':
        function printArray(array $array, array $parents = array())
        {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $childParents = $parents;
                    $childParents[] = $key;
                    printArray($value, $childParents);
                } else {
                    $echoPaths = $parents;
                    $echoPaths[] = $key;
                    $echoFirst = array_shift($echoPaths);
                    echo(sprintf("  %s%s = %s\n",
                        $echoFirst,
                        count($echoPaths) > 0 ? sprintf('[%s]', implode('][', $echoPaths)) : '',
                        var_export($value, true)
                    ));
                }
            }
        }

        $runnable = $deferredModel->getRunnableDeferreds(true);
        echo("Available tasks:\n");
        foreach ($runnable as $_runnable) {
            echo(sprintf('Task %2$d %3$s: `php %1$s --run=%4$s`', $argv[0],
                $_runnable['deferred_id'],
                $_runnable['execute_class'],
                !empty($_runnable['unique_key']) ? $_runnable['unique_key'] : $_runnable['deferred_id']));
            echo("\n");

            $executeData = @unserialize($_runnable['execute_data']);
            if (is_array($executeData)) {
                printArray($executeData, array('params'));
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
            if ($_runnable['unique_key'] === $uniqueKey
                || intval($_runnable['deferred_id']) === intval($uniqueKey)
            ) {
                $deferred = $_runnable;
                break; // foreach
            }
        }
        if (empty($deferred)) {
            // try to resolve class name
            if (class_exists($uniqueKey)) {
                $deferredId = $deferredModel->defer($uniqueKey, array(), null, true);
                $deferred = $deferredModel->getDeferredById($deferredId);
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

        $GLOBALS['_terminate'] = false;
        if (function_exists('pcntl_signal')) {
            $signalFunc = create_function('', 'echo("\nTerminating...");$GLOBALS["_terminate"] = true;');
            pcntl_signal(SIGINT, $signalFunc);
            pcntl_signal(SIGTERM, $signalFunc);
        } else {
            echo("Signal support is not available, Ctrl+C to stop running may cause incomplete task data!\n");
            $signalFunc = '';
        }

        echo(sprintf("Start running task %s @ %s...\n", $uniqueKey, gmdate('c')));
        $i = 0;
        $iStep = max(1, $config['runsPerReport']);
        $startTime = microtime(true);
        while (true) {
            if ($signalFunc !== '') {
                pcntl_signal_dispatch();
            }

            if ($GLOBALS['_terminate'] === true) {
                $response = false;
            } else {
                $response = $deferredModel->runDeferred($deferred, $config['eachRunSeconds'], $status, $canCancel);
                sleep(1);
            }

            if (is_numeric($response)) {
                $mem = memory_get_usage();

                if ($mem < $config['memoryLimit']) {
                    $deferred = $deferredModel->getDeferredById($response);
                } else {
                    $GLOBALS['_terminate'] = true;
                    $i = $iStep;
                    echo("Terminating due to memory constrain...\n");
                }

                if ($i % $iStep === 0) {
                    $mem = sprintf('%sM', number_format($mem / 1024 / 1024, 1));

                    $time = microtime(true) - $startTime;
                    $timeUnit = 's';
                    if ($time > 60) {
                        $time /= 60;
                        $timeUnit = 'm';
                    }
                    if ($time > 60) {
                        $time /= 60;
                        $timeUnit = 'h';
                    }
                    $time = sprintf('%s%s', number_format($time, 1), $timeUnit);

                    if (preg_match('#\((?<number>[^\)]+)\)$#', $status, $matches)) {
                        echo(sprintf('%s (mem=%s, time=%s)', $matches['number'], $mem, $time));
                    } else {
                        echo(sprintf("\n%s / mem=%s / time=%s\n", $status, $mem, $time));
                    }
                } else {
                    echo('.');
                }
            } elseif ($response === false) {
                if (empty($GLOBALS['_terminate'])) {
                    echo("Done\n\n");
                    echo(sprintf("Finished running task %s @ %s\n", $uniqueKey, gmdate('c')));
                }
                break; // while(true)
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

