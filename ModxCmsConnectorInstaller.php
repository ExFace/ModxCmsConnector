<?php
namespace exface\ModxCmsConnector;

use exface\Core\CommonLogic\AbstractApp;
use exface\Core\CommonLogic\AbstractAppInstaller;

/**
 * 
 * @method ModxCmsConnectorApp get_app()
 * 
 * @author Andrej Kabachnik
 *
 */
class ModxCmsConnectorInstaller extends AbstractAppInstaller {
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractApp::install()
	 */
	public function install($source_absolute_path){
		$result = "\n";
		// Copy index-exface.php to the root of the MODx installation
		try {
			$this->get_workbench()->filemanager()->copy(
					$this->get_app()->get_directory_absolute_path() . DIRECTORY_SEPARATOR . 'Install' . DIRECTORY_SEPARATOR . 'index-exface.php',
					$this->get_app()->get_modx_ajax_index_path(),
					true
					);
			$result .= 'Updated index-exface.php in MODx';
		} catch (\Exception $e){
			$result .= 'Failed to update index-exface.php in MODx' . $e->getMessage();
		}
		return $result;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\InstallerInterface::update()
	 */
	public function update($source_absolute_path){
		return $this->install();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
	 */
	public function uninstall(){
		// Remove index-exface.php to the root of the MODx installation
		$this->get_workbench()->filemanager()->remove($this->get_app()->get_modx_ajax_index_path());
		return "\nRemoved " . $this->get_app()->get_modx_ajax_index_path() . '.'; 
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\InstallerInterface::backup()
	 */
	public function backup($destination_absolute_path){
		return 'Backup not implemented for' . $this->get_name_resolver()->get_alias_with_namespace() . '!';
	}
}
?>