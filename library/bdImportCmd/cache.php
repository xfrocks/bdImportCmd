<?php

define('CACHE_CMD', true);

/* start parsing command line options */
$getoptShort = '';
$getoptLong = array();
// action run a builder class
$getoptShort .= 'c::';
$getoptLong[] = 'class::';
// action run params
$getoptShort .= 'p::';
$getoptLong[] = 'params::';
// parse options
$opt = getopt($getoptShort, $getoptLong);
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

$clazz = '';
if (!empty($opt['c'])) {
    $clazz = $opt['c'];
} elseif (!empty($opt['class'])) {
    $clazz = $opt['class'];
}

$params = '';
if (!empty($opt['p'])) {
    $params = $opt['p'];
} elseif (!empty($opt['params'])) {
    $params = $opt['params'];
}
parse_str($params, $params);

if (!empty($clazz)) {
    /** @var XenForo_CacheRebuilder_Abstract $rebuilder */
    $rebuilder = null;
    try {
        $rebuilder = XenForo_CacheRebuilder_Abstract::getCacheRebuilder($clazz);
    } catch (XenForo_Exception $e) {
        if (class_exists($clazz)) {
            $rebuilder = new $clazz($clazz);
        }
    }
    if (empty($rebuilder)) {
        echo(sprintf("Rebuilder %s could not be found.\n", $clazz));
        die;
    }

    $GLOBALS['_terminate'] = false;
    if (function_exists('pcntl_signal')) {
        $signalFunc = create_function('', 'echo("\nTerminating...");$GLOBALS["_terminate"] = true;');
        pcntl_signal(SIGINT, $signalFunc);
        pcntl_signal(SIGTERM, $signalFunc);
    } else {
        echo("Signal support is not available, Ctrl+C to stop running may cause incomplete task data!\n");
        $signalFunc = '';
    }

    echo(sprintf("Start running rebuilder %s @ %s...\n", $clazz, gmdate('c')));
    $i = 0;
    $startTime = microtime(true);
    while (true) {
        if ($signalFunc !== '') {
            pcntl_signal_dispatch();
        }
        if ($GLOBALS['_terminate'] === true) {
            echo("Terminate signal received, bye bye...\n");
            die;
        }

        $position = 0;
        if (!empty($params['position'])) {
            $position = $params['position'];
        }
        $rebuilt = $rebuilder->rebuild($position, $params, $detailedMessage);

        if (is_int($rebuilt)) {
            // still doing this one
            $params['position'] = $rebuilt;
        } else {
            echo("Done\n\n");
            die;
        }

        if ($i % 50 === 0) {
            $mem = memory_get_usage();
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

            echo(sprintf("\n%s / mem=%s / time=%s\n", $detailedMessage, $mem, $time));
        } else {
            echo('.');
        }

        $i++;
    }
}

echo("--class to run specified rebuilder\n");
echo("  --params: specify extra params to rebuild\n");
echo("\n");
