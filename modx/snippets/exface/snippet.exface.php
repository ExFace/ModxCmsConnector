<?php
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Facades\AbstractHttpFacade\Middleware\RequestIdNegotiator;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Factories\FacadeFactory;
use exface\ModxCmsConnector\CommonLogic\Security\ModxCmsAuthToken;
use exface\Core\Exceptions\Security\AuthenticationFailedError;

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
 * &facade - Qualified alias of the ExFace facade to be used: e.g. exface.AdminLTEFacade.AdminLTEFacade.
 * &action - Qualified alias of the ExFace action, that is to be performed. Default: exface.Core.ShowWidget
 * &docAlias - MODx document alias to call the action for. Default: the alias of the current document, i.e. [*alias*]
 * &fallback_field - The key of the attribute of $modx->documentObject to be displayed if the content is not valid UXON
 * &file - path to file to use for contents (relative to the exface installation folder)
 */
if (! function_exists('exf_get_default_facade')) {

    function exf_get_default_facade()
    {
        // TODO get the facade from the config of the connector app
        return 'exface.JEasyUIFacade.JEasyUIFacade';
    }
}

if (! function_exists('exf_get_request')) {
    
    function exf_get_request($modx, HttpFacadeInterface $facade, string $docAlias, string $actionAlias = null) : ServerRequestInterface
    {
        if (is_null($modx->request)) {
            $modx->request = \GuzzleHttp\Psr7\ServerRequest::fromGlobals()
            ->withAttribute($facade->getRequestAttributeForPage(), $docAlias);
            // Remove query parameters specific to MODx
            $queryParams = $modx->request->getQueryParams();
            unset($queryParams['q']);
            if ($queryParmas['quickmanagerclose']) {
                unset($queryParmas['quickmanagerclose']);
                unset($queryParmas['id']);
            }
            $modx->request = $modx->request->withQueryParams($queryParams);
            // Add a request id if not set already. This makes sure, different actions within the same physical request
            // have the same request id.
            if (! $modx->request->hasHeader(RequestIdNegotiator::X_REQUEST_ID)) {
                $modx->request = (new RequestIdNegotiator)->addRequestId($modx->request);
            }
        }
        return $modx->request->withAttribute($facade->getRequestAttributeForAction(), $actionAlias);
    }
}

global $exface, $modx;

$facade = $facade ? $facade : null;
$action = $action ? $action : 'exface.Core.ShowWidget';
$docAlias = $docAlias ? $docAlias : $modx->documentObject['alias'];
$fallback_field = $fallback_field ? $fallback_field : '';
$file = $file ? $file : null;

if (! $content) {
    $content = $modx->documentObject['content'];
}

if (strcasecmp($action, 'exface.Core.ShowWidget') === 0 && substr(trim($content), 0, 1) !== '{') {
    if ($fallback_field) {
        return $modx->documentObject[$fallback_field];
    } else {
        return;
    }
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

// Authenticate the user in the workbench
$cmsConn = $exface->getCMS();
if ($cmsConn->getUserName() !== $exface->getSecurity()->getAuthenticatedToken()->getUsername()) {
    $authToken = new ModxCmsAuthToken($cmsConn, $cmsConn->getUserName());
    try {
        $exface->getSecurity()->authenticate($authToken);
    } catch (AuthenticationFailedError $e) {
        $exface->getLogger()->logException($e, LoggerInterface::INFO);
    }
}
    
switch (strtolower($action)) {
    case "exface.modxcmsconnector.showtemplate":
        $path = $exface->filemanager()->getPathToBaseFolder() . DIRECTORY_SEPARATOR . $file;
        $result = file_get_contents($path);
        if ($result === false) {
            throw new RuntimeException('Cannot read template file "' . $path . '"!');
        }
        break;
    case "exface.modxcmsconnector.getlanguagecode":
        $locale = $exface->getContext()->getScopeSession()->getSessionLocale();
        $result = explode('_', $locale)[0];
        break;
    case 'exface.core.showwidget':
        $facade_instance = FacadeFactory::createFromString($facade, $exface);
        $request = exf_get_request($modx, $facade_instance, $docAlias, $action);
        if ($facade_instance instanceof AbstractAjaxFacade) {
            $response = $facade_instance->handle($request->withAttribute($facade_instance->getRequestAttributeForRenderingMode(), AbstractAjaxFacade::MODE_BODY), 'ShowWidget');
        } else {
            $response = $facade_instance->handle($request);
        }
        break;
    case "exface.core.showheaders":
        $facade_instance = FacadeFactory::createFromString($facade, $exface);
        $request = exf_get_request($modx, $facade_instance, $docAlias, $action);
        if ($facade_instance instanceof AbstractAjaxFacade) {
            $request = $request
                ->withAttribute($facade_instance->getRequestAttributeForRenderingMode(), AbstractAjaxFacade::MODE_HEAD)
                ->withAttribute($facade_instance->getRequestAttributeForAction(), 'exface.Core.ShowWidget');
            $response = $facade_instance->handle($request, 'ShowWidget');
        }
        break;
    default:
        $facade_instance = FacadeFactory::createFromString($facade, $exface);
        $response = $facade_instance->handle($request);
}

if (! isset($result) && isset($response)) {
    $result = (string) $response->getBody();
}

// Restore session
session_id($session_id);
session_start();

return $result;
