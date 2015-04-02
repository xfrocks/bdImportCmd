<?php

class bdImportCmd_Listener
{
    public static function load_class($class, array &$extend)
    {
        static $classes = array(
            'XenForo_ControllerAdmin_Import',
            'XenForo_Model_Import',
        );

        if (in_array($class, $classes, true)) {
            $extend[] = 'bdImportCmd_' . $class;
        }
    }
}