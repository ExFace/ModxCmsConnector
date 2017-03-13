<?php namespace exface\ModxCmsConnector\DataConnectors;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\SqlDataConnector\DataConnectors\MySQL;

/**
 * The MODx DB connector uses the same MySQL connection as MODx. This only works if MODx is set to use mysqli! 
 * 
 * @author Andrej Kabachnik
 *
 */
class ModxDb extends MySQL {

	var $conn;
	var $isConnected;
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::perform_connect()
	 */
	protected function perform_connect() {
		global $modx;
		
		$this->enable_error_exceptions();
		
		if (!$modx->db->isConnected){
			$modx->db->connect($this->get_host(), $this->get_dbase(), $this->get_user(), $this->get_password(), $this->get_connection_method());
		}
		$this->set_current_connection($modx->db->conn);
		$this->set_connected($modx->db->isConnected);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::perform_disconnect()
	 */
	protected function perform_disconnect() {
		// Disconnect is handled by modx, not by ExFace, so no need to do anything here
	}
	
}
?>