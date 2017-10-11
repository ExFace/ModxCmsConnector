<?php
namespace exface\ModxCmsConnector\CmsConnectors;

use exface\Core\Interfaces\CmsConnectorInterface;
use exface\Core\CommonLogic\Workbench;
use exface\ModxCmsConnector\ModxCmsConnectorApp;
use exface\Core\Factories\UiPageFactory;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Exceptions\RuntimeException;

class Modx implements CmsConnectorInterface
{

    const USER_TYPE_MGR = 'mgr';

    const USER_TYPE_WEB = 'web';

    private $user_name = null;

    private $user_type = null;

    private $user_settings = null;

    private $user_locale = null;

    private $workbench = null;

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
        
        UiPageFactory::createFromString($this->getWorkbench()->ui(), (is_null($id) ? 0 : $id), $content);
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
     * Tests if the passed $username is a modx web user.
     * 
     * @param string $username
     * @throws RuntimeException if more than one modx web users with the passed username exist
     * @return boolean
     */
    public function isModxWebUser($username) {
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
    public function getModxWebUserId($username) {
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
    public function isModxMgrUser($username) {
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
    public function getModxMgrUserId($username) {
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
    public function unlockPlugin($plugin_name) {
        $lock_file_path = MODX_BASE_PATH . 'assets' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'lock_' . str_replace(' ', '-', strtolower($plugin_name)) . '.pageCache.php';
        if (is_file($lock_file_path)) {
            unlink($lock_file_path);
        }
    }
}
?>