<?php
namespace exface\ModxCmsConnector\CmsConnectors;

use exface\Core\Interfaces\CmsConnectorInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\Workbench;
use exface\ModxCmsConnector\ModxCmsConnectorApp;
use exface\Core\Factories\UiPageFactory;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\UiPageNotFoundError;

class Modx implements CmsConnectorInterface
{

    const USER_TYPE_MGR = 'mgr';

    const USER_TYPE_WEB = 'web';

    private $user_name = null;

    private $user_type = null;

    private $user_settings = null;

    private $user_locale = null;

    private $workbench = null;
    
    const TV_APP_ALIAS_NAME = 'ExfacePageAppAlias';
    
    const TV_APP_ALIAS_DEFAULT = null;
    
    const TV_REPLACE_ALIAS_NAME = 'ExfacePageReplaceAlias';
    
    const TV_REPLACE_ALIAS_DEFAULT = '';
    
    const TV_UID_NAME = 'ExfacePageUID';
    
    const TV_UID_DEFAULT = '';
    
    const TV_DO_UPDATE_NAME = 'ExfacePageDoUpdate';

    const TV_DO_UPDATE_DEFAULT = true;
    
    /**
     *
     * @deprecated use CmsConnectorFactory instead
     * @param Workbench $exface            
     */
    public function __construct(Workbench $exface)
    {
        $this->workbench = $exface;
        global $modx;
        
        if (! $modx) {
            require_once $this->getApp()->getModxAjaxIndexPath();
        }
        
        if ($mgr = $modx->getLoginUserName('mgr')) {
            $this->user_name = $mgr;
            $this->user_type = self::USER_TYPE_MGR;
        } else {
            $this->user_name = $modx->getLoginUserName('web');
            $this->user_type = self::USER_TYPE_WEB;
        }
        
        if ($this->user_name === false){
            $this->user_name = '';
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPageId()
     */
    public function getPageId()
    {
        global $modx;
        return $modx->documentIdentifier;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPageContents()
     */
    public function getPageContents($doc_id)
    {
        global $modx;
        
        $q = $modx->db->select('content', $modx->getFullTableName('site_content'), 'id = ' . intval($doc_id));
        $source = $modx->db->getValue($q);
        return $source;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::createLinkInternal()
     */
    public function createLinkInternal($doc_id, $url_params = '')
    {
        global $modx;
        return $modx->makeUrl($doc_id, null, $url_params, 'full');
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::createLinkToFile()
     */
    public function createLinkToFile($path_absolute)
    {
        $sitePath = Filemanager::pathNormalize($this->getPathToModx(), '/');
        $filePath = Filemanager::pathNormalize($path_absolute, '/');
        $path = str_replace($sitePath, '', $filePath);
        if (substr($path, 0, 1) == "/" || substr($path, 0, 1) == "\\") {
            $path = substr($path, 1);
        }
        return $this->getApp()->getModx()->getConfig('site_url') . $path;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::createLinkExternal()
     */
    public function createLinkExternal($url)
    {
        return $url;
    }

    /**
     * For MODx no request params must be stripped off here, since they all get handled in the snippet.
     * This way they are only removed on regular requests - not on AJAX.
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::removeSystemRequestParams()
     */
    public function removeSystemRequestParams(array $param_array)
    {
        return $param_array;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::get_paget_title()
     */
    public function getPageTitle($resource_id = null)
    {
        global $modx;
        if (is_null($resource_id) || $resource_id == $modx->documentIdentifier) {
            return $modx->documentObject['pagetitle'];
        } else {
            $doc = $modx->getDocument($resource_id, 'pagetitle');
            return $doc['pagetitle'];
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getUserName()
     */
    public function getUserName()
    {
        return $this->user_name;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::isUserLoggedIn()
     */
    public function isUserLoggedIn()
    {
        return $this->getUserName() === '' ? false : true;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::isUserAdmin()
     */
    public function isUserAdmin()
    {
        return $this->user_type == self::USER_TYPE_MGR ? true : false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getUserLocale()
     */
    public function getUserLocale()
    {
        if (is_null($this->user_locale)) {
            switch ($this->getUserSettings('manager_language')) {
                case 'bulagrian':
                    $loc = 'bg_BG';
                    break;
                case 'chinese':
                    $loc = 'zh_CN';
                    break;
                case 'german':
                    $loc = 'de_DE';
                    break;
                default:
                    $loc = 'en_US';
            }
            $this->user_locale = $loc;
        }
        
        return $this->user_locale;
    }

    protected function getUserSettings($setting_name = null)
    {
        if (is_null($this->user_settings)) {
            global $modx;
            // Create the settings array an populate it with defaults
            $this->user_settings = array(
                'manager_language' => $modx->config['manager_language']
            );
            // Overload with user specific values if a user is logged on
            if ($modx->getLoginUserID()) {
                $rs = $modx->db->select('setting_name, setting_value', $modx->getFullTableName('user_settings'), "user=" . $modx->getLoginUserID() . " AND setting_name IN ('" . implode("','", array_keys($this->user_settings)) . "')");
                while ($row = $modx->db->getRow($rs)) {
                    $this->user_settings[$row['setting_name']] = $row['setting_value'];
                }
            }
        }
        if (is_null($setting_name)) {
            return $this->user_settings;
        } else {
            return $this->user_settings[$setting_name];
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     *
     * @return ModxCmsConnectorApp
     */
    public function getApp()
    {
        return $this->getWorkbench()->getApp('exface.ModxCmsConnector');
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::sanitizeOutput()
     */
    public function sanitizeOutput($string)
    {
        return str_replace(array(
            '[[',
            '[!',
            '{{'
        ), array(
            '[ [',
            '[ !',
            '{ {'
        ), $string);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::sanitizeErrorOutput()
     */
    public function sanitizeErrorOutput($string)
    {
        return $this->sanitizeOutput($string);
    }

    public function isUiPage($content, $id = null)
    {
        $content = trim($content);
        if (substr($content, 0, 1) !== '{' || substr($content, - 1, 1) !== '}') {
            return false;
        }
        
        try {
            UiPageFactory::createFromString($this->getWorkbench()->ui(), (is_null($id) ? 0 : $id), $content);
        } catch (\Throwable $e) {
            return false;
        }
        
        return true;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::clearCmsCache()
     */
    public function clearCmsCache()
    {
        $this->getApp()->getModx()->clearCache();
        return $this;
    }
    
    /**
     * Returns the path to the MODx folder from www root (MODX_BASE_PATH)
     * 
     * @return string
     */
    protected function getPathToModx()
    {
        return $this->getApp()->getModx()->config['base_path'];
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getSiteUrl()
     */
    public function getSiteUrl()
    {
        return $this->getApp()->getModx()->config['site_url'];
    }
    
    /**
     * TODO #ui-page-installer
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getCmsPageId()
     */
    public function getCmsPageId(UiPageInterface $page, $ignore_replacements = false)
    {
    }

    /**
     * TODO #ui-page-installer
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::loadPageByAlias()
     */
    public function loadPageByAlias($alias_with_namespace, $ignore_replacements = false)
    {}

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::loadPage()
     */
    public function loadPage($page_id_or_alias, $ignore_replacements = false)
    {
        if (substr($page_id_or_alias, 0, 2) == '0x') {
            return $this->loadPageById($page_id_or_alias, $ignore_replacements);
        } elseif (! is_numeric($page_id_or_alias)) {
            return $this->loadPageByAlias($page_id_or_alias, $ignore_replacements);
        } else {
            return $this->loadPageByCmsId($page_id_or_alias, $ignore_replacements);
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::loadPageById()
     */
    public function loadPageById($uid, $ignore_replacements = false)
    {
        global $modx;
        
        // TODO check ob Seite ersetzt werden muss
        
        if ($modx->documentObject[$this::TV_UID_NAME] && $uid == $modx->documentObject[$this::TV_UID_NAME][1]) {
            $uiPage = UiPageFactory::create($this->getWorkbench()->ui(), $uid);
            $uiPage->setIdCms($modx->documentObject['id']);
            $uiPage->setName($modx->documentObject['pagetitle']);
            $uiPage->setShortDescription($modx->documentObject['description']);
            $uiPage->setAlias($modx->documentObject['alias']);
            //$uiPage->setTemplate($modx->documentObject['template']);
            $uiPage->setMenuIndex($modx->documentObject['menuindex']);
            $uiPage->setMenuParentIdCms($modx->documentObject['parent']);
            //$uiPage->setMenuParentPage(???);
            $uiPage->setContents($modx->documentObject['content']);
            $uiPage->setAppAlias($modx->documentObject[$this::TV_APP_ALIAS_NAME] ? $modx->documentObject[$this::TV_APP_ALIAS_NAME][1]: $this::TV_APP_ALIAS_DEFAULT);
            // TODO isset(Updateable)
            $uiPage->setUpdateable($modx->documentObject[$this::TV_DO_UPDATE_NAME] ? $modx->documentObject[$this::TV_DO_UPDATE_NAME][1] : $this::TV_DO_UPDATE_DEFAULT);
            $uiPage->setReplacesPageAlias($modx->documentObject[$this::TV_REPLACE_ALIAS_NAME] ? $modx->documentObject[$this::TV_REPLACE_ALIAS_NAME][1] : $this::TV_REPLACE_ALIAS_DEFAULT);
            
        } else {
            $siteContent = $modx->getFullTableName('site_content');
            $siteTmplvars = $modx->getFullTableName('site_tmplvars');
            $siteTmplvarContentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
            
            $result = $modx->db->select('msc.id as id, msc.pagetitle as name, msc.description as shortDescription, msc.alias as alias, msc.template as template, msc.menuindex as menuIndex, msc.parent as menuParentIdCms, msc.content as contents', $siteContent . ' msc left join ' . $siteTmplvarContentvalues . ' mstc on msc.id = mstc.contentid left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id', 'mst.name = "' . $this::TV_UID_NAME . '" and mstc.value = "' . $uid . '"');
            if ($row = $modx->db->getRow($result)) {
                $uiPage = UiPageFactory::create($this->getWorkbench()->ui(), $uid);
                $uiPage->setIdCms($row['id']);
                $uiPage->setName($row['name']);
                $uiPage->setShortDescription($row['shortDescription']);
                $uiPage->setAlias($row['alias']);
                //$uiPage->setTemplate($row['template']);
                $uiPage->setMenuIndex($row['menuIndex']);
                $uiPage->setMenuParentIdCms($row['menuParentIdCms']);
                //$uiPage->setMenuParentPage(???);
                $uiPage->setContents($row['contents']);
                
                $result = $modx->db->select('mst.name as name, mstc.value as value', $siteTmplvarContentvalues . ' mstc left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id', 'mstc.contentid = (select mstc.contentid from ' . $siteTmplvarContentvalues . ' mstc left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id where mst.name = "' . $this::TV_UID_NAME . '" and mstc.value = "' . $uid . '")');
                $tmplVars = [];
                while ($row = $modx->db->getRow($result)) {
                    $tmplVars[$row['name']] = $row['value'];
                }
                
                $uiPage->setAppAlias($tmplVars[$this::TV_APP_ALIAS_NAME] ? $tmplVars[$this::TV_APP_ALIAS_NAME] : $this::TV_APP_ALIAS_DEFAULT);
                // TODO isset(Updateable)
                $uiPage->setUpdateable($tmplVars[$this::TV_DO_UPDATE_NAME] ? $tmplVars[$this::TV_DO_UPDATE_NAME] : $this::TV_DO_UPDATE_DEFAULT);
                $uiPage->setReplacesPageAlias($tmplVars[$this::TV_REPLACE_ALIAS_NAME] ? $tmplVars[$this::TV_REPLACE_ALIAS_NAME] : $this::TV_REPLACE_ALIAS_DEFAULT);
                
            } else {
                throw new UiPageNotFoundError('No page with UID "' . $uid . '" defined.');
            }
        }
        
        return $uiPage;
    }

    /**
     * TODO #ui-page-installer
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::savePage()
     */
    public function savePage(UiPageInterface $page)
    {}
    
    /**
     * TODO #ui-page-installer
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::loadPageByCmsId()
     */
    public function loadPageByCmsId($cms_page_id, $ignore_replacements = false)
    {}
    
    /**
     * TODO #ui-page-installer
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::createPage()
     */
    public function createPage(UiPageInterface $page)
    {}
    
    /**
     * TODO #ui-page-installer
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::updatePage()
     */
    public function updatePage(UiPageInterface $page)
    {}
    
    /**
     * TODO #ui-page-installer
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::deletePage()
     */
    public function deletePage(UiPageInterface $page)
    {}
    
    /**
     * TODO #ui-page-installer
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPagesByApp()
     */
    public function getPagesForApp(AppInterface $app)
    {
        // TODO #ui-page-installer
    }

}
?>