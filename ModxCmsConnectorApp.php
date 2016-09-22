<?php namespace exface\ModxCmsConnector;
class ModxCmsConnectorApp extends \exface\Core\CommonLogic\AbstractApp {
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractApp::install()
	 */
	public function install(){
		$result = '';
		// Copy index-exface.php to the root of the MODx installation
		try {
			$this->get_workbench()->filemanager()->copy(
				$this->get_directory_absolute_path() . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'index-exface.php', 
				$this->get_workbench()->get_installation_path() . DIRECTORY_SEPARATOR . $this->get_config()->get_option('PATH_TO_MODX'), 
				true
			);
			$result .= 'Updated index-exface.php in MODx';
		} catch (\Exception $e){
			throw $e;
		}
		return $result;
	}
}
?>