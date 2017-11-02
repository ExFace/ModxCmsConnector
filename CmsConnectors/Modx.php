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
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\UiPageIdNotUniqueError;
use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\CommonLogic\AbstractCmsConnector;
use exface\Core\Interfaces\Log\LoggerInterface;

class Modx extends AbstractCmsConnector
{

    const USER_TYPE_MGR = 'mgr';

    const USER_TYPE_WEB = 'web';

    private $user_name = null;

    private $user_type = null;

    private $user_settings = null;

    private $user_locale = null;

    private $workbench = null;

    const TV_APP_ALIAS_NAME = 'ExfacePageAppAlias';

    const TV_APP_ALIAS_DEFAULT = '';

    const TV_REPLACE_ALIAS_NAME = 'ExfacePageReplaceAlias';

    const TV_REPLACE_ALIAS_DEFAULT = '';

    const TV_UID_NAME = 'ExfacePageUID';

    const TV_UID_DEFAULT = '';

    const TV_DO_UPDATE_NAME = 'ExfacePageDoUpdate';

    const TV_DO_UPDATE_DEFAULT = true;

    const TV_DEFAULT_PARENT_ALIAS_NAME = 'ExfacePageDefaultParentAlias';

    const TV_DEFAULT_PARENT_ALIAS_DEFAULT = '';

    const MODX_ADD_ACTION = '4';

    const MODX_UPDATE_ACTION = '27';

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
        
