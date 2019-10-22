<?php
namespace exface\ModxCmsConnector\CommonLogic\Installers;

use exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\StringDataType;
use exface\ModxCmsConnector\ModxCmsConnectorApp;

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
    public function install(string $source_absolute_path) : \Iterator
    {
        // Copy index-exface.php to the root of the MODx installation
        $idt = $this->getOutputIndentation();
        try {
            $this->getWorkbench()->filemanager()->copy($this->getApp()->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'Install' . DIRECTORY_SEPARATOR . 'index-exface.php', $this->getApp()->getModxAjaxIndexPath(), true);
            yield $idt . "Updated index-exface.php in MODx" . PHP_EOL;
        } catch (\Exception $e) {
            yield $idt . "ERROR: Failed to update index-exface.php in MODx: " . $e->getMessage() . " in " . $e->getFile() . ' on line ' . $e->getLine() . PHP_EOL;
        }
        
        try {
            $modx = $this->getModx();
        } catch (\Throwable $e) {
            yield $idt . "ERROR getting MODx: " . $e->getMessage() . " in " . $e->getFile() . ' on line ' . $e->getLine() . PHP_EOL;
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
            
            yield $idt . "Updated MODx settings" . PHP_EOL;
        } catch (\Throwable $e) {
            yield $idt . "Error updating MODx settings: " . $e->getMessage() . " in " . $e->getFile() . ' on line ' . $e->getLine() . PHP_EOL;
        }
        
        // Sync users
        yield $idt . $this->importUsers($modx);
    }
    
    protected function getModx()
    {
        return $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getModx();
    }
    
    /**
     * Imports manager users from the CMS if there are no users registered in ExFace yet.
     * 
     * @param \DocumentParser $modx
     * @return string
     */
    protected function importUsers(\DocumentParser $modx) : string
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER');
        if ($ds->countRowsInDataSource() > 0) {
            return '';
        }
        
        $tblManagerUsers = $modx->getFullTableName('manager_users');
        $tblUserAttributes = $modx->getFullTableName('user_attributes');
        $tblUserSettings = $modx->getFullTableName('user_settings');
        $sql = <<<SQL
SELECT 
    ma.username, 
    ua.fullname, 
    ua.email,
    sl.setting_value AS manager_language
FROM {$tblManagerUsers} ma 
    INNER JOIN {$tblUserAttributes} ua ON ma.id = ua.internalKey
    LEFT JOIN {$tblUserSettings} sl ON ma.id = sl.user AND sl.setting_name = "manager_language"
SQL;
        $result = $modx->db->query($sql);
        while ($row = $modx->db->getRow($result)) {
            $langLocalMap = $this->getWorkbench()->getApp('exface.ModxCmsConnector')->getConfig()->getOption('USERS.LANGUAGE_LOCALE_MAPPING')->toArray();
            if (($lang = $row['manager_language']) && array_key_exists($lang, $langLocalMap)) {
                $locale = $langLocalMap[$lang];
            } else {
                $locale = $this->getWorkbench()->getCoreApp()->getConfig()->getOption('LOCALE.DEFAULT');
            }
            $ds->addRow([
                'USERNAME' => $row['username'],
                'EMAIL' => $row['email'],
                'FIRST_NAME' => StringDataType::substringBefore($row['fullname'], ' '),
                'LAST_NAME' => StringDataType::substringAfter($row['fullname'], ' '),
                'LOCALE' => $locale,
                'CREATED_BY_USER' => '0x00000000000000000000000000000000',
                'MODIFIED_BY_USER' => '0x00000000000000000000000000000000'
            ]);
        }
        
        if ($ds->countRows() > 0) {
            // IMPORTANT: disable fixed values for modified-by-column because otherwise it will
            // try to fetch the current user UID and cause errors since there are no users there 
            // right now - we are trying to create them!
            if ($user_col = $ds->getColumns()->getByExpression('MODIFIED_BY_USER')) {
                $user_col->setIgnoreFixedValues(true);
            }
            $ds->dataCreate();
        }
        
        return "\nImported {$ds->countRows()} manager users from CMS.";
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
    public function uninstall() : \Iterator
    {
        // Remove index-exface.php to the root of the MODx installation
        $this->getWorkbench()->filemanager()->remove($this->getApp()->getModxAjaxIndexPath());
        yield $this->getOutputIndentation() . "Removed " . $this->getApp()->getModxAjaxIndexPath() . '.' . PHP_EOL;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup(string $destination_absolute_path) : \Iterator
    {
        return 'Backup not implemented for installer "' . $this->getSelectorInstalling()->getAliasWithNamespace() . '"!';
    }
}
?>