<?php
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\UserFactory;
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Factories\SelectorFactory;

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
        
        $default_height = is_int($uxon_editor_height) ? $uxon_editor_height : 600;
        
        $richIds=implode('","',$elements);
        $output .= <<< OUT

	<!-- JSON editor -->
	<link rel="stylesheet" type="text/css" href="{$base_path}exface/vendor/npm-asset/jsoneditor/dist/jsoneditor.min.css">
	<script type="text/javascript" src="{$base_path}exface/vendor/npm-asset/jsoneditor/dist/jsoneditor.min.js"></script>
	
    <!-- Style fixes for MODx -->
	<style>
		.jsoneditor-contextmenu ul.menu {width: 126px !important}
	</style>
	
	<!-- Plugin initialization --><script type="text/javascript">
	var richIds = ["{$richIds}"];
	var jsonEditors = {};
	for (var richField=0;richField<richIds.length;richField++){
			var richId = richIds[richField];
			var el = document.getElementById(richId);
			var newDiv = document.createElement('div');
			newDiv.setAttribute('id', 'jsonEditor'+richId);
			newDiv.style.height = '{$default_height}'+'px';
			
			var options = {
				name: "widget",
                mode: "tree",
                modes: ['code', 'tree'],
                enableTransform: false,
            	enableSort: false,
                autocomplete: {
                    applyTo: ['value'],
                    filter: function (token, match, config) {
						console.log(token, match, config);
							
						// remove leading space in token if not the only character
						if (  token.length > 1 
							&& ( token.search(/^\s[^\s]/i) > -1 )
						) {
							token = token.substr(1, token.length - 1);
						}
							
						// remove spaces in token if preceeded by double underscores
				        if (  token.length > 3  && token.search(/\_\_\s/i) ) {
                            token = token.substr(0, token.length - 1);
					   
                        // return true if token consists of whitespace characters only
                        } else if (!token.replace(/\s/g, '').length) {
					        return true;
					    } 
					    return match.indexOf(token) > -1;
					},

                    getOptions: function (text, path, input, editor) {
                        return new Promise(function (resolve, reject) {
                  		    var pathBase = path.length <= 1 ? '' : JSON.stringify(path.slice(-1));
                  		    if (editor._autosuggestPending === true) {
                                if (editor._autosuggestLastResult && editor._autosuggestLastPath == pathBase) {
                                    resolve(editor._autosuggestLastResult.values);
                                } else {
                                    reject();
                                }
                   		   } else {
                                editor._autosuggestPending = true;
                                var uxon = JSON.stringify(editor.get());
                                return jsonEditorFetchAutosuggest('widget', text, path, input, uxon, resolve, reject)
                       			.then(json => {
               				         if (json !== undefined) {
                       					editor._autosuggestPending = false;
                       					editor._autosuggestLastPath = pathBase;
                       					editor._autosuggestLastResult = json;
                       				}
                   			    });
           		           }
                        });
                    }
                },
                onError: function (err) {
				    alert(err.toString());
				}
            };
			  
			var editor = new JSONEditor(newDiv, options);
			editor.setText(el.innerHTML || "{}");
						editor.expandAll();
			jsonEditors[richId] = editor;

			el.parentNode.insertBefore(newDiv,el.nextSibling);
			el.style.display='none';

			var help = document.createElement('div');
			var helpInner = document.createElement('div');
			helpInner.style.display='none';
			help.addEventListener("click", function(){
                helpInner.style.display=(helpInner.style.display === "block" ? "none" : "block");
            });
			helpInner.innerHTML = "<table>"+
									"<tr><th>Key</th><th>Description</th></tr>"+
									"<tr><td>Alt+Arrows</td><td>Move the caret up/down/left/right between fields</td></tr>"+
									"<tr><td>Shift+Alt+Arrows</td><td>Move field up/down/left/right</td></tr>"+
									"<tr><td>Ctrl+D</td><td>Duplicate field</td></tr>"+
									"<tr><td>Ctrl+Del</td><td>Remove field</td></tr>"+
									"<tr><td>Ctrl+Enter</td><td>Open link when on a field containing an url</td></tr>"+
									"<tr><td>Ctrl+Ins</td><td>Insert a new field with type auto</td></tr>"+
									"<tr><td>Ctrl+Shift+Ins</td><td>Append a new field with type auto</td></tr>"+
									"<tr><td>Ctrl+E</td><td>Expand or collapse field</td></tr>"+
									"<tr><td>Alt+End</td><td>Move the caret to the last field</td></tr>"+
									"<tr><td>Ctrl+F</td><td>Find</td></tr>"+
									"<tr><td>F3, Ctrl+G<br></td><td>Find next</td></tr>"+
									"<tr><td>Shift+F3, Ctrl+Shift+G</td><td>Find previous</td></tr>"+
									"<tr><td>Alt+Home</td><td>Move the caret to the first field</td></tr>"+
									"<tr><td>Ctrl+M</td><td>Show actions menu</td></tr>"+
									"<tr><td>Ctrl+Z</td><td>Undo last action</td></tr>"+
									"<tr><td>Ctrl+Shift+Z</td><td>Redo</td></tr>"+
								  "</table>";
            help.innerHtml = '<a href="javascript:;"><i class="fa fa-question-circle" aria-hidden="true"></i> Keyboard Shortcuts</a>';
			//help.appendChild(helpInner);
			newDiv.parentNode.insertBefore(help,newDiv.nextSibling);
			
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

    function jsonEditorFetchAutosuggest(schema, text, path, input, uxon, resolve, reject) {
        var formData = new URLSearchParams({
    		action: 'exface.Core.UxonAutosuggest',
    		text: text,
    		path: JSON.stringify(path),
    		input: input,
    		schema: schema,
    		uxon: uxon
    	});
    	return fetch('{$autosuggestUrl}', {
    		method: "POST", // *GET, POST, PUT, DELETE, etc.
    		mode: "cors", // no-cors, cors, *same-origin
    		cache: "no-cache", // *default, no-cache, reload, force-cache, only-if-cached
    		credentials: "same-origin", // include, *same-origin, omit
    		headers: {
    			//"Content-Type": "application/json; charset=utf-8",
    			"Content-Type": "application/x-www-form-urlencoded",
    		},
    		redirect: "follow", // manual, *follow, error
    		referrer: "no-referrer", // no-referrer, *client
    		body: formData, // body data type must match "Content-Type" header
    	})
    	.then(response => response.json())
    	.then(json => {resolve(json.values); return json;})
    	.catch(response => {reject();}); // parses response to JSON
    }

    function jsonEditorgetNodeFromTarget(target) {
	   while (target) {
    	    if (target.node) {
    	       return target.node;
    	    }
    	    target = target.parentNode;
       }
    
	   return undefined;
    }
  
    function jsonEditorfocusFirstChildValue(node) {
    	var child, found;
    	for (var i in node.childs) {
    		child = node.childs[i];
    		if (child.type === 'string' || child.type === 'auto') {
    			child.focus(child.getField() ? 'value' : 'field');
                return child;
    		} else {
    			found = jsonEditorfocusFirstChildValue(child);
                if (found) {
                    return found;
                }
    		}
    	}
    	return false;
    }

    window.\$j(function() {
        for (var e in jsonEditors) {
        	var editor = jsonEditors[e];
            window.\$j(document).on('blur', '#jsonEditor'+e+' div.jsoneditor-field[contenteditable="true"]', function() {
                var node = jsonEditorgetNodeFromTarget(this);
        		if (node.getValue() === '') {
            		var path = node.getPath();
            		var prop = path[path.length-1];
            		if (editor._autosuggestLastResult && editor._autosuggestLastResult.templates) {
            			var tpl = editor._autosuggestLastResult.templates[prop];
            			if (tpl) {
            				var val = JSON.parse(tpl);
            				node.setValue(val, (Array.isArray(val) ? 'array' : 'object'));
            				node.expand(true);
            				jsonEditorfocusFirstChildValue(node);
            			}
            		} 
                }
        	});
        }
    });
	</script>
				
OUT;
        $modx->event->output($output);
        break;
        
    default:
        return; // stop here - this is very important.
        break;
}