<?php namespace exface\ModxCmsConnector\DataConnectors;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\SqlDataConnector\Interfaces\SqlDataConnectorInterface;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataConnectionError;
use exface\SqlDataConnector\SqlDataQuery;

class ModxDb extends AbstractDataConnector implements SqlDataConnectorInterface {

	var $conn;
	var $isConnected;
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::perform_connect()
	 */
	protected function perform_connect() {
		global $modx;
		
		if (!$modx->db->isConnected){
			$modx->db->connect($this->get_config_array()['host'], $this->get_config_array()['dbase'], $this->get_config_array()['user'], $this->get_config_array()['pass'], $this->get_config_array()['connection_method']);
		}
		
		$this->isConnected = $modx->db->isConnected;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::perform_disconnect()
	 */
	protected function perform_disconnect() {
		// Disconnect is handled by modx, not by ExFace, so no need to do anything here
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::perform_query()
	 * @param SqlDataConnectorInterface $query
	 */
	protected function perform_query(DataQueryInterface $query) {
		global $modx;
		
		if (!($query instanceof SqlDataQuery)){
			throw new DataConnectionError('The MODx DB data connector expects an SqlDataQuery as input: "' . get_class($query) . '" given instead!');
		}
		
		$result = $modx->db->query($query->get_sql());
		$query->set_result_array($this->make_array($result));
		return $query;
	}

	function get_insert_id($conn=NULL) {
		global $modx;
		return $modx->db->getInsertId($conn);
	}

	function get_affected_rows_count($conn=NULL) {
		global $modx;
		return $modx->db->getAffectedRows($conn);
	}
	
	function get_last_error($conn=NULL) {
		global $modx;
		return $modx->db->getLastError($conn);
	}
	
	function make_array($rs){
		global $modx;
		$array = $modx->db->makeArray($rs);
		if (!is_array($array)){
			$array = array();
		}
		return $array; 
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::transaction_start()
	 */
	public function transaction_start(){
		return true;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::transaction_commit()
	 */
	public function transaction_commit(){
		return true;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::transaction_rollback()
	 */
	public function transaction_rollback(){
		return false;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractDataConnector::transaction_is_started()
	 */
	public function transaction_is_started(){
		return true;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\SqlDataConnector\Interfaces\SqlDataConnectorInterface::run_sql()
	 */
	public function run_sql($string){
		$query = new SqlDataQuery();
		$query->set_sql($string);
		return $this->query($query);
	}
}
?>