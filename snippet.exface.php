<?php
global $exface, $modx;
$function = $function ? $function : 'draw';
$template_frameworks = array('5' => 'exface.JEasyUiTemplate', '10' => 'exface.JQueryMobileTemplate', '11' => 'exface.AdminLteTemplate');
$docId = $docId ? $docId : $modx->documentIdentifier;
if (!$content) $content = $modx->documentObject['content'];
if (strpos($content, '{') !== 0) {
	if ($function == "draw"){
		return $content;
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

$widget = $exface->ui()->get_widget(null, $docId);
if (!$widget) return $content;
$template_name = $template_frameworks[$modx->documentObject['template']];
$exface->ui()->set_base_template_alias($template_name);
$template = $exface->ui()->get_template();

switch ($function){
	case "headers": 
		$result = $template->process_request($docId, $widget->get_id(), 'exface.Core.ShowHeaders'); 
		break;
	case "draw": 
		$result = $template->process_request($docId, $widget->get_id(), 'exface.Core.ShowWidget');
		$exface->stop(); 
		break;
}

return $result;
?>