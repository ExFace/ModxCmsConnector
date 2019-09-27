<?php
namespace exface\ModxCmsConnector\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\DataConnectors\MySqlConnector;
use exface\Core\Exceptions\DataSources\DataConnectionConfigurationError;

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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\MySqlConnector::getDbase()
     */
    public function getDbase()
    {
        return $this->getModx()->db->config['dbase'];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getHost()
     */
    public function getHost()
    {
        return $this->getModx()->db->config['host'];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getUser()
     */
    public function getUser()
    {
        return $this->getModx()->db->config['user'];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getPassword()
     */
    public function getPassword()
    {
        return $this->getModx()->db->config['pass'];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getCharacterSet()
     */
    public function getCharacterSet()
    {
        return $this->getModx()->db->config['charset'];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\MySqlConnector::getConnectionMethod()
     */
    public function getConnectionMethod()
    {
        return $this->getModx()->db->config['connection_method'];
    }
    
    /**
     * 
     * @return string
     */
    public function getModxTablePrefix() : string
    {
        return $this->getModx()->db->config['table_prefix'];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::setHost()
     */
    public function setHost($value)
    {
        throw new DataConnectionConfigurationError($this, 'Cannot set connection options for CMS DB connector: use the database configuration of the CMS!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\MySqlConnector::setDbase()
     */
    public function setDbase($value)
    {
        throw new DataConnectionConfigurationError($this, 'Cannot set connection options for CMS DB connector: use the database configuration of the CMS!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::setUser()
     */
    public function setUser($value)
    {
        throw new DataConnectionConfigurationError($this, 'Cannot set connection options for CMS DB connector: use the database configuration of the CMS!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\MySqlConnector::setUsePersistantConnection()
     */
    public function setUsePersistantConnection($value)
    {
        throw new DataConnectionConfigurationError($this, 'Cannot set connection options for CMS DB connector: use the database configuration of the CMS!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::setPassword()
     */
    public function setPassword($value)
    {
        throw new DataConnectionConfigurationError($this, 'Cannot set connection options for CMS DB connector: use the database configuration of the CMS!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\MySqlConnector::setCharset()
     */
    public function setCharset($value)
    {
        throw new DataConnectionConfigurationError($this, 'Cannot set connection options for CMS DB connector: use the database configuration of the CMS!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::setCharacterSet()
     */
    public function setCharacterSet($value)
    {
        throw new DataConnectionConfigurationError($this, 'Cannot set connection options for CMS DB connector: use the database configuration of the CMS!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\MySqlConnector::setConnectionMethod()
     */
    public function setConnectionMethod($value)
    {
        throw new DataConnectionConfigurationError($this, 'Cannot set connection options for CMS DB connector: use the database configuration of the CMS!');
    }
}