<?php

class bdImportCmd_XenForo_Model_Import extends XFCP_bdImportCmd_XenForo_Model_Import
{
    protected $_importers = array();

    public function getImporter($key)
    {
        if (isset($this->_importers[$key])) {
            return $this->_importers[$key];
        }

        $importer = parent::getImporter($key);

        $this->_importers[$key] = $importer;

        return $importer;
    }

}