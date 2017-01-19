<?php namespace exface\ModxCmsConnector;
class ModxCmsConnectorApp extends \exface\Core\CommonLogic\AbstractApp {
	
	/**
	 * Returns the absolute path to the index-exface.php file in the root folder of MODx. This file is used to initialize
	 * MODx for API requests, that are not handled by MODx by default.
	 * 
	 * @return string
	 */
	public function get_modx_ajax_index_path(){
		return $this->get_workbench()->get_installation_path() . DIRECTORY_SEPARATOR . $this->get_config()->get_option('PATH_TO_MODX');
	}
}
?>