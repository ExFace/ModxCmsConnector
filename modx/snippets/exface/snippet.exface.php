<?php
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use exface\Core\Templates\AbstractAjaxTemplate\AbstractAjaxTemplate;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\RequestIdNegotiator;

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
$action = $action ? $action : 'exface.Core.ShowWidget';
$docAlias = $docAlias ? $docAlias : $modx->documentObject['alias'];
$fallback_field = $fallback_field ? $fallback_field : '';
$file = $file ? $file : null;

if (! $content)
    $content = $modx->documentObject['content'];
if (strcasecmp($action, 'exface.Core.ShowWidget') === 0 && substr(trim($content), 0, 1) !== '{') {
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
if (is_null($modx->request)) {
    $modx->request = \GuzzleHttp\Psr7\ServerRequest::fromGlobals();
    $modx->request = $modx->request->withAttribute($template_instance->getRequestAttributeForPage(), $docAlias);
    // Add a request id if not set already. This makes sure, different actions within the same physical request
    // have the same request id.
    if (! $modx->request->hasHeader(RequestIdNegotiator::X_REQUEST_ID)) {
        $modx->request = (new RequestIdNegotiator)->addRequestId($modx->request);
    }
}
$request = $modx->request = $modx->request->withAttribute($template_instance->getRequestAttributeForAction(), $action);

switch (strtolower($action)) {
    case "exface.modxcmsconnector.showtemplate":
        $result = file_get_contents($exface->filemanager()->getPathToBaseFolder() . DIRECTORY_SEPARATOR . $file);
        break;
    case "exface.modxcmsconnector.getlanguagecode":
        $locale = $exface->context()->getScopeSession()->getSessionLocale();
        $result = explode('_', $locale)[0];
        break;
    case 'exface.core.showwidget':
        if ($template_instance instanceof AbstractAjaxTemplate) {
            $response = $template_instance->handle($request->withAttribute($template_instance->getRequestAttributeForRenderingMode(), AbstractAjaxTemplate::MODE_BODY), 'ShowWidget');
        } else {
            $response = $template_instance->handle($request);
        }
        break;
    case "exface.core.showheaders":
        if ($template_instance instanceof AbstractAjaxTemplate) {
            $request = $request
                ->withAttribute($template_instance->getRequestAttributeForRenderingMode(), AbstractAjaxTemplate::MODE_HEAD)
                ->withAttribute($template_instance->getRequestAttributeForAction(), 'exface.Core.ShowWidget');
            $response = $template_instance->handle($request, 'ShowWidget');
            
            // Mute error messages in headers as they will braek the page.
            if ($response->getStatusCode() != 200) {
                $result = '';
            }
        }
        break;
    default:
        $response = $template_instance->handle($request);
}

if (! isset($result) && isset($response)) {
    $result = (string) $response->getBody();
}

// Restore session
session_id($session_id);
session_start();

return $result;
