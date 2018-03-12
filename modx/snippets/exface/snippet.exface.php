<?php
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate;
use exface\Core\Templates\AbstractAjaxTemplate\AbstractAjaxTemplate;

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
 * &docAlias - MODx document alias to call the action for. Default: the alias of the current document, i.e. [*alias*]
 * &fallback_field - The key of the attribute of $modx->documentObject to be displayed if the content is not valid UXON
 * &file - path to file to use for contents (relative to the exface installation folder)
 */
if (! function_exists('exf_get_default_template')) {

    function exf_get_default_template()
    {
        // TODO get the template from the config of the connector app
        return 'exface.JEasyUiTemplate';
    }
}

global $exface, $modx;

$template = $template ? $template : exf_get_default_template();
$action = $action ? strtolower($action) : 'exface.core.showwidget';
$docAlias = $docAlias ? $docAlias : $modx->documentObject['alias'];
$fallback_field = $fallback_field ? $fallback_field : '';
$file = $file ? $file : null;

if (! $content)
    $content = $modx->documentObject['content'];
if ($action === 'exface.core.showwidget' && substr(trim($content), 0, 1) !== '{') {
    if ($fallback_field) {
        return $modx->documentObject[$fallback_field];
    } else {
        return;
    }
}

unset($_REQUEST['q']); // remove the q parameter, that is used by modx internally
                       // Remove URL-params added when using quickmanager
if ($_REQUEST['quickmanagerclose']) {
    unset($_REQUEST['quickmanagerclose']);
    unset($_REQUEST['id']);
}

// Backup and close session
$session_id = session_id();
session_write_close();

// load exface
if (! $exface) {
    require_once ($modx->config['base_path'] . 'exface/vendor/exface/Core/CommonLogic/Workbench.php');
    $exface = new \exface\Core\CommonLogic\Workbench();
    $exface->start();
}

$template_instance = $exface->ui()->getTemplate($template);

switch ($action) {
    case "exface.core.showheaders":
        try {
            $request = \GuzzleHttp\Psr7\ServerRequest::fromGlobals();
            $request = $request->withAttribute($template_instance->getRequestAttributeForRenderingMode(), AbstractAjaxTemplate::MODE_HEAD);
            $request = $request->withAttribute($template_instance->getRequestAttributeForPage(), $docAlias);
            $request = $request->withAttribute($template_instance->getRequestAttributeForAction(), 'exface.Core.ShowWidget');
            $response = $template_instance->handle($request, null, null, true);
        } catch (\exface\Core\Interfaces\Exceptions\ErrorExceptionInterface $e) {
            $exface->getLogger()->logException($e);
            $ui = $exface->ui();
            $page = \exface\Core\Factories\UiPageFactory::createEmpty($ui);
            try {
                $result = $template_instance->buildHtmlHead($e->createWidget($page));
            } catch (\Exception $ee) {
                // If the exception widget cannot be rendered either, output no headers in order not to break them.
                $exface->getLogger()->logException($ee);
            }
        }
        break;
    case 'exface.core.showwidget':
        $request = \GuzzleHttp\Psr7\ServerRequest::fromGlobals();
        $request = $request->withAttribute($template_instance->getRequestAttributeForRenderingMode(), AbstractAjaxTemplate::MODE_BODY);
        $request = $request->withAttribute($template_instance->getRequestAttributeForPage(), $docAlias);
        $request = $request->withAttribute($template_instance->getRequestAttributeForAction(), $action);
        $response = $template_instance->handle($request);
        break;
    case "exface.modxcmsconnector.showtemplate":
        $result = file_get_contents($exface->filemanager()->getPathToBaseFolder() . DIRECTORY_SEPARATOR . $file);
        break;
    case "exface.modxcmsconnector.getlanguagecode":
        $locale = $exface->context()->getScopeSession()->getSessionLocale();
        $result = explode('_', $locale)[0];
        break;
    default:
        $response = $template_instance->handle(\GuzzleHttp\Psr7\ServerRequest::fromGlobals(), $docAlias, $action);
        break;
}

if (! isset($result) && isset($response)) {
    $result = (string) $response->getBody();
}

// Restore session
session_id($session_id);
session_start();

return $result;
