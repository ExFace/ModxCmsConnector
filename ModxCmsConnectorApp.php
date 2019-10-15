<?php
namespace exface\ModxCmsConnector;

use exface\Core\Interfaces\InstallerInterface;
use exface\Core\CommonLogic\AppInstallers\SqlSchemaInstaller;
use exface\Core\CommonLogic\Model\App;
use exface\Core\Facades\AbstractPWAFacade\ServiceWorkerBuilder;
use exface\Core\Facades\AbstractPWAFacade\ServiceWorkerInstaller;
use exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller;
use exface\ModxCmsConnector\CommonLogic\Installers\ModxCmsConnectorInstaller;

class ModxCmsConnectorApp extends App
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
     * @see App::getInstaller($injected_installer)
     */
    public function getInstaller(InstallerInterface $injected_installer = null)
    {
        // Add the custom MODx installer
        $installerContainer = parent::getInstaller($injected_installer);
        $installerContainer->addInstaller(new ModxCmsConnectorInstaller($this->getSelector()));
        
        // Add the SQL schema installer for DB fixes
        // FIXME how to get to the MODx data connection without knowing, that is used for the model loader. The model loader could
        // theoretically use another connection?
        
        // Init the SQL installer
        $dbInstaller = new MySqlDatabaseInstaller($this->getSelector());
        $dbInstaller
            ->setFoldersWithMigrations(['Migrations'])
            ->setFoldersWithStaticSql(['CmsSettings'])
            ->setMigrationsTableName($dbInstaller->getMigrationsTableName() . '_evocms')
            ->setDataConnection($this->getWorkbench()->model()->getModelLoader()->getDataConnection());
        
        // Also add the old SqlSchemInstaller, so that in can update existing installations
        // upto it's last update script. After this, this installer will not do anything.
        // DON'T USE this one in future, only the first one!
        $legacySchemaInstaller = new SqlSchemaInstaller($this->getSelector());
        $legacySchemaInstaller
            ->setLastUpdateIdConfigOption('LAST_PERFORMED_MODEL_SOURCE_UPDATE_ID')
            ->setDataConnection($this->getWorkbench()->model()->getModelLoader()->getDataConnection())
            ->setSqlFolderName($dbInstaller->getSqlFolderName())
            ->setSqlUpdatesFolderName('LegacyInstallerUpdates');
        
        // First add the legacy installer to make sure, it's actions are performed first!
        $installerContainer->addInstaller($legacySchemaInstaller);
        $installerContainer->addInstaller($dbInstaller);
        
        
        // Add an installer for the service worker routing
        $serviceWorkerBuilder = new ServiceWorkerBuilder();
        foreach ($this->getConfig()->getOption('INSTALLER.SERVICEWORKER.ROUTES') as $id => $uxon) {
            $serviceWorkerBuilder->addRouteToCache(
                $id,
                $uxon->getProperty('matcher'),
                $uxon->getProperty('strategy'),
                $uxon->getProperty('method'),
                $uxon->getProperty('description'),
                $uxon->getProperty('cacheName'),
                $uxon->getProperty('maxEntries'),
                $uxon->getProperty('maxAgeSeconds')
            );
        }
        $serviceWorkerInstaller = new ServiceWorkerInstaller($this->getSelector(), $serviceWorkerBuilder);
        $installerContainer->addInstaller($serviceWorkerInstaller);
        
        return $installerContainer;
    }

    public function getModx()
    {
        global $modx;
        return $modx;
    }
}
?>