<?php

class bdImportCmd_ControllerResponse_PossibleSteps extends XenForo_ControllerResponse_View
{

    public $possibleSteps = array();

    public function dispatch(bdImportCmd_XenForo_FrontController $fc)
    {
        if (IMPORT_CMD_FORK > 0) {
            bdImportCmd_Helper_Terminal::error('Step finished, run with `mergeFork=%d` to continue.', IMPORT_CMD_FORK);
        }

        if (empty($this->possibleSteps)) {
            bdImportCmd_Helper_Terminal::error('No possible steps to continue.');
        }
        $step = reset($this->possibleSteps);
        bdImportCmd_Helper_Terminal::log('Auto-dispatch step %s...', $step);

        $oldRequest = $fc->getRequest();

        $fc->setRequest(new bdImportCmd_ControllerResponse_PossibleSteps_ControllerRequestHttpPost());
        $fc->getRequest()->setParam('step', $step);
        $response = $fc->dispatch(new XenForo_RouteMatch('XenForo_ControllerAdmin_Import', 'start-step'));

        $fc->setRequest($oldRequest);

        return $response;
    }
}

class bdImportCmd_ControllerResponse_PossibleSteps_ControllerRequestHttpPost extends Zend_Controller_Request_Http
{
    public function getMethod()
    {
        return 'POST';
    }
}
