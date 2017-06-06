<?php
namespace exface\ModxCmsConnector;

use exface\Core\Interfaces\InstallerInterface;
use exface\SqlDataConnector\SqlSchemaInstaller;

class ModxCmsConnectorApp extends \exface\Core\CommonLogic\AbstractApp
{

    /**
     * Returns the absolute path to the index-exface.php file in the root folder of MODx.
     * This file is used to initialize
     * MODx for API requests, that are not handled by MODx by default.
     *
     * @return string
     */
    public function getModxAjaxIndexPath()
    {
        return $this->getWorkbench()->getInstallationPath() . DIRECTORY_SEPARATOR . $this->getConfig()->getOption('PATH_TO_MODX');
    }

    /**
     * The MODx CMS connector app includes a custom installer, that will take care of adding the index-exface.php to MODx, registering
     * snippets and plugins, etc.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractApp::getInstaller($injected_installer)
     */
    public function getInstaller(InstallerInterface $injected_installer = null)
    {
        // Add the custom MODx installer
        $installer = parent::getInstaller($injected_installer);
        $installer->addInstaller(new ModxCmsConnectorInstaller($this->getNameResolver()));
        
        // Add the SQL schema installer for DB fixes
        $schema_installer = new SqlSchemaInstaller($this->getNameResolver());
        $schema_installer->setLastUpdateIdConfigOption('LAST_PERFORMED_MODEL_SOURCE_UPDATE_ID');
        // FIXME how to get to the MODx data connection without knowing, that is used for the model loader. The model loader could
        // theoretically use another connection?
        $schema_installer->setDataConnection($this->getWorkbench()->model()->getModelLoader()->getDataConnection());
        $installer->addInstaller($schema_installer);
        return $installer;
    }

    public function getModx()
    {
        global $modx;
        return $modx;
    }
}
?>