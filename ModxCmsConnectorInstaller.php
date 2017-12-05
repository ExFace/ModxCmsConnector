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
        // Copy index-exface.php to the root of the MODx installation
        try {
            $this->getWorkbench()->filemanager()->copy($this->getApp()->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'Install' . DIRECTORY_SEPARATOR . 'index-exface.php', $this->getApp()->getModxAjaxIndexPath(), true);
            $result .= "\nUpdated index-exface.php in MODx";
        } catch (\Exception $e) {
            $result .= "\nFailed to update index-exface.php in MODx" . $e->getMessage();
        }
        
        try {
            $modx = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getModx();
        } catch (\Throwable $e) {
            $result .= "\nError getting MODx";
            return $result;
        }
        
        // Update important Modx settings.
        try {
            // System settings
            $systemSettings = $modx->getFullTableName('system_settings');
            $modx->db->update(['setting_value' => '1'], $systemSettings, 'setting_name = "friendly_urls"');
            $modx->db->update(['setting_value' => '0'], $systemSettings, 'setting_name = "allow_duplicate_alias"');
            $modx->db->update(['setting_value' => '1'], $systemSettings, 'setting_name = "automatic_alias"');
            $modx->db->update(['setting_value' => '1'], $systemSettings, 'setting_name = "udperms_allowroot"');
            $modx->db->update(['setting_value' => $this->getWorkbench()->getCMS()->getDefaultTemplateId()], $systemSettings, 'setting_name = "default_template"');
            
            // Plugins
            $sitePlugins = $modx->getFullTableName('site_plugins');
            $modx->db->update(['disabled' => 1], $sitePlugins, 'name = "TransAlias"');
            $modx->db->update(['disabled' => 0], $sitePlugins, 'name = "ExFace"');
            
            $result .= "\nUpdated MODx settings";
        } catch (\Throwable $e) {
            $result .= "\nError updating MODx settings;" . $e->getMessage();
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