<?php namespace exface\ModxCmsConnector;
use exface\Core\Interfaces\InstallerInterface;

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
	
	/**
	 * The MODx CMS connector app includes a custom installer, that will take care of adding the index-exface.php to MODx, registering
	 * snippets and plugins, etc.
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractApp::get_installer($injected_installer)
	 */
	public function get_installer(InstallerInterface $injected_installer = null){
		$installer = parent::get_installer($injected_installer);
		$installer->add_installer(new ModxCmsConnectorInstaller($this->get_name_resolver()));
		return $installer;
	}
}
?>