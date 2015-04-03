<?php

class bdImportCmd_XenForo_Model_DataRegistry extends XFCP_bdImportCmd_XenForo_Model_DataRegistry
{
	public function get($itemName)
	{
		if (defined('IMPORT_CMD_FORK')
			&& IMPORT_CMD_FORK > 0
			&& $itemName === 'importSession') {
			$itemName .= IMPORT_CMD_FORK;
		}

		$result = parent::get($itemName);
		if (!empty($result)) {
			return $result;
		}

		$result = parent::get('importSession');
		$result['stepStart'] = IMPORT_CMD_STEP_START;
		$result['stepOptions'] = array();
		return $result;
	}

	public function set($itemName, $value)
	{
		if (defined('IMPORT_CMD_FORK')
			&& IMPORT_CMD_FORK > 0
			&& $itemName === 'importSession') {
			$itemName .= IMPORT_CMD_FORK;
		}

		return parent::set($itemName, $value);
	}

	public function delete($itemName)
	{
		if (defined('IMPORT_CMD_FORK')
			&& IMPORT_CMD_FORK > 0
			&& $itemName === 'importSession') {
			$itemName .= IMPORT_CMD_FORK;
		}

		return parent::delete($itemName);
	}
}