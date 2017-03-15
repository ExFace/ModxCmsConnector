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
 * &action - Qualified alias of the ExFace action, that is to be performed. Default: exface.Core.ShowWidget
 * &docId - MODx document id to call the action for. Default: the id of the current document, i.e. [*id*]
 * &fallback_field - The key of the attribute of $modx->documentObject to be displayed if the content is not valid UXON
 */
global $exface, $exface_cache, $modx;

$action = $action ? $action : 'exface.Core.ShowWidget';
$template_mapping = array('5' => 'exface.JEasyUiTemplate', '10' => 'exface.JQueryMobileTemplate', '11' => 'exface.AdminLteTemplate');
$docId = $docId ? $docId : $modx->documentIdentifier;
$fallback_field = $fallback_field ? $fallback_field : '';
$error = null;

if (!$content) $content = $modx->documentObject['content'];
if (substr(trim($content), 0, 1) !== '{') {
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

$template_name = $template_mapping[$modx->documentObject['template']];
$exface->ui()->set_base_template_alias($template_name);
$template = $exface->ui()->get_template();

if ($cache = $exface_cache[$docId][$action]){
	return $cache;
}

switch ($action){
	case "exface.Core.ShowHeaders":
		try {
			$result = $template->process_request($docId, null, 'exface.Core.ShowHeaders', true); 
		} catch (\exface\Core\Interfaces\Exceptions\ErrorExceptionInterface $e){
			$ui = $exface->ui();
			$page = \exface\Core\Factories\UiPageFactory::create($ui, 0);
			try {
				$result = $template->draw_headers($e->create_widget($page));
			} catch (\Exception $e){
				// If the exception widget cannot be rendered either, output no headers in order not to break them.	
			}
		} 
		break;
	default: 
		$result = $template->process_request($docId, null, $action);
		break;
}

$exface_cache[$docId][$action] = $result;

return $result;
