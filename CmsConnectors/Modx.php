<?php
namespace exface\ModxCmsConnector\CmsConnectors;

use exface\Core\Interfaces\CmsConnectorInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\Workbench;
use exface\ModxCmsConnector\ModxCmsConnectorApp;
use exface\Core\Factories\UiPageFactory;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\UiPage\UiPageNotFoundError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\CommonLogic\AbstractCmsConnector;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Exceptions\UiPage\UiPageLoadingError;
use exface\Core\Exceptions\UiPage\UiPageIdNotUniqueError;
use exface\Core\Exceptions\UiPage\UiPageCreateError;
use exface\Core\Exceptions\UiPage\UiPageUpdateError;

class Modx extends AbstractCmsConnector
{

    const USER_TYPE_MGR = 'mgr';

    const USER_TYPE_WEB = 'web';

    private $user_name = null;

    private $user_type = null;

    private $user_settings = null;

    private $user_locale = null;

    private $workbench = null;

    const TV_APP_UID_NAME = 'ExfacePageAppAlias';

    const TV_APP_UID_DEFAULT = '';

    const TV_REPLACE_ALIAS_NAME = 'ExfacePageReplaceAlias';

    const TV_REPLACE_ALIAS_DEFAULT = '';

    const TV_UID_NAME = 'ExfacePageUID';

    const TV_UID_DEFAULT = '';

    const TV_DO_UPDATE_NAME = 'ExfacePageDoUpdate';

    const TV_DO_UPDATE_DEFAULT = true;

    const TV_DEFAULT_MENU_POSITION_NAME = 'ExfacePageDefaultParentAlias';

