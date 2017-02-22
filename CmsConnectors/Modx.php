<?php namespace exface\ModxCmsConnector\CmsConnectors;

use exface\Core\Interfaces\CmsConnectorInterface;
use exface\Core\CommonLogic\Workbench;
use exface\ModxCmsConnector\ModxCmsConnectorApp;
use exface\Core\Factories\UiPageFactory;

class Modx implements CmsConnectorInterface {
	private $user_name = null;
	private $user_settings = null;
	private $user_locale = null;
	private $workbench = null;
	
	/**
	 * @deprecated use CmsConnectorFactory instead
	 * @param Workbench $exface
	 */
	public function __construct(Workbench $exface){
		$this->workbench = $exface;
		global $modx;
		if (!$modx){
			require_once $this->get_app()->get_modx_ajax_index_path();
		}
		$this->user_name = $modx->getLoginUserName('mgr') ? $modx->getLoginUserName('mgr') : $modx->getLoginUserName('web');
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\CmsConnectorInterface::get_page_id()
	 */
	public function get_page_id(){
		global $modx;
		return $modx->documentIdentifier;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\CmsConnectorInterface::get_page_contents()
	 */
	public function get_page_contents($doc_id){
		global $modx;
	
		$q = $modx->db->select('content', $modx->getFullTableName('site_content'), 'id = ' . intval($doc_id));
		$source = $modx->db->getValue($q);
		return $source;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\CMSInterface::create_link_internal()
	 */
	public function create_link_internal($doc_id, $url_params=''){
		global $modx;
		return $modx->makeUrl($doc_id, null, $url_params, 'full');
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\CMSInterface::create_link_external()
	 */
	public function create_link_external($url){
		return $url;
	}
	
	/**
	 * For MODx no request params must be stripped off here, since they all get handled in the snippet.
	 * This way they are only removed on regular requests - not on AJAX.
	 * @see \exface\Core\Interfaces\CMSInterface::remove_system_request_params()
	 */
	public function remove_system_request_params(array $param_array){
		return $param_array;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\CmsConnectorInterface::get_paget_title()
	 */
	public function get_page_title($resource_id = null){
		global $modx;
		if (is_null($resource_id) || $resource_id == $modx->documentIdentifier){
			return $modx->documentObject['pagetitle'];
		} else {
			$doc = $modx->getDocument($resource_id, 'pagetitle');
			return $doc['pagetitle'];
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\CMSInterface::get_user_name()
	 */
	public function get_user_name(){
		return $this->user_name;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\CMSInterface::get_user_locale()
	 */
	public function get_user_locale(){
		if (is_null($this->user_locale)){
			switch ($this->get_user_settings('manager_language')){
				case 'bulagrian': $loc = 'bg_BG'; break;
				case 'chinese': $loc = 'zh_CN'; break;
				case 'german': $loc = 'de_DE'; break;
				default: $loc = 'en_US';
			}
			$this->user_locale = $loc;
		}
		
		return $this->user_locale;
	}
	
	protected function get_user_settings($setting_name=null){
		if (is_null($this->user_settings)){
			global $modx;
			// Create the settings array an populate it with defaults
			$this->user_settings = array(
					'manager_language' => $modx->config['manager_language']
			);
			// Overload with user specific values if a user is logged on
			if ($modx->getLoginUserID()){
				$rs = $modx->db->select('setting_name, setting_value', $modx->getFullTableName('user_settings'), "user=".$modx->getLoginUserID()." AND setting_name IN ('" . implode("','", array_keys($this->user_settings)) . "')");
				while ($row = $modx->db->getRow($rs)) {
					$this->user_settings[$row['setting_name']] = $row['setting_value'];
				}
			}
		}
		if (is_null($setting_name)){
			return $this->user_settings;
		} else {
			return $this->user_settings[$setting_name];
		}
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
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\CMSInterface::sanitize_output()
	 */
	public function sanitize_output($string){
		return str_replace(array('[[', '[!', '{{'), array('[ [', '[ !', '{ {'), $string);
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\CMSInterface::sanitize_error_output()
	 */
	public function sanitize_error_output($string){
		return $this->sanitize_output($string);
	}
	
	public function is_ui_page($content, $id = null){
		$content = trim($content);
		if (substr($content, 0, 1) !== '{' || substr($content, -1, 1) !== '}'){
			return false;
		}
		
		UiPageFactory::create_from_string($this->get_workbench()->ui(), (is_null($id) ? 0 : $id), $content);
		try {
			UiPageFactory::create_from_string($this->get_workbench()->ui(), (is_null($id) ? 0 : $id), $content);
		} catch (\Throwable $e){
			return false;
		}
		
		return true;
	}
}
?>