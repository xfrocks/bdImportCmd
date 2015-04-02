<?php

$rootDir = getcwd();

require($rootDir . '/library/XenForo/Dependencies/Abstract.php');

$originalContents = file_get_contents($rootDir . '/library/XenForo/Dependencies/Admin.php');
$contents = substr($originalContents, strpos($originalContents, '<?php') + 5);
$contents = str_replace(
    'class XenForo_Dependencies_Admin',
    'class _XenForo_Dependencies_Admin',
    $contents
);
eval($contents);

abstract class bdImportCmd_XenForo_Dependencies_Admin extends _XenForo_Dependencies_Admin
{
    public function preLoadData()
    {
        parent::preLoadData();

        $addOns = XenForo_Application::get('addOns');
        if (empty($addOns['bdImportCmd'])) {
            /** @var XenForo_Model_AddOn $addOnModel */
            $addOnModel = XenForo_Model::create('XenForo_Model_AddOn');
            $addOnModel->installAddOnXmlFromFile(dirname(dirname(dirname(__FILE__))) . '/addon-bdImportCmd.xml');
            bdImportCmd_Helper_Terminal::error('Auto-installed add-on.');
        }
    }

    public function getViewRenderer(Zend_Controller_Response_Http $response, $responseType, Zend_Controller_Request_Http $request)
    {
        return new bdImportCmd_ViewRenderer_Terminal($this, $response, $request);
    }

    public function route(Zend_Controller_Request_Http $request, $routePath = null)
    {
        return new XenForo_RouteMatch('XenForo_ControllerAdmin_Import', 'import');
    }


}

eval('class XenForo_Dependencies_Admin extends bdImportCmd_XenForo_Dependencies_Admin {}');