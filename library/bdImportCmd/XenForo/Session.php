<?php

$rootDir = getcwd();

$originalContents = file_get_contents($rootDir . '/library/XenForo/Session.php');
$contents = substr($originalContents, strpos($originalContents, '<?php') + 5);
$contents = str_replace(
    'class XenForo_Session',
    'class _XenForo_Session',
    $contents
);
eval($contents);

abstract class bdImportCmd_XenForo_Session extends _XenForo_Session
{
    protected function _setup($sessionId = '', $ipAddress = false, array $defaultSession = null)
    {
        $session = $this->_db->fetchRow('
            SELECT *
            FROM xf_session_admin
            ORDER BY expiry_date DESC
        ');

        if (empty($session)) {
            bdImportCmd_Helper_Terminal::error('Please login to AdminCP with your account.');
        }

        $sessionId = $session['session_id'];

        parent::_setup($sessionId, $ipAddress, $defaultSession);
    }

    public function sessionMatchesIp(array $session, $ipAddress)
    {
        return true;
    }

    public function deleteSessionFromSource($sessionId)
    {
        return;
    }

    public function saveSessionToSource($sessionId, $isUpdate)
    {
        return;
    }

}

eval('class XenForo_Session extends bdImportCmd_XenForo_Session {}');