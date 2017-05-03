<?php
/**
 * ExFace
 *
 * Outputs the output of an ExFace action.
 *
 * @category 	snippet
 * @version 	0.6.2
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@properties
 * @internal	@modx_category ExFace
 * @internal    @installset base, sample
 */

/*
 * Parameters:
 * &tempalte - Qualified alias of the ExFace template to be used: e.g. exface.AdminLteTemplate.
 * &action - Qualified alias of the ExFace action, that is to be performed. Default: exface.Core.ShowWidget
 * &docId - MODx document id to call the action for. Default: the id of the current document, i.e. [*id*]
 * &fallback_field - The key of the attribute of $modx->documentObject to be displayed if the content is not valid UXON
 */

if (!function_exists('exf_get_default_template')) {
	function exf_get_default_template(){
		// TODO get the template from the config of the connector app
		return 'exface.JEasyUiTemplate';
	}
}

global $exface, $exface_cache, $modx;

$template = $template ? $template : exf_get_default_template();
$action = $action ? $action : 'exface.Core.ShowWidget';
$docId = $docId ? $docId : $modx->documentIdentifier;
$fallback_field = $fallback_field ? $fallback_field : '';
$file = $file ? $file : null;

if (!$content) $content = $modx->documentObject['content'];
if (strcasecmp($action, 'exface.Core.ShowWidget') === 0 && substr(trim($content), 0, 1) !== '{') {
	if ($fallback_field){
		return $modx->documentObject[$fallback_field];
	} else {
		return;
	}
}

unset($_REQUEST['q']); // remove the q parameter, that is used by modx internally
// Remove URL-params added when using quickmanager
if ($_REQUEST['quickmanagerclose']){
	unset($_REQUEST['quickmanagerclose']);
	unset($_REQUEST['id']);
}

// load exface
if (!$exface){
	include_once($modx->config['base_path'].'exface/exface.php');
}

$exface->ui()->set_base_template_alias($template);
$template_instance = $exface->ui()->get_template();

if ($cache = $exface_cache[$docId][$action]){
	return $cache;
}

switch ($action){
	case "exface.Core.ShowHeaders":
		try {
			$result = $template_instance->process_request($docId, null, 'exface.Core.ShowHeaders', true); 
		} catch (\exface\Core\Interfaces\Exceptions\ErrorExceptionInterface $e){
			$ui = $exface->ui();
			$page = \exface\Core\Factories\UiPageFactory::create($ui, 0);
			try {
				$result = $template_instance->draw_headers($e->create_widget($page));
			} catch (\Exception $e){
				// If the exception widget cannot be rendered either, output no headers in order not to break them.	
			}
		} 
		break;
	case "exface.ModxCmsConnector.ShowTemplate":
		$result = file_get_contents($exface->filemanager()->get_path_to_base_folder() . DIRECTORY_SEPARATOR . $file);
		break;
	default: 
		$result = $template_instance->process_request($docId, null, $action);
		break;
}

$exface_cache[$docId][$action] = $result;

return $result;
