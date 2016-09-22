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
				$this->get_modx_ajax_index_path(), 
				true
			);
			$result .= 'Updated index-exface.php in MODx';
		} catch (\Exception $e){
			throw $e;
		}
		return $result;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractApp::uninstall()
	 */
	public function uninstall(){
		// Remove index-exface.php to the root of the MODx installation
		$this->get_workbench()->filemanager()->remove($this->get_modx_ajax_index_path());
	}
	
	public function get_modx_ajax_index_path(){
		return $this->get_workbench()->get_installation_path() . DIRECTORY_SEPARATOR . $this->get_config()->get_option('PATH_TO_MODX');
	}
}
?>