<?php
namespace exface\ModxCmsConnector;

use exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller;

/**
 *
 * @method ModxCmsConnectorApp getApp()
 *        
 * @author Andrej Kabachnik
 *        
 */
class ModxCmsConnectorInstaller extends AbstractAppInstaller
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install($source_absolute_path)
    {
        $result = "\n";
        // Copy index-exface.php to the root of the MODx installation
        try {
            $this->getWorkbench()->filemanager()->copy($this->getApp()->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'Install' . DIRECTORY_SEPARATOR . 'index-exface.php', $this->getApp()->getModxAjaxIndexPath(), true);
            $result .= 'Updated index-exface.php in MODx';
        } catch (\Exception $e) {
            $result .= 'Failed to update index-exface.php in MODx' . $e->getMessage();
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::update()
     */
    public function update($source_absolute_path)
    {
        return $this->install();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall()
    {
        // Remove index-exface.php to the root of the MODx installation
        $this->getWorkbench()->filemanager()->remove($this->getApp()->getModxAjaxIndexPath());
        return "\nRemoved " . $this->getApp()->getModxAjaxIndexPath() . '.';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup($destination_absolute_path)
    {
        return 'Backup not implemented for installer "' . $this->getNameResolver()->getAliasWithNamespace() . '"!';
    }
}
?>