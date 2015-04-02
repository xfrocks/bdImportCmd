<?php

$rootDir = getcwd();

$originalContents = file_get_contents($rootDir . '/library/XenForo/FrontController.php');
$contents = substr($originalContents, strpos($originalContents, '<?php') + 5);
$contents = str_replace(
    'class XenForo_FrontController',
    'class _XenForo_FrontController',
    $contents
);
eval($contents);

abstract class bdImportCmd_XenForo_FrontController extends _XenForo_FrontController
{
    public function run()
    {
        return;
    }

    public function getViewRenderer()
    {
        return $this->_getViewRenderer(__CLASS__);
    }
}

eval('class XenForo_FrontController extends bdImportCmd_XenForo_FrontController {}');