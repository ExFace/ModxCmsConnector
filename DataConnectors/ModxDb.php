<?php namespace exface\ModxCmsConnector\DataConnectors;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\SqlDataConnector\Interfaces\SqlDataConnectorInterface;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataConnectionError;
use exface\SqlDataConnector\SqlDataQuery;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\SqlDataConnector\DataConnectors\AbstractSqlConnector;

class ModxDb extends AbstractSqlConnector {

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
	protected function perform_query_sql(SqlDataQuery $query) {
		global $modx;		
		$result = $modx->db->query($query->get_sql());
		if ($error = $modx->db->getLastError($result)){
			throw new DataQueryFailedError($query, $error);
		}
		$query->set_result_resource($result);
		return $query;
	}
	
	public function get_insert_id(SqlDataQuery $query) {
		global $modx;
		return $modx->db->getInsertId($query->get_result_resource());
	}
	
	public function get_affected_rows_count(SqlDataQuery $query) {
		global $modx;
		return $modx->db->getAffectedRows($query->get_result_resource());
	}
		
	public function make_array(SqlDataQuery $query){
		global $modx;
		$array = $modx->db->makeArray($query->get_result_resource());
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
	
	public function free_result(SqlDataQuery $query){
		return;
	}
}
?>