<?php
namespace exface\ModxCmsConnector\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\DataConnectors\MySqlConnector;

/**
 * The MODx DB connector uses the same MySQL connection as MODx.
 * This only works if MODx is set to use mysqli!
 *
 * @author Andrej Kabachnik
 *        
 */
class ModxDb extends MySqlConnector
{
    /**
     * 
     * @return DocumentParser
     */
    protected function getModx()
    {
        global $modx;
        return $modx;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        $modx = $this->getModx();
        $this->enableErrorExceptions();
        
        if (! $modx->db->isConnected) {
            $modx->db->connect($this->getHost(), $this->getDbase(), $this->getUser(), $this->getPassword(), $this->getConnectionMethod());
        }
        $this->setCurrentConnection($modx->db->conn);
    }
    
    
    public function isConnected() : bool
    {
        return $this->getModx()->db->isConnected === true;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performDisconnect()
     */
    protected function performDisconnect()
    {
        // Disconnect is handled by modx, not by ExFace, so no need to do anything here
    }
}
?>