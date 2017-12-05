<?php
namespace exface\ModxCmsConnector\CommonLogic;

class ModxSessionManager
{

    private $modx;

    private $savedSessionId;

    /**
     * 
     * @param \DocumentParser $modx
     */
    public function __construct(\DocumentParser $modx)
    {
        $this->setModx($modx);
    }

    /**
     * 
     * @return ModxSessionManager
     */
    public function sessionOpen()
    {
        if ($this->sessionIsOpen()) {
            // Schliessen der zuvor geoeffneten Session.
            $this->setSavedSessionId(session_id());
            session_write_close();
        } else {
            $this->setSavedSessionId(null);
        }
        
        // Oeffnen der Modx-Session.
        require_once $this->getModx()->getConfig('site_manager_path') . 'includes' . DIRECTORY_SEPARATOR . 'config.inc.php';
        startCMSSession();
        
        return $this;
    }

    /**
     * 
     * @return ModxSessionManager
     */
    public function sessionClose()
    {
        // Schliessen der Modx-Session.
        session_write_close();
        
        if ($this->getSavedSessionId()) {
            // Oeffnen der zuvor geoeffneten Session.
            session_id($this->getSavedSessionId());
            session_start();
        }
        $this->setSavedSessionId(null);
        
        return $this;
    }

    /**
     * 
     * @return boolean
     */
    protected function sessionIsOpen()
    {
        if (php_sapi_name() !== 'cli') {
            if (version_compare(phpversion(), '5.4.0', '>=')) {
                return session_status() === PHP_SESSION_ACTIVE ? true : false;
            } else {
                return session_id() === '' ? false : true;
            }
        }
        return false;
    }

    /**
     * 
     * @return \DocumentParser
     */
    protected function getModx()
    {
        return $this->modx;
    }

    /**
     * 
     * @param \DocumentParser $modx
     * @return ModxSessionManager
     */
    protected function setModx(\DocumentParser $modx)
    {
        $this->modx = $modx;
        return $this;
    }

    /**
     * 
     * @return string
     */
    protected function getSavedSessionId()
    {
        return $this->savedSessionId;
    }

    /**
     * 
     * @param string $savedSessionId
     * @return ModxSessionManager
     */
    protected function setSavedSessionId($savedSessionId)
    {
        $this->savedSessionId = $savedSessionId;
        return $this;
    }
}
