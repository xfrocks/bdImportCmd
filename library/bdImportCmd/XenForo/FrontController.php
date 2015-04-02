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
    protected $_controllers = array();

    public function run()
    {
        return;
    }

    public function getViewRenderer()
    {
        return $this->_getViewRenderer(__CLASS__);
    }

    protected function _getValidatedController($controllerName, $action, XenForo_RouteMatch $routeMatch)
    {
        $hash = md5($controllerName . spl_object_hash($this->getRequest()) . spl_object_hash($routeMatch));

        if (isset($this->_controllers[$hash])) {
            return $this->_controllers[$hash];
        }

        $controller = parent::_getValidatedController($controllerName, $action, $routeMatch);

        $this->_controllers[$hash] = $controller;

        return $controller;
    }


}

eval('class XenForo_FrontController extends bdImportCmd_XenForo_FrontController {}');