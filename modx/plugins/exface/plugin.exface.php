<?php
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\UserFactory;
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsonEditorTrait;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Facades\DocsFacade;

/**
 * ExFace
 *
 * UXON WYSIWYG editor, page alias generation, user-sync, etc.
 *
 * @category    plugin
 * @version     0.28.6
 * @license     http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @package     modx
 * @author      Stefan Leupold, Andrej Kabachnik
 * @internal    @properties &enable_user_sync=Enable user sync;list;true,false;true &enable_uxon_editor=Make UXON editor the default WYSIWYG editor;list;true,false;true &uxon_editor_height=Height of UXON editor (in px);int;600
 * @internal    @events OnWebDeleteUser,OnWebSaveUser,OnManagerDeleteUser,OnManagerSaveUser,OnDocDuplicate,OnDocFormSave,OnStripAlias,OnBeforeUserFormSave,OnBeforeWUsrFormSave,OnRichTextEditorRegister,OnRichTextEditorInit
 * @internal    @modx_category ExFace
 * @internal    @installset base, sample
 */

const TV_APP_UID_NAME = 'ExfacePageAppAlias';

const TV_REPLACE_ALIAS_NAME = 'ExfacePageReplaceAlias';

const TV_UID_NAME = 'ExfacePageUID';

const TV_DO_UPDATE_NAME = 'ExfacePageDoUpdate';

const TV_DEFAULT_MENU_POSITION_NAME = 'ExfacePageDefaultParentAlias';

$eventName = $modx->event->name;

$vendorPath = MODX_BASE_PATH . 'exface' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
require_once $vendorPath . 'exface' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'CommonLogic' . DIRECTORY_SEPARATOR . 'Workbench.php';
require_once $vendorPath . 'autoload.php';

global $exface;
if (! isset($exface)) {
    try {
        $exface = new Workbench();
        // Start the workbench to make the model and CMS accessible
        $exface->start();
    } catch (Throwable $e) {
        generateError($e, 'Error loading workbench');
        return;
    }
}

if (! function_exists('generateError')) {
    function generateError(Throwable $e, string $message) {
        global $exface, $modx;
        if ($e instanceof ExceptionInterface){
            $log_hint = ' (see log ID ' . $e->getId() . ')';
        }
        $modx->event->alert($message . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ' at line ' . $e->getLine() . $log_hint);
        // Unbedingt als letztes Loggen, sonst werden die Modx-Meldungen nicht angezeigt.
        // Zusammenhang mit TODO unten?
        $exface->getLogger()->logException($e);
    }
}

// Start: angepasst aus plugin.transalias.php
require_once MODX_BASE_PATH . 'assets/plugins/transalias/transalias.class.php';
try {
    $trans = new TransAlias($modx);
    $trans->loadTable('common', 'No');
} catch (Throwable $e) {
    generateError($e, 'Error loading transalias');
    return;
}
// Ende: angepasst aus plugin.transalias.php

