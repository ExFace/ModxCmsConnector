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
use exface\Core\Exceptions\UiPageLoadingError;

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
    public function loadPageCurrent()
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
        $uiPage->setMenuParentPageSelector($modx->documentObject['parent']);
        $uiPage->setUpdateable(array_key_exists($this::TV_DO_UPDATE_NAME, $modx->documentObject) ? $modx->documentObject[$this::TV_DO_UPDATE_NAME][1] : $this::TV_DO_UPDATE_DEFAULT);
        $uiPage->setReplacesPageAlias($modx->documentObject[$this::TV_REPLACE_ALIAS_NAME] ? $modx->documentObject[$this::TV_REPLACE_ALIAS_NAME][1] : $this::TV_REPLACE_ALIAS_DEFAULT);
        $uiPage->setMenuParentPageDefaultAlias($modx->documentObject[$this::TV_DEFAULT_PARENT_ALIAS_NAME] ? $modx->documentObject[$this::TV_DEFAULT_PARENT_ALIAS_NAME] : $this::TV_DEFAULT_PARENT_ALIAS_DEFAULT);
        $uiPage->setContents($modx->documentObject['content']);
        
        $this->addPageToCache($modx->documentIdentifier, $uiPage);
        
        // FIXME look for replacing pages!
        
        return $uiPage;
    }
    
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
    
    protected function getPageFromDb($cmsId = null, $uid = null, $alias = null, $ignore_replacements = false)
    {
        if (is_null($cmsId) && is_null($uid) && is_null($alias)) {
            throw new UiPageLoadingError('Error loading page from CMS database: no page identifier specified!');
        }
        
        global $modx;
        
        $siteContent = $modx->getFullTableName('site_content');
        $siteTmplvarContentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
        $siteTmplvars = $modx->getFullTableName('site_tmplvars');
        $replaceAliasTvName = self::TV_REPLACE_ALIAS_NAME;
        
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
        ({$this->buildSqlTmplvarSubselect(self::TV_APP_ALIAS_NAME)}) as app_alias,
        ({$this->buildSqlTmplvarSubselect(self::TV_DEFAULT_PARENT_ALIAS_NAME)}) as default_parent_alias,
        ({$this->buildSqlTmplvarSubselect(self::TV_REPLACE_ALIAS_NAME)}) as replace_alias,
        ({$this->buildSqlTmplvarSubselect(self::TV_DO_UPDATE_NAME)}) as do_update,
        (SELECT 
            GROUP_CONCAT(r_mstc.contentid SEPARATOR ',')
            FROM {$siteTmplvarContentvalues} r_mstc
                LEFT JOIN {$siteTmplvars} r_mst ON r_mstc.tmplvarid = r_mst.id 
            WHERE r_mst.name = "{$replaceAliasTvName}"
                AND r_mstc.value = msc.alias
        ) as replacing_ids
    FROM {$siteContent}  msc
    WHERE {$where}
SQL;
        $result = $modx->db->query($query);
        $row = $modx->db->getRow($result);
        $page = $this->createPageFromDbRow($row);
        
        if (! $ignore_replacements && ! is_null($row['replacing_ids'])) {
            $replacing_ids = explode(',', $row['replacing_ids']);
            if (count($replacing_ids > 1)) {
                throw new UiPageLoadingError('Page "' . $page->getAliasWithNamespace() . '" is replaced by multiple pages with the respective CMS-Ids ' . $row['replacing_ids'] . ': only one replacement per page allowed!');
            }
            $replacingPage = $this->getPageFromCms($replacing_ids[0]);
            $this->replacePageInCache($page, $replacing_ids[0], $replacingPage);
            return $replacingPage;
        }
        
        $this->addPageToCache($row['id'], $page);
        
        return $page;
    }
    
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
     * Returns a UiPage from the database.
     * 
     * @param resource $result contains the modx_site_contents of the page
     * @return UiPageInterface
     */
    protected function createPageFromDbRow($row)
    {
        $pageAlias = $row['alias'];
        $pageUid = $row['uid'];
        $appAlias = $row['app_alias'] ? $row['app_alias'] : $this::TV_APP_ALIAS_DEFAULT;
        $uiPage = UiPageFactory::create($this->getWorkbench()->ui(), $pageAlias, $pageUid, $appAlias);
        
        $uiPage->setName($row['name']);
        $uiPage->setShortDescription($row['shortDescription']);
        $uiPage->setMenuIndex($row['menuIndex']);
        $uiPage->setMenuVisible(! $row['hideMenu']);
        $uiPage->setMenuParentPageSelector($row['menuParentIdCms']);
        $uiPage->setUpdateable($row['do_update']);
        $uiPage->setReplacesPageAlias($row['replace_alias']);
        $uiPage->setMenuParentPageDefaultAlias($row['default_parent_alias']);
        $uiPage->setContents($row['contents']);
        
        return $uiPage;
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
    
    protected function isCmsId($page_id_or_alias)
    {
        if (! $this->isUid($page_id_or_alias) && is_numeric($page_id_or_alias)) {
            return true;
        }
        
        return false;
    }
}
?>