    const TV_DEFAULT_MENU_POSITION_DEFAULT = '';

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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::createLinkInternal()
     */
    public function createLinkInternal($page_or_id_or_alias, $url_params = '')
    {
        global $modx;
        if ($page_or_id_or_alias instanceof UiPageInterface) {
            $cmsId = $this->getPageIdInCms($page_or_id_or_alias);
        } elseif ($this->isCmsId($page_or_id_or_alias)) {
            $cmsId = $page_or_id_or_alias;
        } else {
            try {
                $page = $this->loadPage($page_or_id_or_alias);
                $cmsId = $this->getPageIdInCms($page);
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::WARNING);
                return '';
            }
        }
        return $modx->makeUrl($cmsId, null, $url_params, 'full');
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
    public function loadPageByAlias($alias_with_namespace, $ignore_replacements = false)
    {
        return $this->getPageFromCms(null, null, $alias_with_namespace, $ignore_replacements);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::loadPageById()
     */
    public function loadPageById($uid, $ignore_replacements = false)
    {
        return $this->getPageFromCms(null, $uid, null, $ignore_replacements);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::loadPageCurrent()
     */
    public function loadPageCurrent($ignore_replacements = false)
    {
        global $modx;
        
        $siteContent = $modx->getFullTableName('site_content');
        
        $query = <<<SQL
    SELECT
        msc.alias as alias,
        ({$this->buildSqlReplaceIdsSubselect()}) as replacing_ids
    FROM {$siteContent} msc
    WHERE msc.id = {$modx->documentIdentifier}
SQL;
        $result = $modx->db->query($query);
        
        if ($modx->db->getRecordCount($result) == 0) {
            throw new UiPageNotFoundError('The requested UiPage with CMS-ID: "' . $modx->documentIdentifier . '" doesn\'t exist.');
        } elseif ($modx->db->getRecordCount($result) > 1) {
            throw new UiPageIdNotUniqueError('Several UiPages with the requested CMS-ID: "' . $modx->documentIdentifier . '" exist.');
        }
        
        $row = $modx->db->getRow($result);
        $page = $this->createPageFromApi();
        $this->addPageToCache($modx->documentIdentifier, $page);
        
        if (! $ignore_replacements && ! is_null($replacingPage = $this->getReplacePage($page, $row['replacing_ids']))) {
            return $replacingPage;
        }
        
        return $page;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractCmsConnector::getPageFromCms()
     */
    protected function getPageFromCms($cmsId = null, $uid = null, $alias = null, $ignore_replacements = false) {        
        global $modx;
        
        if (! is_null($modx->documentIdentifier)) {
            $currentPage = $this->loadPageCurrent();
            if (! is_null($alias)) {
                if ($currentPage->getAliasWithNamespace() === $alias) {
                    return $currentPage;
                }
            } elseif (! is_null($uid)) {
                if ($currentPage->getId() === $uid) {
                    return $currentPage;
                }
            } elseif (! is_null($cmsId)) {
                // We can't check as a page directly for it's CMS-id, but once the current page is loaded (see above)
                // it will be added to the cache, so checking the cache will return it here.
                if ($this->getPageFromCache($cmsId) === $currentPage) {
                    return $currentPage;
                }
            } else {
                throw new UiPageLoadingError('Error loading page from CMS database: no page identifier specified!');
            }
        }
        
        return $this->getPageFromDb($cmsId, $uid, $alias, $ignore_replacements);
    }
    
    /**
     * Loads and returns the requested UiPage from the database.
     * 
     * @param integer $cmsId
     * @param string $uid
     * @param string $alias
     * @param boolean $ignore_replacements
     * @throws UiPageLoadingError
     * @throws UiPageNotFoundError
     * @throws UiPageIdNotUniqueError
     * @return UiPageInterface
     */
    protected function getPageFromDb($cmsId = null, $uid = null, $alias = null, $ignore_replacements = false)
    {
        if (is_null($cmsId) && is_null($uid) && is_null($alias)) {
            throw new UiPageLoadingError('Error loading page from CMS database: no page identifier specified!');
        }
        
        global $modx;
        
        $siteContent = $modx->getFullTableName('site_content');
        $siteTmplvarContentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
        $siteTmplvars = $modx->getFullTableName('site_tmplvars');
        
        $uidSubselect = $this->buildSqlTmplvarSubselect($this::TV_UID_NAME);
        
        if (! is_null($uid)) {
            $where = 'EXISTS (' . $uidSubselect . ' and mstc.value = "' . $uid . '")';
        } 
        if (! is_null($alias)) {
            $where = 'msc.alias = "' . $alias . '"';
        }
        if (! is_null($cmsId)) {
            $where = 'msc.id = ' . intval($cmsId);
        }
        
        $query = <<<SQL
    SELECT
        msc.id as id,
        msc.pagetitle as name,
        msc.description as shortDescription,
        msc.alias as alias,
        msc.template as template,
        msc.menuindex as menuIndex,
        msc.hidemenu as hideMenu,
        msc.parent as menuParentIdCms,
        msc.content as contents,
        ({$uidSubselect}) as uid,
        ({$this->buildSqlTmplvarSubselect(self::TV_APP_UID_NAME)}) as app_uid,
        ({$this->buildSqlTmplvarSubselect(self::TV_DEFAULT_MENU_POSITION_NAME)}) as default_menu_position,
        ({$this->buildSqlTmplvarSubselect(self::TV_REPLACE_ALIAS_NAME)}) as replace_alias,
        ({$this->buildSqlTmplvarSubselect(self::TV_DO_UPDATE_NAME)}) as do_update,
        ({$this->buildSqlReplaceIdsSubselect()}) as replacing_ids
    FROM {$siteContent} msc
    WHERE {$where}
SQL;
        $result = $modx->db->query($query);
        
        if ($modx->db->getRecordCount($result) == 0) {
            throw new UiPageNotFoundError('The requested UiPage with CMS-ID: "' . $cmsId . '"/UID: "' . $uid . '"/alias: "' . $alias . '" doesn\'t exist.');
        } elseif ($modx->db->getRecordCount($result) > 1) {
            throw new UiPageIdNotUniqueError('Several UiPages with the requested CMS-ID: "' . $cmsId . '"/UID: "' . $uid . '"/alias: "' . $alias . '" exist.');
        }
        
        $row = $modx->db->getRow($result);
        $page = $this->createPageFromDbRow($row);
        $this->addPageToCache($row['id'], $page);
        
        if (! $ignore_replacements && ! is_null($replacingPage = $this->getReplacePage($page, $row['replacing_ids']))) {
            return $replacingPage;
        }
        
        return $page;
    }
    
    /**
     * Returns the UiPage specified by $replacingIdsConcatString, replacing the UiPage
     * $page.
     * 
     * The replaced UiPage $page is also replaced in the page cache by the replacing page.
     * 
     * @param UiPageInterface $page
     * @param string $replacingIdsConcatString
     * @throws UiPageLoadingError
     * @return UiPageInterface|null
     */
    protected function getReplacePage(UiPageInterface $page, $replacingIdsConcatString)
    {
        if (! is_null($replacingIdsConcatString)) {
            $replacingIds = explode(',', $replacingIdsConcatString);
            if (count($replacingIds) > 1) {
                throw new UiPageLoadingError('Page "' . $page->getAliasWithNamespace() . '" is replaced by multiple pages with the respective CMS-Ids ' . $replacingIdsConcatString . ': only one replacement per page allowed!');
            }
            $replacingPage = $this->getPageFromDb($replacingIds[0]);
            $this->replacePageInCache($page, $replacingIds[0], $replacingPage);
            return $replacingPage;
        }
        return null;
    }
    
    /**
     * Returns an SQL subquery to obtain a template variable.
     * 
     * @param string $tmplvarName
     * @return string
     */
    protected function buildSqlTmplvarSubselect($tmplvarName)
    {
        global $modx;
        
        $siteTmplvars = $modx->getFullTableName('site_tmplvars');
        $siteTmplvarContentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
        
        return <<<SQL
        SELECT
            mstc.value
        FROM {$siteTmplvarContentvalues} mstc
            LEFT JOIN {$siteTmplvars} mst ON mstc.tmplvarid = mst.id
        WHERE msc.id = mstc.contentid
            AND mst.name = "{$tmplvarName}"
SQL;
    }
    
    /**
     * Returns an SQL subquery to obtain the CMS-IDs of the replacing pages.
     * 
     * @return string
     */
    protected function buildSqlReplaceIdsSubselect()
    {
        global $modx;
        
        $siteTmplvars = $modx->getFullTableName('site_tmplvars');
        $siteTmplvarContentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
        $replaceAliasTvName = self::TV_REPLACE_ALIAS_NAME;
        
        return <<<SQL
        SELECT
            GROUP_CONCAT(r_mstc.contentid SEPARATOR ',')
        FROM {$siteTmplvarContentvalues} r_mstc
            LEFT JOIN {$siteTmplvars} r_mst ON r_mstc.tmplvarid = r_mst.id
        WHERE r_mst.name = "{$replaceAliasTvName}"
            AND r_mstc.value = msc.alias
SQL;
    }

    /**
     * Returns the current page from ModX.
     * 
     * @return UiPageInterface
     */
    protected function createPageFromApi()
    {
        global $modx;
        
        $pageAlias = $modx->documentObject['alias'];
        $pageUid = $modx->documentObject[$this::TV_UID_NAME] ? $modx->documentObject[$this::TV_UID_NAME][1] : $this::TV_UID_DEFAULT;
        $appUid = $modx->documentObject[$this::TV_APP_UID_NAME] ? $modx->documentObject[$this::TV_APP_UID_NAME][1] : $this::TV_APP_UID_DEFAULT;
        $uiPage = UiPageFactory::create($this->getWorkbench()->ui(), $pageAlias, $pageUid, $appUid);
        
        $uiPage->setName($modx->documentObject['pagetitle']);
        $uiPage->setShortDescription($modx->documentObject['description']);
        $uiPage->setMenuIndex($modx->documentObject['menuindex']);
        $uiPage->setMenuVisible(! $modx->documentObject['hidemenu']);
        $uiPage->setMenuParentPageSelector($modx->documentObject['parent']);
        $uiPage->setUpdateable(array_key_exists($this::TV_DO_UPDATE_NAME, $modx->documentObject) ? $modx->documentObject[$this::TV_DO_UPDATE_NAME][1] : $this::TV_DO_UPDATE_DEFAULT);
        $uiPage->setReplacesPageAlias($modx->documentObject[$this::TV_REPLACE_ALIAS_NAME] ? $modx->documentObject[$this::TV_REPLACE_ALIAS_NAME][1] : $this::TV_REPLACE_ALIAS_DEFAULT);
        $uiPage->setMenuDefaultPosition($modx->documentObject[$this::TV_DEFAULT_MENU_POSITION_NAME] ? $modx->documentObject[$this::TV_DEFAULT_MENU_POSITION_NAME][1] : $this::TV_DEFAULT_MENU_POSITION_DEFAULT);
        $uiPage->setContents($modx->documentObject['content']);
        
        return $uiPage;
    }
    
    /**
     * Returns a UiPage from the database.
     * 
     * @param resource $result contains the modx_site_contents of the page
     * @return UiPageInterface
     */
    protected function createPageFromDbRow($row)
    {
        $pageAlias = $row['alias'];
        $pageUid = $row['uid'];
        $appUid = $row['app_uid'] ? $row['app_uid'] : $this::TV_APP_UID_DEFAULT;
        $uiPage = UiPageFactory::create($this->getWorkbench()->ui(), $pageAlias, $pageUid, $appUid);
        
        $uiPage->setName($row['name']);
        $uiPage->setShortDescription($row['shortDescription']);
        $uiPage->setMenuIndex($row['menuIndex']);
        $uiPage->setMenuVisible(! $row['hideMenu']);
        $uiPage->setMenuParentPageSelector($row['menuParentIdCms']);
        $uiPage->setUpdateable($row['do_update']);
        $uiPage->setReplacesPageAlias($row['replace_alias']);
        $uiPage->setMenuDefaultPosition($row['default_menu_position']);
        $uiPage->setContents($row['contents']);
        
        return $uiPage;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::createPage()
     */
    public function createPage(UiPageInterface $page)
    {
        global $modx;
        
        try {
            $existingPage = $this->loadPage($page->getId());
            // Es existiert bereits eine Seite mit dieser UID.
            throw new UiPageIdNotUniqueError('A different UiPage with the same UID "' . $page->getId() . '" already exists.');
        } catch (UiPageNotFoundError $upnfe) {
            // Alles ok, es existiert noch keine Seite mit dieser UID.
        }
        
        try {
            $existingPage = $this->loadPage($page->getAliasWithNamespace());
            // Es existiert bereits eine Seite mit diesem Alias.
            throw new UiPageIdNotUniqueError('A different UiPage with the same alias "' . $page->getAliasWithNamespace() . '" already exists.');
        } catch (UiPageNotFoundError $upnfe) {
            // Alles ok, es existiert noch keine Seite mit diesem Alias.
        }
        
        // Page IDs bestimmen.
        try {
            $parentAlias = $page->getMenuParentPageAlias();
            $parentPage = $this->loadPage($parentAlias);
            $parentIdCms = $this->getPageIdInCms($parentPage);
        } catch (UiPageNotFoundError $upnfe) {
            $this->getWorkbench()->getLogger()->logException($upnfe, LoggerInterface::INFO);
            // TODO check if the root exists!
            $parentIdCms = $this->getPageIdRoot();
        }
        
        // Parent Document Object von Modx laden.
        $parentDoc = $this->getModxDocument($parentIdCms);
        $published = $parentDoc['published'];
        $template = $parentDoc['template'] ? $parentDoc['template'] : $modx->config['default_template'];
        $docGroups = $parentDoc['document_groups'] ? explode(',', $parentDoc['document_groups']) : [];
        
        require_once ($modx->config['base_path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modResource.php');
        $resource = new \modResource($modx);
        $resource->close();
        $resource->set('pagetitle', $page->getName());
        $resource->set('description', $page->getShortDescription());
        $resource->set('alias', $page->getAliasWithNamespace());
        $resource->set('published', $published ? '1' : '0');
        // IDEA configure the default template in the connector config as the default MODx template
        // may be one without all the needed TVs
        $resource->set('template', $template);
        $resource->set('menuindex', $page->getMenuIndex());
        $resource->set('hidemenu', $page->getMenuVisible() ? '0' : '1');
        $resource->set('parent', $parentIdCms);
        $resource->set('content', $page->getContents());
        $resource->set($this::TV_UID_NAME, $page->getId());
        $resource->set($this::TV_APP_UID_NAME, $page->getApp()->getUid());
        $resource->set($this::TV_DO_UPDATE_NAME, $page->isUpdateable() ? '1' : '0');
        $resource->set($this::TV_REPLACE_ALIAS_NAME, $page->getReplacesPageAlias() ? $page->getReplacesPageAlias(): ''); // wird null uebergeben, wird es ignoriert
        $resource->set($this::TV_DEFAULT_MENU_POSITION_NAME, $page->getMenuDefaultPosition());
        $resource->setDocumentGroups(0, $docGroups);
        $idCms = $resource->save(true, true);
        
        if ($idCms === false) {
            throw new UiPageCreateError($resource->list_log());
        }
        
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
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::updatePage()
     */
    public function updatePage(UiPageInterface $page)
    {
        global $modx;
        
        // Get the CMS id of the currently saved page matching the UID of the new page
        $idCms = $this->getPageIdInCms($page);
        
        try {
            $parentAlias = $page->getMenuParentPageAlias();
            $parentPage = $this->loadPage($parentAlias);
            $parentIdCms = $this->getPageIdInCms($parentPage);
        } catch (UiPageNotFoundError $upnfe) {
            $this->getWorkbench()->getLogger()->logException($upnfe, LoggerInterface::INFO);
            // TODO check if the root exists!
            $parentIdCms = $this->getPageIdRoot();
        }
        
        require_once ($modx->config['base_path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modResource.php');
        $resource = new \modResource($modx);
        $resource->edit($idCms);
        $resource->set('pagetitle', $page->getName());
        $resource->set('description', $page->getShortDescription());
        $resource->set('alias', $page->getAliasWithNamespace());
        $resource->set('menuindex', $page->getMenuIndex());
        $resource->set('hidemenu', $page->getMenuVisible() ? '0' : '1');
        $resource->set('deleted', 0); // This will undelete pages, that the user marked as deleted.
        $resource->set('parent', $parentIdCms);
        $resource->set('content', $page->getContents());
        $resource->set($this::TV_UID_NAME, $page->getId());
        $resource->set($this::TV_APP_UID_NAME, $page->getApp()->getUid());
        $resource->set($this::TV_DO_UPDATE_NAME, $page->isUpdateable() ? '1' : '0');
        $resource->set($this::TV_REPLACE_ALIAS_NAME, $page->getReplacesPageAlias() ? $page->getReplacesPageAlias(): ''); // wird null uebergeben, wird es ignoriert
        $resource->set($this::TV_DEFAULT_MENU_POSITION_NAME, $page->getMenuDefaultPosition());
        $updateResult = $resource->save(true, true);
        
        if ($updateResult === false) {
            throw new UiPageUpdateError($resource->list_log());
        }
        
        // Now that we saved the page, we must update the cache.
        $this->addPageToCache($idCms, $page);
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::deletePage()
     */
    public function deletePage($page_or_id_or_alias)
    {
        global $modx;
        
        $page = $page_or_id_or_alias instanceof UiPageInterface ? $page_or_id_or_alias : $this->loadPage($page_or_id_or_alias);
        
        require_once ($modx->config['base_path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modResource.php');
        $resource = new \modResource($modx);
        $resource->delete($this->getPageIdInCms($page), true);
        
        // Clear the cache to make sure, no references to the page remain
        $this->clearPagesCache();
        
        return $this;
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
        $tvAppUidName = self::TV_APP_UID_NAME;
        
        $query = <<<SQL
    SELECT
        msc.id as id
    FROM
        {$siteContent} msc
        LEFT JOIN {$siteTmplvarContentvalues} mstc ON msc.id = mstc.contentid
        LEFT JOIN {$siteTmplvars} mst ON mstc.tmplvarid = mst.id
    WHERE
        mst.name = "{$tvAppUidName}"
        AND mstc.value = "{$app->getUid()}"
        AND msc.deleted = "0"
SQL;
        $result = $modx->db->query($query);
        
        $pages = [];
        while ($row = $modx->db->getRow($result)) {
            $pages[] = $this->getPageFromCms($row['id'], null, null, true);
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
     * Returns an associative array in which the names of the system-events are the keys
     * and the their ids are the values.
     * 
     * @return string[] ['system_event_name' => 'system_event_id', ...]
     */
    public function getSystemEventIds()
    {
        global $modx;
        
        $result = $modx->db->select('id, name', $modx->getFullTableName('system_eventnames'));
        $eventIds = [];
        while ($row = $modx->db->getRow($result)) {
            $eventIds[$row['name']] = $row['id'];
        }
        
        return $eventIds;
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
     * @param string $plugin_name
     */
    public function unlockPlugin($plugin_name)
    {
        $lock_file_path = MODX_BASE_PATH . 'assets' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'lock_' . str_replace(' ', '-', strtolower($plugin_name)) . '.pageCache.php';
        if (is_file($lock_file_path)) {
            unlink($lock_file_path);
        }
    }
    
    /**
     * This function is a replacement for $modx->getDocument().
     * 
     * The original function doesn't return private documents (documents with restricted
     * resource groups) when ModX is in the frontend mode.
     * 
     * It additionally returns the document groups of the requested document.
     * 
     * @param integer $cmsId
     * @throws UiPageNotFoundError
     * @throws UiPageIdNotUniqueError
     * @return string[]
     */
    protected function getModxDocument($cmsId)
    {
        global $modx;
        
        if (! $cmsId) {
            return [];
        }
        
        $siteContent = $modx->getFullTableName('site_content');
        $documentGroups = $modx->getFullTableName('document_groups');
        
        $query = <<<SQL
    SELECT
        msc.*,
        (SELECT
            GROUP_CONCAT(dg.document_group SEPARATOR ',')
        FROM
            {$documentGroups} dg
        WHERE
            dg.document = {$cmsId}) as document_groups
    FROM
        {$siteContent} msc
    WHERE
        msc.id = {$cmsId}
SQL;
        $result = $modx->db->query($query);
        
        if ($modx->db->getRecordCount($result) == 0) {
            throw new UiPageNotFoundError('The requested UiPage with CMS-ID: "' . $cmsId . '" doesn\'t exist.');
        } elseif ($modx->db->getRecordCount($result) > 1) {
            throw new UiPageIdNotUniqueError('Several UiPages with the requested CMS-ID: "' . $cmsId . '" exist.');
        }
        
        $row = $modx->db->getRow($result);
        
        return $row;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractCmsConnector::isCmsId()
     */
    protected function isCmsId($page_id_or_alias)
    {
        if (! $this->isUid($page_id_or_alias) && is_numeric($page_id_or_alias)) {
            return true;
        }
        
        return false;
    }
}
?>