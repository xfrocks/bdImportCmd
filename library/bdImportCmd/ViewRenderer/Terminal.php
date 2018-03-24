<?php

class bdImportCmd_ViewRenderer_Terminal extends XenForo_ViewRenderer_Abstract
{
    public function renderError($errorText)
    {
        bdImportCmd_Helper_Terminal::error($errorText);

        return '';
    }

    public function renderMessage($message)
    {
        bdImportCmd_Helper_Terminal::warn($message);

        return '';
    }

    public function renderView($viewName, array $params = array(), $templateName = '', XenForo_ControllerResponse_View $subView = null)
    {
        if ($subView) {
            return $this->renderSubView($subView);
        }

        $viewOutput = $this->renderViewObject($viewName, 'Terminal', $params, $templateName);

        if ($viewOutput === null) {
            $viewOutput = $params;
        }

        if (is_string($viewOutput)) {
            bdImportCmd_Helper_Terminal::log($viewOutput);
        } elseif (is_array($viewOutput)) {
            $handled = false;

            switch ($templateName) {
                case 'import_step_run':
                    if (!empty($viewOutput['stepInfo']['title'])) {
                        if (!empty($viewOutput['message'])) {
                            bdImportCmd_Helper_Terminal::log(
                                '%s = %s',
                                $viewOutput['stepInfo']['title'],
                                $viewOutput['message']
                            );
                        } else {
                            bdImportCmd_Helper_Terminal::log(
                                '%s = N/A',
                                $viewOutput['stepInfo']['title']
                            );
                        }
                        $handled = true;
                    }
                    break;
            }

            if (!$handled) {
                bdImportCmd_Helper_Terminal::log('templateName = %s', $templateName);

                foreach ($viewOutput as $key => $value) {
                    if (is_object($value)) {
                        if ($value instanceof Exception) {
                            $string = $value->getMessage();
                        } else {
                            $string = strval($value);
                        }
                    } else {
                        $string = var_export($value, true);
                    }

                    bdImportCmd_Helper_Terminal::log('%s = %s', $key, $string);
                }

                bdImportCmd_Helper_Terminal::stop();
            }
        }

        return '';
    }

    public function renderContainer($contents, array $params = array())
    {
        return $contents;
    }

    public function renderUnrepresentable()
    {
        return $this->renderError('Unrepresentable in Terminal.');
    }
}
