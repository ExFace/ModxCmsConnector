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

    const MODX_ADD_ACTION = '4';

    const MODX_UPDATE_ACTION = '27';

    const DEFAULT_MENU_PARENT_ALIAS = 'exface.core.new-pages';

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
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getCurrentPageId()
     */
    public function getCurrentPageId()
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
        return $modx->makeUrl($this->getPageIdCms($id_or_alias), null, $url_params, 'full');
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
     * @see \exface\Core\Interfaces\CmsConnectorInterface::get_page_title()
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getCurrentPageAlias()
     */
    public function getCurrentPageAlias()
    {
        global $modx;
        return $modx->documentObject['alias'];
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::loadPageByIdCms()
     */
    public function loadPageByIdCms($page_id_cms, $ignore_replacements = false, $replaced_ids = [])
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
        
        $pageUid = $modx->documentObject[$this::TV_UID_NAME] ? $modx->documentObject[$this::TV_UID_NAME][1] : $this::TV_UID_DEFAULT;
        $pageAlias = $modx->documentObject['alias'];
        $uiPage = UiPageFactory::create($this->getWorkbench()->ui(), $pageAlias, $pageUid);
        
        $uiPage->setName($modx->documentObject['pagetitle']);
        $uiPage->setShortDescription($modx->documentObject['description']);
        $uiPage->setMenuIndex($modx->documentObject['menuindex']);
        $uiPage->setMenuVisible(! $modx->documentObject['hidemenu']);
        $uiPage->setMenuParentPageAlias($this->getPageId($modx->documentObject['parent']));
        $uiPage->setAppAlias($modx->documentObject[$this::TV_APP_ALIAS_NAME] ? $modx->documentObject[$this::TV_APP_ALIAS_NAME][1] : $this::TV_APP_ALIAS_DEFAULT);
        $uiPage->setUpdateable(array_key_exists($this::TV_DO_UPDATE_NAME, $modx->documentObject) ? $modx->documentObject[$this::TV_DO_UPDATE_NAME][1] : $this::TV_DO_UPDATE_DEFAULT);
        $uiPage->setReplacesPageAlias($modx->documentObject[$this::TV_REPLACE_ALIAS_NAME] ? $modx->documentObject[$this::TV_REPLACE_ALIAS_NAME][1] : $this::TV_REPLACE_ALIAS_DEFAULT);
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
        
        $pageUid = $tmplVars[$this::TV_UID_NAME] ? $tmplVars[$this::TV_UID_NAME] : $this::TV_UID_DEFAULT;
        $pageAlias = $row['alias'];
        $uiPage = UiPageFactory::create($this->getWorkbench()->ui(), $pageAlias, $pageUid);
        
        $uiPage->setName($row['name']);
        $uiPage->setShortDescription($row['shortDescription']);
        $uiPage->setMenuIndex($row['menuIndex']);
        $uiPage->setMenuVisible(! $row['hideMenu']);
        $uiPage->setMenuParentPageAlias($this->getPageId($row['menuParentIdCms']));
        $uiPage->setAppAlias($tmplVars[$this::TV_APP_ALIAS_NAME] ? $tmplVars[$this::TV_APP_ALIAS_NAME] : $this::TV_APP_ALIAS_DEFAULT);
        $uiPage->setUpdateable(array_key_exists($this::TV_DO_UPDATE_NAME, $tmplVars) ? $tmplVars[$this::TV_DO_UPDATE_NAME] : $this::TV_DO_UPDATE_DEFAULT);
        $uiPage->setReplacesPageAlias($tmplVars[$this::TV_REPLACE_ALIAS_NAME] ? $tmplVars[$this::TV_REPLACE_ALIAS_NAME] : $this::TV_REPLACE_ALIAS_DEFAULT);
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
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::getPageIdCms()
     */
    public function getPageIdCms($page_or_id_or_alias)
    {
        $id_or_alias = $page_or_id_or_alias instanceof UiPageInterface ? $page_or_id_or_alias->getId() : $page_or_id_or_alias;
        return $this->getPageIds($id_or_alias)['idCms'];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::createPage()
     */
    public function createPage(UiPageInterface $page)
    {
        global $modx;
        
        // IDs der Template-Variablen bestimmen.
        // $result = $modx->db->select('mst.id as id, mst.name as name', $modx->getFullTableName('site_tmplvars') . ' mst');
        // $tvIds = [];
        // while ($row = $modx->db->getRow($result)) {
        // $tvIds[$row['name']] = $row['id'];
        // }
        
        // Page IDs bestimmen.
        try {
            $parentAlias = $page->getMenuParentPageAlias() ? $page->getMenuParentPageAlias() : $this::DEFAULT_MENU_PARENT_ALIAS;
            $parentIdCms = $this->getPageIdCms($parentAlias);
        } catch (UiPageNotFoundError $upnfe) {
            // Die Parent-Seite hat eine ID, welche im CMS nicht existiert.
            if ($parentAlias != $this::DEFAULT_MENU_PARENT_ALIAS) {
                try {
                    $parentAlias = $this::DEFAULT_MENU_PARENT_ALIAS;
                    $parentIdCms = $this->getPageIdCms($parentAlias);
                } catch (UiPageNotFoundError $upnfe) {
                    // Die Default-Parent Seite existiert nicht im CMS.
                    throw new UiPageNotFoundError('The default parent page doesn\'t exist in the CMS.');
                }
            } else {
                // Die Default-Parent Seite existiert nicht im CMS.
                throw new UiPageNotFoundError('The default parent page doesn\'t exist in the CMS.');
            }
        }
        
        // Parent Document Groups bestimmen.
        $result = $modx->db->select('dg.document_group as document_group', $modx->getFullTableName('document_groups') . ' dg', 'dg.document = ' . $parentIdCms);
        $docGroups = [];
        while ($row = $modx->db->getRow($result)) {
            $docGroups[] = $row['document_group'];
        }
        
        // $_POST['id'] = '';
        // $_POST['mode'] = $this::MODX_ADD_ACTION;
        // $_POST['pagetitle'] = $page->getName();
        // $_POST['longtitle'] = '';
        // $_POST['description'] = $page->getShortDescription();
        // $_POST['alias'] = $page->getAliasWithNamespace();
        // $_POST['link_attributes'] = '';
        // $_POST['introtext'] = '';
        // $_POST['template'] = $modx->config['default_template'];
        // $_POST['menutitle'] = '';
        // $_POST['menuindex'] = $page->getMenuIndex();
        // $_POST['hidemenu'] = $page->getMenuVisible() ? '0' : '1';
        // $_POST['parent'] = $parentIdCms;
        // $_REQUEST['parent'] = $parentIdCms;
        // $_POST['ta'] = $page->getContents();
        // $_POST['tv' . $tvIds[$this::TV_UID_NAME]] = $page->getId();
        // $_POST['tv' . $tvIds[$this::TV_APP_ALIAS_NAME]] = $page->getAppAlias();
        // $_POST['tv' . $tvIds[$this::TV_DO_UPDATE_NAME]] = $page->isUpdateable() ? '1' : '0';
        // $_POST['tv' . $tvIds[$this::TV_REPLACE_ALIAS_NAME]] = $page->getReplacesPageAlias();
        // $_POST['published'] = '1';
        // $_POST['pub_date'] = '';
        // $_POST['unpub_date'] = '';
        // $_POST['type'] = 'document';
        // $_POST['contentType'] = 'text/html';
        // $_POST['content_dispo'] = '0';
        // $_POST['isfolder'] = '0';
        // $_POST['alias_visible'] = '1';
        // $_POST['richtext'] = '1';
        // $_POST['donthit'] = '0';
        // $_POST['searchable'] = '1';
        // $_POST['cacheable'] = '1';
        // $_POST['syncsite'] = '1';
        // $_POST['docgroups'] = $docGroups;
        
        // require $this->getApp()->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'processors' . DIRECTORY_SEPARATOR . 'save_content.processor.php';
        
        require_once ($modx->config['base_path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'resourse.php');
        $resourse = \resourse::Instance($modx);
        $resourse->document();
        $resourse->set('pagetitle', $page->getName());
        $resourse->set('description', $page->getShortDescription());
        $resourse->set('alias', $page->getAliasWithNamespace());
        $resourse->set('template', $modx->config['default_template']);
        $resourse->set('menuindex', $page->getMenuIndex());
        $resourse->set('hidemenu', $page->getMenuVisible() ? '0' : '1');
        $resourse->set('parent', $parentIdCms);
        $resourse->set('content', $page->getContents());
        $resourse->set($this::TV_UID_NAME, $page->getId());
        $resourse->set($this::TV_APP_ALIAS_NAME, $page->getAppAlias());
        $resourse->set($this::TV_DO_UPDATE_NAME, $page->isUpdateable() ? '1' : '0');
        $resourse->set($this::TV_REPLACE_ALIAS_NAME, $page->getReplacesPageAlias());
        $idCms = $resourse->save(true, true);
        
        // document access permissions
        foreach ($docGroups as $docGroup) {
            $modx->db->insert([
                'document_group' => $docGroup,
                'document' => $idCms
            ], $modx->getFullTableName('document_groups'));
        }
        
        // update parent folder status
        if ($parentIdCms != 0) {
            $modx->db->update([
                'isfolder' => 1
            ], $modx->getFullTableName('site_content'), "id='{$parentIdCms}'");
        }
        
        // secure web documents - flag as private
        require_once $this->getApp()->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'processors' . DIRECTORY_SEPARATOR . 'secure_web_documents.inc.php';
        secureWebDocument($idCms);
        
        // secure manager documents - flag as private
        require_once $this->getApp()->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'processors' . DIRECTORY_SEPARATOR . 'secure_mgr_documents.inc.php';
        secureMgrDocument($idCms);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::updatePage()
     */
    public function updatePage(UiPageInterface $page)
    {
        global $modx;
        
        // IDs der Template-Variablen bestimmen.
        // $result = $modx->db->select('mst.id as id, mst.name as name', $modx->getFullTableName('site_tmplvars') . ' mst');
        // $tvIds = [];
        // while ($row = $modx->db->getRow($result)) {
        // $tvIds[$row['name']] = $row['id'];
        // }
        
        // Page IDs bestimmen.
        $idCms = $this->getPageIdCms($page->getId());
        try {
            $parentAlias = $page->getMenuParentPageAlias() ? $page->getMenuParentPageAlias() : $this::DEFAULT_MENU_PARENT_ALIAS;
            $parentIdCms = $this->getPageIdCms($parentAlias);
        } catch (UiPageNotFoundError $upnfe) {
            // Die Parent-Seite hat eine ID, welche im CMS nicht existiert.
            if ($parentAlias != $this::DEFAULT_MENU_PARENT_ALIAS) {
                try {
                    $parentAlias = $this::DEFAULT_MENU_PARENT_ALIAS;
                    $parentIdCms = $this->getPageIdCms($parentAlias);
                } catch (UiPageNotFoundError $upnfe) {
                    // Die Default-Parent Seite existiert nicht im CMS.
                    throw new UiPageNotFoundError('The default parent page doesn\'t exist in the CMS.');
                }
            } else {
                // Die Default-Parent Seite existiert nicht im CMS.
                throw new UiPageNotFoundError('The default parent page doesn\'t exist in the CMS.');
            }
        }
        
        // Vorhandene Document Groups ermitteln.
        // $result = $modx->db->select('dg.document_group as document_group', $modx->getFullTableName('document_groups') . ' dg', 'dg.document = ' . $idCms);
        // $docGroups = [];
        // while ($row = $modx->db->getRow($result)) {
        // $docGroups[] = $row['document_group'];
        // }
        
        // Vorhandenes Template ermitteln.
        // $result = $modx->db->select('msc.template as template', $modx->getFullTableName('site_content') . ' msc', 'msc.id = ' . $idCms);
        // if ($modx->db->getRecordCount($result) == 0) {
        // throw new UiPageNotFoundError('No page with CMS-ID "' . $idCms . '" defined.');
        // } elseif ($modx->db->getRecordCount($result) == 1) {
        // $row = $modx->db->getRow($result);
        // $template = $row['template'];
        // } else {
        // throw new UiPageIdNotUniqueError('More than one page with CMS-ID "' . $idCms . '" defined.');
        // }
        
        // $_POST['id'] = $idCms;
        // $_POST['mode'] = $this::MODX_UPDATE_ACTION;
        // $_POST['pagetitle'] = $page->getName();
        // $_POST['longtitle'] = '';
        // $_POST['description'] = $page->getShortDescription();
        // $_POST['alias'] = $page->getAliasWithNamespace();
        // $_POST['link_attributes'] = '';
        // $_POST['introtext'] = '';
        // $_POST['template'] = $template;
        // $_POST['menutitle'] = '';
        // $_POST['menuindex'] = $page->getMenuIndex();
        // $_POST['hidemenu'] = $page->getMenuVisible() ? '0' : '1';
        // $_POST['parent'] = $parentIdCms;
        // $_REQUEST['parent'] = $parentIdCms;
        // $_POST['ta'] = $page->getContents();
        // $_POST['tv' . $tvIds[$this::TV_UID_NAME]] = $page->getId();
        // $_POST['tv' . $tvIds[$this::TV_APP_ALIAS_NAME]] = $page->getAppAlias();
        // $_POST['tv' . $tvIds[$this::TV_DO_UPDATE_NAME]] = $page->isUpdateable() ? '1' : '0';
        // $_POST['tv' . $tvIds[$this::TV_REPLACE_ALIAS_NAME]] = $page->getReplacesPageAlias();
        // $_POST['published'] = '1';
        // $_POST['pub_date'] = '';
        // $_POST['unpub_date'] = '';
        // $_POST['type'] = 'document';
        // $_POST['contentType'] = 'text/html';
        // $_POST['content_dispo'] = '0';
        // $_POST['isfolder'] = '0';
        // $_POST['alias_visible'] = '1';
        // $_POST['richtext'] = '1';
        // $_POST['donthit'] = '0';
        // $_POST['searchable'] = '1';
        // $_POST['cacheable'] = '1';
        // $_POST['syncsite'] = '1';
        // $_POST['docgroups'] = $docGroups;
        
        // require $this->getApp()->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'processors' . DIRECTORY_SEPARATOR . 'save_content.processor.php';
        
        require_once ($modx->config['base_path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'resourse.php');
        $resourse = \resourse::Instance($modx);
        $resourse->edit($idCms);
        $resourse->set('pagetitle', $page->getName());
        $resourse->set('description', $page->getShortDescription());
        $resourse->set('alias', $page->getAliasWithNamespace());
        $resourse->set('menuindex', $page->getMenuIndex());
        $resourse->set('hidemenu', $page->getMenuVisible() ? '0' : '1');
        $resourse->set('parent', $parentIdCms);
        $resourse->set('content', $page->getContents());
        $resourse->set($this::TV_UID_NAME, $page->getId());
        $resourse->set($this::TV_APP_ALIAS_NAME, $page->getAppAlias());
        $resourse->set($this::TV_DO_UPDATE_NAME, $page->isUpdateable() ? '1' : '0');
        $resourse->set($this::TV_REPLACE_ALIAS_NAME, $page->getReplacesPageAlias());
        $resourse->save(true, true);
        
        // update parent folder status
        if ($parentIdCms != 0) {
            $modx->db->update([
                'isfolder' => 1
            ], $modx->getFullTableName('site_content'), "id='{$parentIdCms}'");
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::deletePage()
     */
    public function deletePage($page_or_id_or_alias)
    {
        $id_or_alias = $page_or_id_or_alias instanceof UiPageInterface ? $page_or_id_or_alias->getId() : $page_or_id_or_alias;
        $_GET['id'] = $this->getPageIdCms($id_or_alias);
        require $this->getApp()->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'processors' . DIRECTORY_SEPARATOR . 'delete_content.processor.php';
        
        // resourse->delete loescht die Seiten sofort hart (ohne Verwendung des Papierkorbs).
        // require_once ($modx->config['base_path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'resourse.php');
        // $resourse = \resourse::Instance($modx);
        // $resourse->delete($this->getPageIdCms($id_or_alias));
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\CmsConnectorInterface::clearCMSRecycleBin()
     */
    public function clearCmsRecycleBin()
    {
        require $this->getApp()->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'processors' . DIRECTORY_SEPARATOR . 'remove_content.processor.php';
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
        if (! $page_id_or_alias) {
            throw new UiPageNotFoundError('Empty page_id_or_alias passed.');
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
            throw new UiPageNotFoundError('No page with UID/CMS-ID/alias "' . $page_id_or_alias . '" defined.');
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
        
        // Ueberpruefen ob die Seite durch eine andere ersetzt wird.
        $siteContent = $modx->getFullTableName('site_content');
        $siteTmplvars = $modx->getFullTableName('site_tmplvars');
        $siteTmplvarContentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
        
        $result = $modx->db->select('msc.id as id', $siteContent . ' msc left join ' . $siteTmplvarContentvalues . ' mstc on msc.id = mstc.contentid left join ' . $siteTmplvars . ' mst on mstc.tmplvarid = mst.id', 'mst.name = "' . $this::TV_APP_ALIAS_NAME . '" and mstc.value = "' . $app->getAliasWithNamespace() . '"');
        $pages = [];
        while ($row = $modx->db->getRow($result)) {
            $pages[] = $this->loadPageByIdCms($row['id'], true);
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