        if ($this->user_name === false) {
            $this->user_name = '';
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPageCurrentId()
     */
    public function getPageCurrentId()
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::createLinkInternal()
     */
    public function createLinkInternal($page_or_id_or_alias, $url_params = '')
    {
        global $modx;
        $id_or_alias = $page_or_id_or_alias instanceof UiPageInterface ? $page_or_id_or_alias->getId() : $page_or_id_or_alias;
        return $modx->makeUrl($this->getPageCmsId($id_or_alias), null, $url_params, 'full');
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPageTitle()
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
                case 'bulgarian':
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

    public function isUiPage($content, $alias = null)
    {
        $content = trim($content);
        if (substr($content, 0, 1) !== '{' || substr($content, - 1, 1) !== '}') {
            return false;
        }
        
        try {
            UiPageFactory::createFromString($this->getWorkbench()->ui(), (is_null($alias) ? '' : $alias), $content);
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::loadPageByAlias()
     */
    public function loadPageByAlias($alias_with_namespace, $ignore_replacements = false, $replaced_aliases = [])
    {
        global $modx;
        
        $siteContent = $modx->getFullTableName('site_content');
        $siteTmplvars = $modx->getFullTableName('site_tmplvars');
        $siteTmplvarContentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
        
        if (! $ignore_replacements) {
            // Ueberpruefen ob die Seite durch eine andere ersetzt wird.
            $result = $modx->db->select('msc.alias as id', $siteContent . ' msc left join ' . $siteTmplvarContentvalues . ' mstc on msc.id = mstc.contentid left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id', 'mst.name = "' . $this::TV_REPLACE_ALIAS_NAME . '" and mstc.value = "' . $alias_with_namespace . '"');
            $uiPage = $this->getReplacePage($result, $alias_with_namespace, $ignore_replacements, $replaced_aliases);
            if (! is_null($uiPage)) {
                return $uiPage;
            }
        }
        
        if ($alias_with_namespace == $modx->documentObject['alias']) {
            // Es ist die momentan geladene Seite.
            $uiPage = $this->getPageFromModx();
        } else {
            // Die Seite muss aus der Datenbank geladen werden.
            $result = $modx->db->select('msc.id as id, msc.pagetitle as name, msc.description as shortDescription, msc.alias as alias, msc.template as template, msc.menuindex as menuIndex, msc.hidemenu as hideMenu, msc.parent as menuParentIdCms, msc.content as contents', $siteContent . ' msc', 'msc.alias = "' . $alias_with_namespace . '"');
            if ($modx->db->getRecordCount($result) == 0) {
                throw new UiPageNotFoundError('No page with alias "' . $alias_with_namespace . '" defined.');
            } elseif ($modx->db->getRecordCount($result) == 1) {
                $resultTmplVars = $modx->db->select('mst.name as name, mstc.value as value', $siteTmplvarContentvalues . ' mstc left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id', 'mstc.contentid = (select msc.id from ' . $siteContent . ' msc where msc.alias = "' . $alias_with_namespace . '")');
                $uiPage = $this->getPageFromDb($result, $resultTmplVars);
            } else {
                throw new UiPageIdNotUniqueError('More than one page with alias "' . $alias_with_namespace . '" defined.');
            }
        }
        
        return $uiPage;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::loadPageById()
     */
    public function loadPageById($uid, $ignore_replacements = false, $replaced_uids = [])
    {
        global $modx;
        
        $siteContent = $modx->getFullTableName('site_content');
        $siteTmplvars = $modx->getFullTableName('site_tmplvars');
        $siteTmplvarContentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
        
        if (! $ignore_replacements) {
            // Ueberpruefen ob die Seite durch eine andere ersetzt wird.
            $result = $modx->db->select('mstc.value as id', $siteTmplvarContentvalues . ' mstc left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id', 'mst.name = "' . $this::TV_UID_NAME . '" and mstc.contentid in (select msc.id as id from ' . $siteContent . ' msc left join ' . $siteTmplvarContentvalues . ' mstc on msc.id = mstc.contentid left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id where mst.name = "' . $this::TV_REPLACE_ALIAS_NAME . '" and mstc.value = (select msc.alias as alias from ' . $siteContent . ' msc left join ' . $siteTmplvarContentvalues . ' mstc on msc.id = mstc.contentid left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id where mst.name = "' . $this::TV_UID_NAME . '" and mstc.value = "' . $uid . '"))');
            $uiPage = $this->getReplacePage($result, $uid, $ignore_replacements, $replaced_uids);
            if (! is_null($uiPage)) {
                return $uiPage;
            }
        }
        
        if ($modx->documentObject[$this::TV_UID_NAME] && $uid == $modx->documentObject[$this::TV_UID_NAME][1]) {
            // Es ist die momentan geladene Seite.
            $uiPage = $this->getPageFromModx();
        } else {
            // Die Seite muss aus der Datenbank geladen werden.
            $result = $modx->db->select('msc.id as id, msc.pagetitle as name, msc.description as shortDescription, msc.alias as alias, msc.template as template, msc.menuindex as menuIndex, msc.hidemenu as hideMenu, msc.parent as menuParentIdCms, msc.content as contents', $siteContent . ' msc left join ' . $siteTmplvarContentvalues . ' mstc on msc.id = mstc.contentid left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id', 'mst.name = "' . $this::TV_UID_NAME . '" and mstc.value = "' . $uid . '"');
            if ($modx->db->getRecordCount($result) == 0) {
                throw new UiPageNotFoundError('No page with UID "' . $uid . '" defined.');
            } elseif ($modx->db->getRecordCount($result) == 1) {
                $resultTmplVars = $modx->db->select('mst.name as name, mstc.value as value', $siteTmplvarContentvalues . ' mstc left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id', 'mstc.contentid = (select mstc.contentid from ' . $siteTmplvarContentvalues . ' mstc left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id where mst.name = "' . $this::TV_UID_NAME . '" and mstc.value = "' . $uid . '")');
                $uiPage = $this->getPageFromDb($result, $resultTmplVars);
            } else {
                throw new UiPageIdNotUniqueError('More than one page with UID "' . $uid . '" defined.');
            }
        }
        
        return $uiPage;
    }

    /**
     * 
     * @param integer $page_id_cms
     * @param boolean $ignore_replacements
     * @param array $replaced_ids
     * 
     * @throws UiPageNotFoundError
     * @throws UiPageIdNotUniqueError
     * 
     * @return UiPageInterface
     */
    protected function loadPageByCmsId($page_id_cms, $ignore_replacements = false, $replaced_ids = [])
    {
        global $modx;
        
        $siteContent = $modx->getFullTableName('site_content');
        $siteTmplvars = $modx->getFullTableName('site_tmplvars');
        $siteTmplvarContentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
        
        if (! $ignore_replacements) {
            // Ueberpruefen ob die Seite durch eine andere ersetzt wird.
            $result = $modx->db->select('msc.id as id', $siteContent . ' msc left join ' . $siteTmplvarContentvalues . ' mstc on msc.id = mstc.contentid left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id', 'mst.name = "' . $this::TV_REPLACE_ALIAS_NAME . '" and mstc.value = (select msc.alias as alias from ' . $siteContent . ' msc where msc.id = ' . $page_id_cms . ')');
            $uiPage = $this->getReplacePage($result, $page_id_cms, $ignore_replacements, $replaced_ids);
            if (! is_null($uiPage)) {
                return $uiPage;
            }
        }
        
        if ($page_id_cms == $modx->documentObject['id']) {
            // Es ist die momentan geladene Seite.
            $uiPage = $this->getPageFromModx();
        } else {
            // Die Seite muss aus der Datenbank geladen werden.
            $result = $modx->db->select('msc.id as id, msc.pagetitle as name, msc.description as shortDescription, msc.alias as alias, msc.template as template, msc.menuindex as menuIndex, msc.hidemenu as hideMenu, msc.parent as menuParentIdCms, msc.content as contents', $siteContent . ' msc', 'msc.id = ' . $page_id_cms);
            if ($modx->db->getRecordCount($result) == 0) {
                throw new UiPageNotFoundError('No page with CMS-ID "' . $page_id_cms . '" defined.');
            } elseif ($modx->db->getRecordCount($result) == 1) {
                $resultTmplVars = $modx->db->select('mst.name as name, mstc.value as value', $siteTmplvarContentvalues . ' mstc left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id', 'mstc.contentid = ' . $page_id_cms);
                $uiPage = $this->getPageFromDb($result, $resultTmplVars);
            } else {
                throw new UiPageIdNotUniqueError('More than one page with CMS-ID "' . $page_id_cms . '" defined.');
            }
        }
        
        return $uiPage;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::loadPageCurrent()
     */
    public function loadPageCurrent()
    {
        global $modx;
        
        return $this->loadPageByAlias($modx->documentObject['alias']);
    }

    /**
     * Checks if a UiPage is replaced by another UiPage and returns the replacement if so.
     * 
     * @param resource $sql_result contains the page_id_or_alias as id of the replacement page
     * @param string $id the page_id_or_alias of the original page
     * @param boolean $ignore_replacements
     * @param string[] $replaced_ids contains the page_id_or_alias of the already replaced pages
     * @throws RuntimeException if serveral pages replace the same page, or pages are replaced
     * in a circular fashion
     * @return null|UiPageInterface
     */
    protected function getReplacePage($sql_result, $id, $ignore_replacements, $replaced_ids)
    {
        global $modx;
        
        if ($modx->db->getRecordCount($sql_result) == 0) {
            // Seite wird durch keine andere ersetzt und wird normal geladen.
            return null;
        } elseif ($modx->db->getRecordCount($sql_result) == 1) {
            // Seite wird von einer anderen Seite ersetzt.
            if ($row = $modx->db->getRow($sql_result)) {
                if (in_array($row['id'], $replaced_ids)) {
                    // Kreisfoermige Ersetzung von Seiten.
                    throw new RuntimeException('Several pages "' . implode(', ', array_merge($replaced_ids, [
                        $id
                    ])) . '" replace themselves in a circular fashion.');
                }
                if ($row['id'] == $id) {
                    // Seite ersetzt sich selbst und wird normal geladen.
                    return null;
                } else {
                    return $this->loadPage($row['id'], $ignore_replacements, array_merge($replaced_ids, [
                        $id
                    ]));
                }
            }
        } else {
            // Es gibt mehrere Seiten, welche die Seite ersetzen.
            $ids = [];
            while ($row = $modx->db->getRow($sql_result)) {
                $ids[] = $row['id'];
            }
            throw new RuntimeException('Several pages "' . implode(', ', $ids) . '" replace the same page "' . $id . '".');
        }
    }

    /**
     * Returns a UiPage from the currently loaded page in $modx.
     * 
     * @return UiPageInterface
     */
    protected function getPageFromModx()
    {
        global $modx;
        
        $pageAlias = $modx->documentObject['alias'];
        $pageUid = $modx->documentObject[$this::TV_UID_NAME] ? $modx->documentObject[$this::TV_UID_NAME][1] : $this::TV_UID_DEFAULT;
        $appAlias = $modx->documentObject[$this::TV_APP_ALIAS_NAME] ? $modx->documentObject[$this::TV_APP_ALIAS_NAME][1] : $this::TV_APP_ALIAS_DEFAULT;
        $uiPage = UiPageFactory::create($this->getWorkbench()->ui(), $pageAlias, $pageUid, $appAlias);
        
        $uiPage->setName($modx->documentObject['pagetitle']);
        $uiPage->setShortDescription($modx->documentObject['description']);
        $uiPage->setMenuIndex($modx->documentObject['menuindex']);
        $uiPage->setMenuVisible(! $modx->documentObject['hidemenu']);
        $uiPage->setMenuParentPageAlias($this->getPageAlias($modx->documentObject['parent']));
        $uiPage->setUpdateable(array_key_exists($this::TV_DO_UPDATE_NAME, $modx->documentObject) ? $modx->documentObject[$this::TV_DO_UPDATE_NAME][1] : $this::TV_DO_UPDATE_DEFAULT);
        $uiPage->setReplacesPageAlias($modx->documentObject[$this::TV_REPLACE_ALIAS_NAME] ? $modx->documentObject[$this::TV_REPLACE_ALIAS_NAME][1] : $this::TV_REPLACE_ALIAS_DEFAULT);
        $uiPage->setMenuParentPageDefaultAlias($modx->documentObject[$this::TV_DEFAULT_PARENT_ALIAS_NAME] ? $modx->documentObject[$this::TV_DEFAULT_PARENT_ALIAS_NAME] : $this::TV_DEFAULT_PARENT_ALIAS_DEFAULT);
        $uiPage->setContents($modx->documentObject['content']);
        
        return $uiPage;
    }

    /**
     * Returns a UiPage from the database.
     * 
     * @param resource $result contains the modx_site_contents of the page
     * @param resource $resultTmplVars contains the modx_site_tmplvar_contentvalues of the page
     * @return UiPageInterface
     */
    protected function getPageFromDb($result, $resultTmplVars)
    {
        global $modx;
        
        $row = $modx->db->getRow($result);
        $tmplVars = [];
        while ($tvRow = $modx->db->getRow($resultTmplVars)) {
            $tmplVars[$tvRow['name']] = $tvRow['value'];
        }
        
        $pageAlias = $row['alias'];
        $pageUid = $tmplVars[$this::TV_UID_NAME] ? $tmplVars[$this::TV_UID_NAME] : $this::TV_UID_DEFAULT;
        $appAlias = $tmplVars[$this::TV_APP_ALIAS_NAME] ? $tmplVars[$this::TV_APP_ALIAS_NAME] : $this::TV_APP_ALIAS_DEFAULT;
        $uiPage = UiPageFactory::create($this->getWorkbench()->ui(), $pageAlias, $pageUid, $appAlias);
        
        $uiPage->setName($row['name']);
        $uiPage->setShortDescription($row['shortDescription']);
        $uiPage->setMenuIndex($row['menuIndex']);
        $uiPage->setMenuVisible(! $row['hideMenu']);
        $uiPage->setMenuParentPageAlias($this->getPageAlias($row['menuParentIdCms']));
        $uiPage->setUpdateable(array_key_exists($this::TV_DO_UPDATE_NAME, $tmplVars) ? $tmplVars[$this::TV_DO_UPDATE_NAME] : $this::TV_DO_UPDATE_DEFAULT);
        $uiPage->setReplacesPageAlias($tmplVars[$this::TV_REPLACE_ALIAS_NAME] ? $tmplVars[$this::TV_REPLACE_ALIAS_NAME] : $this::TV_REPLACE_ALIAS_DEFAULT);
        $uiPage->setMenuParentPageDefaultAlias($tmplVars[$this::TV_DEFAULT_PARENT_ALIAS_NAME] ? $tmplVars[$this::TV_DEFAULT_PARENT_ALIAS_NAME] : $this::TV_DEFAULT_PARENT_ALIAS_DEFAULT);
        $uiPage->setContents($row['contents']);
        
        return $uiPage;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPageId()
     */
    public function getPageId($page_or_id_or_alias)
    {
        $id_or_alias = $page_or_id_or_alias instanceof UiPageInterface ? $page_or_id_or_alias->getId() : $page_or_id_or_alias;
        return $this->getPageIds($id_or_alias)['id'];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPageAlias()
     */
    public function getPageAlias($page_or_id_or_alias)
    {
        $id_or_alias = $page_or_id_or_alias instanceof UiPageInterface ? $page_or_id_or_alias->getId() : $page_or_id_or_alias;
        return $this->getPageIds($id_or_alias)['alias'];
    }

    /**
     * Returns the CMS page id for the given page, UID or alias.
     *
     * @return integer
     */
    protected function getPageCmsId($page_or_uid_or_alias)
    {
        $id_or_alias = $page_or_uid_or_alias instanceof UiPageInterface ? $page_or_uid_or_alias->getId() : $page_or_uid_or_alias;
        return $this->getPageIds($id_or_alias)['idCms'];
    }
    
    /**
     * Returns TRUE if the given page exists in MODx and is published.
     * @param integer $cmsId
     * @throws UiPageNotFoundError if the page does not exist
     * @return boolean
     */
    protected function isPublished($cmsId) 
    {
        global $modx;
        
        $doc = $modx->getDocument($cmsId, 'id, published');
        if ($doc['id'] == $cmsId && $doc['published'] == 1) {
            return true;
        }
        return false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::createPage()
     */
    public function createPage(UiPageInterface $page)
    {
        global $modx;
        
        // Page IDs bestimmen.
        try {
            $parentAlias = $page->getMenuParentPageAlias();
            $parentIdCms = $this->getPageCmsId($parentAlias);
            $publish = $this->isPublished($parentIdCms);
        } catch (UiPageNotFoundError $upnfe) {
            $parentIdCms = $this->getApp()->getConfig()->getOption('MODX.PAGES.ROOT_CONTAINER_ID');
            $publish = false;
            $this->getWorkbench()->getLogger()->logException($upnfe, LoggerInterface::INFO);
            // TODO check if the root exists!
        }
        
        // Parent Document Groups bestimmen.
        $result = $modx->db->select('dg.document_group as document_group', $modx->getFullTableName('document_groups') . ' dg', 'dg.document = ' . $parentIdCms);
        $docGroups = [];
        while ($row = $modx->db->getRow($result)) {
            $docGroups[] = $row['document_group'];
        }
        
        require_once ($modx->config['base_path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modResource.php');
        $resource = new \modResource($modx);
        $resource->close();
        $resource->set('pagetitle', $page->getName());
        $resource->set('description', $page->getShortDescription());
        $resource->set('alias', $page->getAliasWithNamespace());
        $resource->set('published', $publish ? 1 : 0);
        $resource->set('template', $modx->config['default_template']);
        $resource->set('menuindex', $page->getMenuIndex());
        $resource->set('hidemenu', $page->getMenuVisible() ? '0' : '1');
        $resource->set('parent', $parentIdCms);
        $resource->set('content', $page->getContents());
        $resource->set($this::TV_UID_NAME, $page->getId());
        $resource->set($this::TV_APP_ALIAS_NAME, $page->getApp()->getAliasWithNamespace());
        $resource->set($this::TV_DO_UPDATE_NAME, $page->isUpdateable() ? '1' : '0');
        $resource->set($this::TV_REPLACE_ALIAS_NAME, $page->getReplacesPageAlias());
        $resource->set($this::TV_DEFAULT_PARENT_ALIAS_NAME, $page->getMenuParentPageDefaultAlias());
        $resource->setDocumentGroups(0, $docGroups);
        $idCms = $resource->save(true, true);
        
        // secure web documents - flag as private, siehe secure_web_documents.inc.php
        $siteContent = $modx->getFullTableName('site_content');
        $documentGroups = $modx->getFullTableName('document_groups');
        $webgroupAccess = $modx->getFullTableName('webgroup_access');
        $modx->db->update('privateweb = 0', $siteContent, $idCms > 0 ? 'id = ' . $idCms : 'privateweb = 1');
        $result = $modx->db->select('DISTINCT sc.id', $siteContent . ' sc LEFT JOIN ' . $documentGroups . ' dg ON dg.document = sc.id LEFT JOIN ' . $webgroupAccess . ' wga ON wga.documentgroup = dg.document_group', ($idCms > 0 ? ' sc.id = ' . $idCms . ' AND ' : '') . 'wga.id > 0');
        $ids = $modx->db->getColumn('id', $result);
        if (count($ids) > 0) {
            $modx->db->update('privateweb = 1', $siteContent, 'id IN (' . implode(', ', $ids) . ')');
        }
        
        // secure manager documents - flag as private, siehe secure_mgr_documents.inc.php
        $membergroupAccess = $modx->getFullTableName('membergroup_access');
        $modx->db->update('privatemgr = 0', $siteContent, $idCms > 0 ? 'id = ' . $idCms : 'privatemgr = 1');
        $result = $modx->db->select('DISTINCT sc.id', $siteContent . ' sc LEFT JOIN ' . $documentGroups . ' dg ON dg.document = sc.id LEFT JOIN ' . $membergroupAccess . ' mga ON mga.documentgroup = dg.document_group', ($idCms > 0 ? ' sc.id = ' . $idCms . ' AND ' : '') . 'mga.id > 0');
        $ids = $modx->db->getColumn('id', $result);
        if (count($ids) > 0) {
            $modx->db->update('privatemgr = 1', $siteContent, 'id IN (' . implode(', ', $ids) . ')');
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::updatePage()
     */
    public function updatePage(UiPageInterface $page)
    {
        global $modx;
        
        // Page IDs bestimmen.
        $idCms = $this->getPageCmsId($page->getId());
        try {
            $parentAlias = $page->getMenuParentPageAlias();
            $parentIdCms = $this->getPageCmsId($parentAlias);
        } catch (UiPageNotFoundError $upnfe) {
            $this->getWorkbench()->getLogger()->logException($upnfe);
            $parentIdCms = false;
        }
        
        require_once ($modx->config['base_path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modResource.php');
        $resource = new \modResource($modx);
        $resource->edit($idCms);
        $resource->set('pagetitle', $page->getName());
        $resource->set('description', $page->getShortDescription());
        $resource->set('alias', $page->getAliasWithNamespace());
        $resource->set('menuindex', $page->getMenuIndex());
        $resource->set('hidemenu', $page->getMenuVisible() ? '0' : '1');
        if ($parentIdCms !== false) {
            $resource->set('parent', $parentIdCms);
        }
        $resource->set('content', $page->getContents());
        $resource->set($this::TV_UID_NAME, $page->getId());
        $resource->set($this::TV_APP_ALIAS_NAME, $page->getApp()->getAliasWithNamespace());
        $resource->set($this::TV_DO_UPDATE_NAME, $page->isUpdateable() ? '1' : '0');
        $resource->set($this::TV_REPLACE_ALIAS_NAME, $page->getReplacesPageAlias());
        $resource->set($this::TV_DEFAULT_PARENT_ALIAS_NAME, $page->getMenuParentPageDefaultAlias());
        $resource->save(true, true);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::deletePage()
     */
    public function deletePage($page_or_id_or_alias)
    {
        global $modx;
        
        $id_or_alias = $page_or_id_or_alias instanceof UiPageInterface ? $page_or_id_or_alias->getId() : $page_or_id_or_alias;
        
        require_once ($modx->config['base_path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modResource.php');
        $resource = new \modResource($modx);
        $resource->delete($this->getPageCmsId($id_or_alias), true);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::clearCMSRecycleBin()
     */
    public function clearCmsRecycleBin()
    {
        require_once ($modx->config['base_path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modResource.php');
        $resource = new \modResource($modx);
        $resource->clearTrash(true);
    }

    /**
     * Determines the IDs of the page (UID, CMS-ID, alias) for any one of them passed.
     * 
     * @param string $page_id_or_alias
     * @throws UiPageNotFoundError if no page with the passed UID or CMS-ID or alias exists
     * @throws UiPageIdNotUniqueError if several pages with the same UID or CMS-ID or alias
     * exist
     * @return string
     */
    private function getPageIds($page_id_or_alias)
    {
        if (is_null($page_id_or_alias) || $page_id_or_alias === '') {
            throw new UiPageNotFoundError('Empty page_id_or_alias passed.');
        }
        
        // Die Wurzel des Modx-Menübaums.
        if ($page_id_or_alias === '0' or $page_id_or_alias === 0) {
            return ['idCms' => '0', 'alias' => '', 'id' => ''];
        }
        
        global $modx;
        
        $siteContent = $modx->getFullTableName('site_content');
        $siteTmplvars = $modx->getFullTableName('site_tmplvars');
        $siteTmplvarContentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
        
        if (substr($page_id_or_alias, 0, 2) == '0x') {
            $where = 'mst.name = "' . $this::TV_UID_NAME . '" and mstc.value = "' . $page_id_or_alias . '"';
        } elseif (! is_numeric($page_id_or_alias)) {
            $where = 'mst.name = "' . $this::TV_UID_NAME . '" and msc.alias = "' . $page_id_or_alias . '"';
        } else {
            $where = 'mst.name = "' . $this::TV_UID_NAME . '" and msc.id = ' . $page_id_or_alias;
        }
        
        $result = $modx->db->select('msc.id as idCms, msc.alias as alias, mstc.value as id', $siteContent . ' msc left join ' . $siteTmplvarContentvalues . ' mstc on msc.id = mstc.contentid left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id', $where);
        if ($modx->db->getRecordCount($result) == 0) {
            throw new UiPageNotFoundError('No page with UID/CMS-ID/alias "' . $page_id_or_alias . '" defined or page has no UID or alias.');
        } elseif ($modx->db->getRecordCount($result) == 1) {
            return $modx->db->getRow($result);
        } else {
            throw new UiPageIdNotUniqueError('More than one page with UID/CMS-ID/alias "' . $page_id_or_alias . '" defined.');
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPagesForApp()
     */
    public function getPagesForApp(AppInterface $app)
    {
        global $modx;
        
        $siteContent = $modx->getFullTableName('site_content');
        $siteTmplvars = $modx->getFullTableName('site_tmplvars');
        $siteTmplvarContentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
        
        $result = $modx->db->select('msc.id as id', $siteContent . ' msc left join ' . $siteTmplvarContentvalues . ' mstc on msc.id = mstc.contentid left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id', 'mst.name = "' . $this::TV_APP_ALIAS_NAME . '" and mstc.value = "' . $app->getAliasWithNamespace() . '"');
        $pages = [];
        while ($row = $modx->db->getRow($result)) {
            $pages[] = $this->loadPageByCmsId($row['id'], true);
        }
        
        return $pages;
    }

    /**
     * Returns an associative array in which the names of the template-variables are the keys
     * and the their ids are the values.
     * 
     * @return string[] ['template_variable_name' => 'template_variable_id', ...]
     */
    public function getTemplateVariableIds()
    {
        global $modx;
        
        $result = $modx->db->select('id, name', $modx->getFullTableName('site_tmplvars'));
        $tvIds = [];
        while ($row = $modx->db->getRow($result)) {
            $tvIds[$row['name']] = $row['id'];
        }
        
        return $tvIds;
    }

    /**
     * Tests if the passed $username is a modx web user.
     * 
     * @param string $username
     * @throws RuntimeException if more than one modx web users with the passed username exist
     * @return boolean
     */
    public function isModxWebUser($username)
    {
        global $modx;
        
        $web_users = $modx->getFullTableName('web_users');
        $result = $modx->db->select('wu.id as id', $web_users . ' wu', 'wu.username = "' . $modx->db->escape($username) . '"');
        if ($modx->db->getRecordCount($result) == 0) {
            return false;
        } elseif ($modx->db->getRecordCount($result) == 1) {
            return true;
        } else {
            throw new RuntimeException('More than one Modx web user with username "' . $username . '" defined.');
        }
    }

    /**
     * Returns the id of the web user with the given username.
     * 
     * @param string $username
     * @throws RuntimeException
     * @return integer
     */
    public function getModxWebUserId($username)
    {
        global $modx;
        
        $web_users = $modx->getFullTableName('web_users');
        $result = $modx->db->select('wu.id as id', $web_users . ' wu', 'wu.username = "' . $modx->db->escape($username) . '"');
        if ($modx->db->getRecordCount($result) == 0) {
            throw new RuntimeException('No Modx web user with username "' . $username . '" defined.');
        } elseif ($modx->db->getRecordCount($result) == 1) {
            return $modx->db->getRow($result)['id'];
        } else {
            throw new RuntimeException('More than one Modx web user with username "' . $username . '" defined.');
        }
    }

    /**
     * Tests if the passed $username is a modx manager user.
     * 
     * @param string $username
     * @throws RuntimeException if more than one modx manager users with the passed username exist
     * @return boolean
     */
    public function isModxMgrUser($username)
    {
        global $modx;
        
        $manager_users = $modx->getFullTableName('manager_users');
        $result = $modx->db->select('mu.id as id', $manager_users . ' mu', 'mu.username = "' . $modx->db->escape($username) . '"');
        if ($modx->db->getRecordCount($result) == 0) {
            return false;
        } elseif ($modx->db->getRecordCount($result) == 1) {
            return true;
        } else {
            throw new RuntimeException('More than one Modx manager user with username "' . $username . '" defined.');
        }
    }

    /**
     * Returns the id of the manager user with the given username.
     * 
     * @param string $username
     * @throws RuntimeException
     * @return integer
     */
    public function getModxMgrUserId($username)
    {
        global $modx;
        
        $mgr_users = $modx->getFullTableName('manager_users');
        $result = $modx->db->select('mu.id as id', $mgr_users . ' mu', 'mu.username = "' . $modx->db->escape($username) . '"');
        if ($modx->db->getRecordCount($result) == 0) {
            throw new RuntimeException('No Modx manager user with username "' . $username . '" defined.');
        } elseif ($modx->db->getRecordCount($result) == 1) {
            return $modx->db->getRow($result)['id'];
        } else {
            throw new RuntimeException('More than one Modx manager user with username "' . $username . '" defined.');
        }
    }

    /**
     * Wird ein Plugin im Backend ausgefuehrt, dann wird es vorruebergehend gesperrt (seit
     * ModX 1.2.1). Kehrt die Ausfuehrung direkt zurueck, wird es danach entsperrt (siehe
     * DocumentParser->evalPlugin()). Kehrt man nicht zurueck ($modx->redirect()/
     * $modx->webAlertAndQuit()/im Plugincode wird ein anderes Event im gleichen Plugin
     * getriggert), so bleibt das Plugin gesperrt und wird beim folgenden Aufruf nicht
     * ausgefuehrt. Diese Funktion entsperrt das Plugin manuell.
     * 
     * @param strin $plugin_name
     */
    public function unlockPlugin($plugin_name)
    {
        $lock_file_path = MODX_BASE_PATH . 'assets' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'lock_' . str_replace(' ', '-', strtolower($plugin_name)) . '.pageCache.php';
        if (is_file($lock_file_path)) {
            unlink($lock_file_path);
        }
    }
}
?>