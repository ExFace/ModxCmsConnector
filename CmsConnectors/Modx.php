<?php namespace exface\ModxCmsConnector\CmsConnectors;

use exface\Core\Interfaces\CmsConnectorInterface;
use exface\Core\CommonLogic\Workbench;
use exface\ModxCmsConnector\ModxCmsConnectorApp;

class Modx implements CmsConnectorInterface {
	private $user_name = null;
	private $workbench = null;
	
	function __construct(Workbench $exface){
		$this->workbench = $exface;
		global $modx;
		if (!$modx){
			require_once ($this->get_workbench()->get_installation_path() . DIRECTORY_SEPARATOR . $this->get_app()->get_config()->get_option('PATH_TO_MODX'));
		}
		$this->user_name = $modx->getLoginUserName('mgr') ? $modx->getLoginUserName('mgr') : $modx->getLoginUserName('web');
	}
	
	function get_page_id(){
		global $modx;
		return $modx->documentIdentifier;
	}
	
	function get_page($doc_id){
		global $modx;
	
		$q = $modx->db->select('content', $modx->getFullTableName('site_content'), 'id = ' . intval($doc_id));
		$source = $modx->db->getValue($q);
		return $source;
	}
	
	/**
	 * @see \exface\Core\Interfaces\CMSInterface::create_link_internal()
	 */
	function create_link_internal($doc_id, $url_params=''){
		global $modx;
		return $modx->makeUrl($doc_id, null, $url_params, 'full');
	}
	
	/**
	 * @see \exface\Core\Interfaces\CMSInterface::create_link_external()
	 */
	function create_link_external($url){
		return $url;
	}
	
	/**
	 * For MODx no request params must be stripped off here, since they all get handled in the snippet.
	 * This way they are only removed on regular requests - not on AJAX.
	 * @see \exface\Core\Interfaces\CMSInterface::remove_system_request_params()
	 */
	function remove_system_request_params(array $param_array){
		return $param_array;
	}
	
	function get_page_name($resource_id = null){
		global $modx;
		if (is_null($resource_id) || $resource_id == $modx->documentIdentifier){
			return $modx->documentObject['pagetitle'];
		} else {
			$doc = $modx->getDocument($resource_id, 'pagetitle');
			return $doc['pagetitle'];
		}
	}
	
	/**
	 * @see \exface\Core\Interfaces\CMSInterface::get_user_name()
	 */
	function get_user_name(){
		return $this->user_name;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::get_workbench()
	 */
	public function get_workbench(){
		return $this->workbench;
	}
	
	/**
	 * @return ModxCmsConnectorApp
	 */
	public function get_app(){
		return $this->get_workbench()->get_app('exface.ModxCmsConnector');
	}
}
?>