switch ($eventName) {
    case "OnStripAlias":
        try {
            $modx->event->output($trans->stripAlias($alias, 'lowercase alphanumeric', 'dash'));
        } catch (Throwable $e) {
            generateError($e, 'Error stripping alias using transalias');
            return;
        }
        
        break;
    
    case 'OnDocFormSave':
        try {
            require_once ($modx->config['base_path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modResource.php');
            $savedPage = new modResource($modx);
            $savedPage->edit($id);
            if (! $savedPage->get(TV_APP_UID_NAME) || ! $savedPage->get(TV_DEFAULT_MENU_POSITION_NAME) && $savedPage->get('parent')) {
                $parentPage = new modResource($modx);
                $parentPage->edit($savedPage->get('parent'));
            }
            
            // Hat eine neu erzeugte Seite keine App wird versucht die App zu vererben.
            if ($mode === 'new' && ! $savedPage->get(TV_APP_UID_NAME) && ! is_null($parentPage) && $parentPage->get(TV_APP_UID_NAME)) {
                $savedPage->set(TV_APP_UID_NAME, $parentPage->get(TV_APP_UID_NAME));
            }
            
            // UID setzen
            if (! $savedPage->get(TV_UID_NAME)) {
                $savedPage->set(TV_UID_NAME, UiPage::generateUid());
            }
            
            // Default Menu Position setzen
            if (! $savedPage->get(TV_DEFAULT_MENU_POSITION_NAME) && ! is_null($parentPage)) {
                $savedPage->set(TV_DEFAULT_MENU_POSITION_NAME, $parentPage->get('alias') . ':' . $savedPage->get('menuindex'));
            }
            
            // Generate an app namespace prefix for the alias if it does not have one yet. If it does,
            // leave it - regardless of whether it corresponds to the current app - because the user
            // will not expect it to change silently!
            if (UiPageSelector::getAppAliasFromNamespace($savedPage->get('alias')) === false) {
                if ($savedPage->get(TV_APP_UID_NAME)) {
                    $appAlias = strtolower($exface->getApp($savedPage->get(TV_APP_UID_NAME))->getAliasWithNamespace());
                    $savedPage->set('alias', strtolower($appAlias) . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $savedPage->get('alias'));
                }
            }
            
            // Speichern der aktualisierten Seite, keine Events feuern.
            $savedPage->save();
            
            // Warnung beim Speichern ausgeben
            $appUid = $savedPage->get(TV_APP_UID_NAME);
            $warnOnSavePageInApp = $exface->getApp('exface.ModxCmsConnector')->getConfig()->getOption('MODX.WARNING.ON_SAVE_PAGE_IN_APP');
            if ($appUid && $warnOnSavePageInApp) {
                // Wird eine Seite mit gesetztem App-Alias gespeichert, so wird eine Warnung
                // angezeigt, dass die Aenderungen beim naechsten Update ueberschrieben werden
                // koennten.
                
                // TODO Es gibt hier ein Problem mit der Anzeige der Meldung wenn sie uebersetzt
                // wird. Wird z.B. $exface->getLogger()->... oder $exface->getApp(...)->getTranslator()->translate()
                // aufgerufen, wird der SessionContextScope instanziiert und die aktive Session
                // geschlossen. Selbst wenn sie direkt danach wieder geoeffnet wird, wird die
                // Meldung nicht angezeigt. Problem mit Sessions und globalen Variablen?
                // (global $SystemAlertMsgQueque)?
                
                // $modx->event->alert($exface->getApp('exface.ModxCmsConnector')->getTranslator()->translate('WARNING_SAVE_DIALOG_WITH_APP_ALIAS'));
                $modx->event->alert('You made changes to a dialog, which may be overwritten during the next update.');
            }
        } catch (Throwable $e) {
            generateError($e, 'Error updating saved page');
            return;
        }
        
        break;
    
    case 'OnDocDuplicate':
        // Die duplizierte Seite hat keinen Alias und u.U. (wenn die UID-TV durch mmrules readonly ist)
        // auch keine UID, kann daher durch die herkoemmlichen Methoden nicht so einfach geladen werden.
        // Daher werden hier erst einmal UID und Alias auf anderem Weg vergeben.
        try {
            require_once ($modx->config['base_path'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'MODxAPI' . DIRECTORY_SEPARATOR . 'modResource.php');
            $resource = new \modResource($modx);
            $resource->edit($new_id);
            
            // Hat die duplizierte Seite keinen Alias wird einer erzeugt.
            if (! $resource->get('alias')) {
                if ($resource->get('pagetitle')) {
                    $alias = $trans->stripAlias($resource->get('pagetitle'), 'lowercase alphanumeric', 'dash');
                } else {
                    $alias = UiPage::generateAlias('');
                }
                
                // Generate an app namespace prefix for the alias if it does not have one yet. If it does,
                // leave it - regardless of whether it corresponds to the current app - because the user
                // will not expect it to change silently!
                if (UiPageSelector::getAppAliasFromNamespace($alias) === false) {
                    if ($appUid = $resource->get(TV_APP_UID_NAME)) {
                        $appAlias = $exface->getApp($appUid)->getAliasWithNamespace();
                        $resource->set('alias', strtolower($appAlias) . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $alias);
                    }
                }
            }
            
            // Die duplizierte Seite bekommt eine neue UID.
            $resource->set(TV_UID_NAME, UiPage::generateUid());
            
            // Default Menu Position setzen.
            if ($parent = $resource->get('parent')) {
                $parentSelector = SelectorFactory::createPageSelector($exface, $parent);
                $resource->set(TV_DEFAULT_MENU_POSITION_NAME, $exface->getCMS()->getPage($parentSelector)->getAliasWithNamespace() . ':' . $resource->get('menuindex'));
            }
            
            // Speichern der aktualisierten Seite, keine Events feuern.
            $resource->save();
        } catch (Throwable $e) {
            generateError($e, 'Error updating duplicated page');
            return;
        }
        
        break;
    
    // Verhindert, dass Modx Web- und Manager-Nutzer mit dem gleichen Nutzernamen existieren,
    // wenn ein Modx Nutzer im Backend gespeichert wird.
    case 'OnBeforeUserFormSave':
    case 'OnBeforeWUsrFormSave':
        if ($enable_user_sync === "false") {
            break;
        }
        // Kontrolle auf existierenden Web/Mgr-Nutzernamen entsprechend den Kontrollen in
        // save_user.processor.php und save_web_user.processor.php auf existierenden
        // Mgr/Web-Nutzernamen.
        try {
            $username = ! empty($_POST['newusername']) ? trim($_POST['newusername']) : "New User";
            if (($eventName == 'OnBeforeUserFormSave' && $exface->getCMS()->isModxWebUser($username)) || ($eventName == 'OnBeforeWUsrFormSave' && $exface->getCMS()->isModxMgrUser($username))) {
                // Entsperren des Plugins bevor umgeleitet wird.
                $exface->getCMS()->unlockPlugin($modx->event->activePlugin);
                
                $mode = $_POST['mode'];
                $editMode = $eventName == 'OnBeforeUserFormSave' ? '12' : '88';
                $id = intval($_POST['id']);
                $modx->manager->saveFormValues($mode);
                $modx->webAlertAndQuit('User name is already in use!', "index.php?a={$mode}" . ($mode == $editMode ? "&id={$id}" : ''));
            }
        } catch (Throwable $e) {
            generateError($e, 'Error checking for duplicate modx user');
            return;
        }
        
        break;
    
    // Synchronisiert einen Modx-Nutzer mit einem Exface-Nutzer
    case 'OnManagerSaveUser':
    case 'OnWebSaveUser':
        if ($enable_user_sync === "false") {
            break;
        }
        try {
            $modelLoader = $exface->model()->getModelLoader();
            
            // Vor- und Nachname aus dem vollen Namen ermitteln.
            if (($seppos = strrpos($userfullname, ' ')) !== false) {
                $firstname = substr($userfullname, 0, $seppos);
                $lastname = substr($userfullname, $seppos + 1);
            } else {
                $firstname = '';
                $lastname = $userfullname;
            }
            
            // Locale ermitteln
            $langLocalMap = $exface->getApp('exface.ModxCmsConnector')->getConfig()->getOption('USERS.LANGUAGE_LOCALE_MAPPING')->toArray();
            if (($lang = $_POST['manager_language']) && array_key_exists($lang, $langLocalMap)) {
                $locale = $langLocalMap[$lang];
            }
            
            // Wird der Nutzer gerade umbenannt, enthaelt $oldusername den alten, $username den
            // neuen, sonst ist $oldusername leer.
            if ($oldusername) {
                $exfUserOld = UserFactory::createFromModel($exface, $oldusername);
            }
            $exfUser = UserFactory::createFromModel($exface, $username);
            if ($exfUserOld && $exfUserOld->hasModel()) {
                if ($exfUser->hasModel()) {
                    // Der Nutzer wird gerade umbenannt. Es existiert ein Exface-Nutzer mit dem
                    // alten Namen. Es existiert ebenso ein Exface-Nutzer mit dem neuen Namen.
                    // Der Nutzer mit dem alten Namen wird geloescht. Der Nutzer mit dem neuen
                    // Namen wird aktualisiert.
                    $modelLoader->deleteUser($exfUserOld);
                    
                    $exfUser->setFirstName($firstname);
                    $exfUser->setLastName($lastname);
                    $exfUser->setLocale($locale);
                    $exfUser->setEmail($useremail);
                    $modelLoader->updateUser($exfUser);
                } else {
                    // Der Nutzer wird gerade umbenannt. Es existiert ein Exface-Nutzer mit dem
                    // alten Namen. Es existiert kein Exface-Nutzer mit dem neuen Namen. Der
                    // Nutzer mit dem alten Namen wird aktualisiert.
                    $exfUserOld->setUsername($username);
                    $exfUserOld->setFirstName($firstname);
                    $exfUserOld->setLastName($lastname);
                    $exfUserOld->setLocale($locale);
                    $exfUserOld->setEmail($useremail);
                    $modelLoader->updateUser($exfUserOld);
                }
            } else {
                if ($exfUser->hasModel()) {
                    // Der Nutzer wird nicht umbenannt. Es existiert ein Exface-Nutzer mit dem
                    // Namen, welcher aktualisert wird.
                    $exfUser->setFirstName($firstname);
                    $exfUser->setLastName($lastname);
                    $exfUser->setLocale($locale);
                    $exfUser->setEmail($useremail);
                    $modelLoader->updateUser($exfUser);
                } else {
                    // Der Nutzer wird nicht umbenannt. Es existiert kein Exface-Nutzer mit dem
                    // Namen, daher wird ein neuer Exface-Nutzer angelegt.
                    $modelLoader->createUser(UserFactory::create($exface, $username, $firstname, $lastname, $locale, $useremail));
                }
            }
        } catch (Throwable $e) {
            generateError($e, 'Error creating or updating exface user');
            return;
        }
        
        break;
    
    // Wird ein Modx-Nutzer geloescht wird auch der entsprechende Exface-Nutzer geloescht.
    case 'OnManagerDeleteUser':
    case 'OnWebDeleteUser':
        if ($enable_user_sync === "false") {
            break;
        }
        try {
            $exfUser = UserFactory::createFromModel($exface, $username);
            if ($exfUser->hasModel()) {
                // Es existiert ein Exface-Nutzer mit dem Namen, welcher geloescht wird.
                $exface->model()->getModelLoader()->deleteUser($exfUser);
            }
        } catch (Throwable $e) {
            generateError($e, 'Error deleting exface user');
            return;
        }
        
        break;
        
    // UXON Editor WYSIWYG plugin
    case "OnRichTextEditorRegister":
        if ($enable_uxon_editor === "false") {
            break;
        }
		
        $modx->event->output("UXONeditor");
        break;
        
    case "OnRichTextEditorInit":
        if ($enable_uxon_editor === "false") {
            break;
        }
        
        if($editor!=='UXONeditor') return;
        $base_path = MODX_BASE_URL;
       
        $autosuggestUrl = $base_path . 'exface/api/jeasyui';

        $workbench = $exface->getCMS()->getWorkbench();
      
        /* @var \exface\Core\Facades\DocsFacade $docsFacadeClass */
        $docsFacade = FacadeFactory::createFromAnything(DocsFacade::class, $workbench);
        $uxonEditorHelpUrl = $docsFacade->buildUrlToFacade() . '/exface/Core/Docs/Creating_UIs/UXON/Introduction_to_the_UXON_editor.md';
        
        $default_height = is_int($uxon_editor_height) ? $uxon_editor_height : 600;
        
        $uxonEditorFuncPrefix = 'jsonEditor';
        $uxonEditorId = 'UXONeditor';
        $addHelpButtonFunction = JsonEditorTrait::buildJsFunctionNameAddHelpButton($uxonEditorFuncPrefix);
        $addPresetHint = JsonEditorTrait::buildJsFunctionNameAddPresetHint($uxonEditorFuncPrefix);
        $onBlurFunction = JsonEditorTrait::buildJsFunctionNameOnBlur($uxonEditorFuncPrefix);
        
        $uxonEditorCss = JsonEditorTrait::buildCssModalStyles($uxonEditorId);
        $uxonEditorOptions = JsonEditorTrait::buildJsUxonEditorOptions('widget', $uxonEditorFuncPrefix, $workbench);
        $uxonEditorFunctions = JsonEditorTrait::buildJsUxonEditorFunctions(
            $uxonEditorFuncPrefix, 
            'widget', 
            'null', 
            'null', 
            $autosuggestUrl,
            $workbench,
            $uxonEditorId
        );
        
        $richIds=implode('","',$elements);
        $output .= <<< OUT

	<!-- JSON editor -->
	<link rel="stylesheet" type="text/css" href="{$base_path}exface/vendor/npm-asset/jsoneditor/dist/jsoneditor.min.css">
    <script type="text/javascript" src="{$base_path}exface/vendor/npm-asset/jsoneditor/dist/jsoneditor.min.js"></script>
    <script type="text/javascript" src="{$base_path}exface/vendor/npm-asset/picomodal/src/picoModal.js"></script>
    <link rel="stylesheet" type= "text/css" href="$base_path}exface/vendor/npm-asset/mobius1-selectr/src/selectr.css">
    <script type="text/javascript" src="{$base_path}exface/vendor/npm-asset/mobius1-selectr/src/selectr.js"></script>
    <style type="text/css">{$uxonEditorCss}</style>
    <!-- Style fixes for MODx -->
	<style>
	    .jsoneditor-contextmenu ul.menu {width: 126px !important}
        .jsoneditor-modal > label {display: block; width: 100%; height: 100%; margin: 0;}
        .jsoneditor-modal.jsoneditor-modal-nopadding iframe {width: 100% !important; }
        .jsoneditor-modal.uxoneditor-modal {position: fixed !important;}
        .jsoneditor-modal-overlay.pico-overlay {position: fixed !important;}
</style>
	
 
	<!-- Plugin initialization -->
    <script type="text/javascript">
        (function(){
            var $ = window.\$j;
        	var richIds = ["{$richIds}"];
        	var jsonEditors = {};
        	for (var richField=0;richField<richIds.length;richField++){
        			var richId = richIds[richField];
        			var el = document.getElementById(richId);
        			var newDiv = document.createElement('div');
        			newDiv.setAttribute('id', 'jsonEditor'+richId);
        			newDiv.style.height = '{$default_height}'+'px';
        			
        			var options = {
        				onError: function (err) {
                            try{
        				        alert(err.toString());
                            } catch{
                                console.error('Alert from UXON editor: ', err);
                            }
        				},
                        mode: "tree",
                        modes: ['code', 'tree'],
                        {$uxonEditorOptions}
                    };
        			var editor = new JSONEditor(newDiv, options);
        			editor.setText(el.innerHTML || "{}");
        		    editor.expandAll();
                    setTimeout(function() {
                        {$addHelpButtonFunction}(
                            window.\$j,
                            'jsonEditor'+richId,
                            "{$uxonEditorHelpUrl}",
                            "Help" 
                        );
                    }, 0);
                    {$addPresetHint}();
                    
        			jsonEditors[richId] = editor;
        
        			el.parentNode.insertBefore(newDiv,el.nextSibling);
        			el.style.display='none';
        			
        			form = el.form;
        			if (form.attachEvent) {
        				form.attachEvent("submit", jsonEditorSave);
        			} else {
        				form.addEventListener("submit", jsonEditorSave);
        			}
        
        
        			
        	}

            function jsonEditorSave(e){
        		for (key in jsonEditors){
        			var el = document.getElementById(key);
        			if (jsonEditors[key].getText() !== '' && jsonEditors[key].getText() !== '{}' && jsonEditors[key].getText() != '{}'){
        				el.innerHTML = jsonEditors[key].getText();
        			}
        		}
        	}
        
            {$uxonEditorFunctions}
        
            window.\$j(function() {
                for (var e in jsonEditors) {
                	var editor = jsonEditors[e];
                    window.\$j(document).on('blur', '#jsonEditor'+e+' div.jsoneditor-field[contenteditable="true"]', {jsonEditor: editor}, {$onBlurFunction});
                }
            });

        })();
	</script>
				
OUT;
        $modx->event->output($output);
        break;
        
    default:
        return; // stop here - this is very important.
        break;
}