<?php
global $exface, $modx;
$function = $function ? $function : 'draw';
$template_frameworks = array('5' => 'exface.JEasyUiTemplate', '10' => 'exface.JQueryMobileTemplate', '11' => 'exface.AdminLteTemplate');
$docId = $docId ? $docId : $modx->documentIdentifier;
$error = null;

if (!$content) $content = $modx->documentObject['content'];
if (substr(trim($content), 0, 1) !== '{') {
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

$template_name = $template_frameworks[$modx->documentObject['template']];
$exface->ui()->set_base_template_alias($template_name);
$template = $exface->ui()->get_template();
switch ($function){
	case "headers":
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
	case "draw": 
		$result = $template->process_request($docId, null, 'exface.Core.ShowWidget'); 
		$exface->stop(); 
		break;
}

return $result;
?>