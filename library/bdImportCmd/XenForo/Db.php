<?php

$rootDir = getcwd();

$originalContents = file_get_contents($rootDir . '/library/XenForo/Db.php');
$contents = substr($originalContents, strpos($originalContents, '<?php') + 5);
$contents = str_replace(
    'class XenForo_Db',
    'class _XenForo_Db',
    $contents
);
eval($contents);

abstract class bdImportCmd_XenForo_Db extends _XenForo_Db
{

    public static function beginTransaction(Zend_Db_Adapter_Abstract $db = null)
    {
        // do nothing
    }

    public static function commit(Zend_Db_Adapter_Abstract $db = null)
    {
        // do nothing
    }

    public static function rollback(Zend_Db_Adapter_Abstract $db = null)
    {
        // do nothing
    }
}

eval('class XenForo_Db extends bdImportCmd_XenForo_Db {}');
