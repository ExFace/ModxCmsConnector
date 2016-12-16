<?php namespace exface\ModxCmsConnector\DataConnectors;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\SqlDataConnector\Interfaces\SqlDataConnectorInterface;
use exface\SqlDataConnector\SqlDataQuery;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\SqlDataConnector\DataConnectors\MySQL;

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
		
		if (!$modx->db->isConnected){
			$modx->db->connect($this->get_host(), $this->get_dbase(), $this->get_user(), $this->get_password(), $this->get_connection_method());
		}
		$this->set_current_connection($modx->db->conn);
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