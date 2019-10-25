<?php
namespace exface\ModxCmsConnector\CommonLogic\Installers;

use exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller;
use exface\ModxCmsConnector\CmsConnectors\Modx;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;

/**
 * Installs a CMS template, that is available as a file.
 * 
 * 
 *        
 * @author Andrej Kabachnik
 *        
 */
class ModxCmsTemplateInstaller extends AbstractAppInstaller
{
    
    private $templateName = null;
    
    private $templateDescription = '';
    
    private $templateVersion = '1.0';
    
    private $templateCategory = 'ExFace';
    
    private $templateLicense = 'http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)';
    
    private $templateFilePath = null;
    
    private $facadeAlias = null;
    
    private $modxConnector = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path) : \Iterator
    {
        $idt = $this->getOutputIndentation();
        $modx = $this->getModx();
        $db = $modx->db;
        $tplName = $this->getTemplateName();
        $tplTableName = $modx->getFullTableName('site_templates');
        
        yield $idt . 'CMS template "' . $tplName . '"... ';
        
        $checkExists = $modx->db->query("SELECT id FROM $tplTableName WHERE content LIKE ('% &file=`{$this->getTemplateFilePath()}`%')");
        if ($db->getRecordCount($checkExists) > 0) {
            yield 'already exists.' . PHP_EOL;
            return;
        }
        
        $resTpl = $db->query("INSERT INTO $tplTableName (`templatename`, `description`, `content`) VALUES ('{$db->escape($tplName)}', '{$db->escape($this->getTemplateDescription())}', '{$db->escape($this->getTemplateBody())}')");
        if (! $resTpl) {
            throw new InstallerRuntimeError($this, 'Could not install CMS template "' . $tplName . '": ' . $db->getLastError() . '!');
        } else {
            $tplId = $db->getInsertId();
            $tplVarNames = [
                Modx::TV_APP_UID_NAME,
                Modx::TV_DEFAULT_MENU_POSITION_NAME,
                Modx::TV_DO_UPDATE_NAME,
                Modx::TV_REPLACE_ALIAS_NAME,
                Modx::TV_UID_NAME
            ];
            $tplVarNamesIn = "'" . implode("','", $tplVarNames) . "'";
            $resTplVars = $db->query("INSERT INTO {$modx->getFullTableName('site_tmplvar_templates')} (`tmplvarid`, `templateid`) SELECT `id`, $tplId FROM {$modx->getFullTableName('site_tmplvars')} WHERE name IN ($tplVarNamesIn)");
            if (! $resTplVars) {
                throw new InstallerRuntimeError($this, 'Could not add template variables to "' . $tplName . '": ' . $db->getLastError() . '!');
            }
            
            yield 'done.' . PHP_EOL;
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall() : \Iterator
    {
        return 'Uninstall not implemented for installer "' . $this->getSelectorInstalling()->getAliasWithNamespace() . '"!';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup(string $destination_absolute_path) : \Iterator
    {
        return 'Backup not implemented for' . $this->getSelectorInstalling()->getAliasWithNamespace() . '!';
    }
    
    /**
     *
     * @return string
     */
    public function getTemplateName() : string
    {
        return $this->templateName;
    }
    
    /**
     * 
     * @param string $value
     * @return ModxCmsTemplateInstaller
     */
    public function setTemplateName(string $value) : ModxCmsTemplateInstaller
    {
        $this->templateName = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    protected function getTemplateDescription() : string
    {
        return $this->templateDescription;
    }
    
    /**
     *
     * @param string $value
     * @return ModxCmsTemplateInstaller
     */
    public function setTemplateDescription(string $value) : ModxCmsTemplateInstaller
    {
        $this->templateDescription = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    protected function getTemplateLicense() : string
    {
        return $this->templateLicense;
    }
    
    /**
     *
     * @param string $value
     * @return ModxCmsTemplateInstaller
     */
    public function setTemplateLicense(string $value) : ModxCmsTemplateInstaller
    {
        $this->templateLicense = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    protected function getTemplateVersion() : string
    {
        return $this->templateVersion;
    }
    
    /**
     *
     * @param string $value
     * @return ModxCmsTemplateInstaller
     */
    public function setTemplateVersion(string $value) : ModxCmsTemplateInstaller
    {
        $this->templateVersion = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    protected function getTemplateCategory() : string
    {
        return $this->templateCategory;
    }
    
    /**
     *
     * @param string $value
     * @return ModxCmsTemplateInstaller
     */
    public function setTemplateCategory(string $value) : ModxCmsTemplateInstaller
    {
        $this->templateCategory = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    protected function getFacadeAlias() : string
    {
        return $this->facadeAlias;
    }
    
    /**
     * 
     * @param string $value
     * @return ModxCmsTemplateInstaller
     */
    public function setFacadeAlias(string $value) : ModxCmsTemplateInstaller
    {
        $this->facadeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    protected function getTemplateFilePath() : string
    {
        return $this->templateFilePath;
    }
    
    /**
     * Path to the template file relative to the installation folder (e.g. vendor/exface/ModxCmsConnector/...)
     * 
     * @param string $value
     * @return ModxCmsTemplateInstaller
     */
    public function setTemplateFilePath(string $value) : ModxCmsTemplateInstaller
    {
        $this->templateFilePath = $value;
        return $this;
    }
        
    protected function getTemplateBody() : string
    {
        return <<<PHP
{$this->getTemplateSnippetCall()}

PHP;
    }
        
    protected function getTemplateSnippetCall() : string
    {
        return "[[ExFace? &action=`exface.ModxCmsConnector.ShowTemplate` &facade=`{$this->getFacadeAlias()}` &file=`{$this->getTemplateFilePath()}`]]";
    }
        
    /**
     * 
     * @throws InstallerRuntimeError
     * @return \DocumentParser
     */
    protected function getModx() : \DocumentParser
    {
        if ($this->modxConnector === null) {
            $cms = $this->getWorkbench()->getCMS();
            if (! $cms instanceof Modx) {
                throw new InstallerRuntimeError($this, 'Cannot use MdoxCmsTemplateInstaller with other CMS connectors than MODx / Evolution CMS!');
            }
            $this->modxConnector = $cms;
        }
        return $this->modxConnector->getModx();
    }
}