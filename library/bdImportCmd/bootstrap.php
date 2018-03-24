<?php

$fileDir = getcwd();
$autoloaderPath = $fileDir . '/library/XenForo/Autoloader.php';
if (!file_exists($autoloaderPath)) {
    die("The current directory must be XenForo root.\n");
}
/** @noinspection PhpIncludeInspection */
require($autoloaderPath);
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');
XenForo_Application::initialize($fileDir . '/library', $fileDir);
$dependencies = new XenForo_Dependencies_Public();
$dependencies->preLoadData();
