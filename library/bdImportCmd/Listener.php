<?php

class bdImportCmd_Listener
{
    public static function front_controller_pre_view(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_FrontController $fc,
        XenForo_ControllerResponse_Abstract &$controllerResponse,
        XenForo_ViewRenderer_Abstract &$viewRenderer,
        array &$containerParams)
    {
        // disable deferred task for json responses
        XenForo_Application::$autoDeferredIds = array();
    }

    public static function container_params(
        /** @noinspection PhpUnusedParameterInspection */
        array &$params,
        XenForo_Dependencies_Abstract $dependencies)
    {
        // disable deferred task for pages (mostly cron)
        $params['hasAutoDeferred'] = false;
    }

    public static function load_class_cmd($class, array &$extend)
    {
        static $classes = array(
            'XenForo_ControllerAdmin_Import',
            'XenForo_Model_DataRegistry',
            'XenForo_Model_Import',
        );

        if (in_array($class, $classes, true)) {
            $extend[] = 'bdImportCmd_' . $class;
        }
    }

    public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $hashes += bdImportCmd_FileSums::getHashes();
    }
}