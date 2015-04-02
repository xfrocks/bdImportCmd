<?php

class bdImportCmd_XenForo_ControllerAdmin_Import extends XFCP_bdImportCmd_XenForo_ControllerAdmin_Import
{
    public function actionImport()
    {
        $response = parent::actionImport();

        if (defined('IMPORT_CMD')
        && $response instanceof XenForo_ControllerResponse_View
            && $response->templateName === 'import_steps'
        ) {
            $steps = $response->params['steps'];
            $possibleSteps = array();

            foreach ($steps as $step => $stepInfo) {
                if (!empty($stepInfo['runnable'])) {
                    $possibleSteps[] = $step;
                }
            }

            $response = new bdImportCmd_ControllerResponse_PossibleSteps();
            $response->possibleSteps = $possibleSteps;
        }

        return $response;
    }

}