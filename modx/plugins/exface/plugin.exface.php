<?php
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\UserFactory;
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\Exceptions\UiPageNotPartOfAppError;
use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\CommonLogic\NameResolver;

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
    $exface = new \exface\Core\CommonLogic\Workbench();
    $exface->start();
}

// Start: angepasst aus plugin.transalias.php
require_once $modx->config['base_path'] . 'assets/plugins/transalias/transalias.class.php';
$trans = new TransAlias($modx);
$trans->loadTable('common', 'No');
// Ende: angepasst aus plugin.transalias.php

switch ($eventName) {
    case "OnStripAlias":
        $tvIds = $exface->getCMS()->getTemplateVariableIds();
        
        // Die folgenden Aufrufe wuerden eigentlich besser in 'OnBeforeDocFormSave' passen.
        // Dort koennen die TVs $_POST aber nicht mehr effektiv geaendert werden (sie werden
        // schon vorher ausgelesen), deshalb ist dieser Code hier.
        
        // UID setzen.
        if (! $_POST['tv' . $tvIds[TV_UID_NAME]]) {
            $_POST['tv' . $tvIds[TV_UID_NAME]] = UiPage::generateUid();
        }
        
        // App vererben
        if (! $_POST['tv' . $tvIds[TV_APP_UID_NAME]] && $_POST['parent']) {
            try {
                $_POST['tv' . $tvIds[TV_APP_UID_NAME]] = $exface->getCMS()->loadPage($_POST['parent'])->getApp()->getUid();
            } catch (UiPageNotPartOfAppError $upnae) {
                // ignorieren
            }
        }
        
        // Default Menu Position setzen
        if (! $_POST['tv' . $tvIds[TV_DEFAULT_MENU_POSITION_NAME]] && $_POST['parent']) {
            $_POST['tv' . $tvIds[TV_DEFAULT_MENU_POSITION_NAME]] = $exface->getCMS()->loadPage($_POST['parent'])->getAliasWithNamespace() . ':' . $_POST['menuindex'];
        }
        
        // Alias mit Namespace erzeugen und zurueckgeben
        $alias = $trans->stripAlias($alias, 'lowercase alphanumeric', 'dash');
        $appNameResolver = NameResolver::createFromString($alias, 'Apps', $exface);
        if (isset($_POST['tv' . $tvIds[TV_APP_UID_NAME]])) {
            // manuelles Speichern
            if ($appUid = $_POST['tv' . $tvIds[TV_APP_UID_NAME]]) {
                $appAlias = strtolower($exface->getApp($appUid)->getAliasWithNamespace());
                $appNameResolver->setNamespace($appAlias);
            } else {
                $appNameResolver->setNamespace('');
            }
        }
        // else: Speichern durch Modx->create/updatePage
        $modx->event->output(trim($appNameResolver->getAliasWithNamespace(), ' .'));
        
        break;
    
    case 'OnDocFormSave':
        // ExfacePageAppUID TV auslesen
        $tvIds = $exface->getCMS()->getTemplateVariableIds();
        $appUid = $_POST['tv' . $tvIds[TV_APP_UID_NAME]];
        
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
        
        break;
    
    case 'OnDocDuplicate':
        // Die duplizierte Seite hat keinen Alias und u.U. (wenn die UID-TV durch mmrules readonly ist)
        // auch keine UID, kann daher durch die herkoemmlichen Methoden nicht so einfach geladen werden.
        // Daher werden hier erst einmal UID und Alias auf anderem Weg vergeben.
        
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
            
            $appNameResolver = NameResolver::createFromString($alias, 'Apps', $exface);
            if ($appUid = $resource->get(TV_APP_UID_NAME)) {
                $appAlias = strtolower($exface->getApp($appUid)->getAliasWithNamespace());
                $appNameResolver->setNamespace($appAlias);
            } else {
                $appNameResolver->setNamespace('');
            }
            
            $resource->set('alias', trim($appNameResolver->getAliasWithNamespace(), ' .'));
        }
        
        // Die duplizierte Seite bekommt eine neue UID.
        $resource->set(TV_UID_NAME, UiPage::generateUid());
        
        // Default Menu Position setzen.
        if ($resource->get('parent')) {
            $resource->set(TV_DEFAULT_MENU_POSITION_NAME, $exface->getCMS()->loadPage($resource->get('parent'))->getAliasWithNamespace() . ':' . $resource->get('menuindex'));
        }
        
        // Speichern der aktualisierten Seite.
        $resource->save();
        
        break;
    
    // Verhindert, dass Modx Web- und Manager-Nutzer mit dem gleichen Nutzernamen existieren,
    // wenn ein Modx Nutzer im Backend gespeichert wird.
    case 'OnBeforeUserFormSave':
    case 'OnBeforeWUsrFormSave':
        // Kontrolle auf existierenden Web/Mgr-Nutzernamen entsprechend den Kontrollen in
        // save_user.processor.php und save_web_user.processor.php auf existierenden
        // Mgr/Web-Nutzernamen.
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
        
        break;
    
    // Synchronisiert einen Modx-Nutzer mit einem Exface-Nutzer
    case 'OnManagerSaveUser':
    case 'OnWebSaveUser':
        $userContextScope = $exface->context()->getScopeUser();
        
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
        try {
            $exfUserOld = $userContextScope->getUserByName($oldusername);
        } catch (UserNotFoundError $unfe) {}
        try {
            $exfUser = $userContextScope->getUserByName($username);
        } catch (UserNotFoundError $unfe) {}
        if ($exfUserOld) {
            if ($exfUser) {
                // Der Nutzer wird gerade umbenannt. Es existiert ein Exface-Nutzer mit dem
                // alten Namen. Es existiert ebenso ein Exface-Nutzer mit dem neuen Namen.
                // Der Nutzer mit dem alten Namen wird geloescht. Der Nutzer mit dem neuen
                // Namen wird aktualisiert.
                $userContextScope->deleteUser($exfUserOld);
                
                $exfUser->setFirstName($firstname);
                $exfUser->setLastName($lastname);
                $exfUser->setLocale($locale);
                $exfUser->setEmail($useremail);
                $userContextScope->updateUser($exfUser);
            } else {
                // Der Nutzer wird gerade umbenannt. Es existiert ein Exface-Nutzer mit dem
                // alten Namen. Es existiert kein Exface-Nutzer mit dem neuen Namen. Der
                // Nutzer mit dem alten Namen wird aktualisiert.
                $exfUserOld->setUsername($username);
                $exfUserOld->setFirstName($firstname);
                $exfUserOld->setLastName($lastname);
                $exfUserOld->setLocale($locale);
                $exfUserOld->setEmail($useremail);
                $userContextScope->updateUser($exfUserOld);
            }
        } else {
            if ($exfUser) {
                // Der Nutzer wird nicht umbenannt. Es existiert ein Exface-Nutzer mit dem
                // Namen, welcher aktualisert wird.
                $exfUser->setFirstName($firstname);
                $exfUser->setLastName($lastname);
                $exfUser->setLocale($locale);
                $exfUser->setEmail($useremail);
                $userContextScope->updateUser($exfUser);
            } else {
                // Der Nutzer wird nicht umbenannt. Es existiert kein Exface-Nutzer mit dem
                // Namen, daher wird ein neuer Exface-Nutzer angelegt.
                $userContextScope->createUser(UserFactory::create($exface, $username, $firstname, $lastname, $locale, $useremail));
            }
        }
        
        break;
    
    // Wird ein Modx-Nutzer geloescht wird auch der entsprechende Exface-Nutzer geloescht.
    case 'OnManagerDeleteUser':
    case 'OnWebDeleteUser':
        $userContextScope = $exface->context()->getScopeUser();
        
        try {
            $exfUser = $userContextScope->getUserByName($username);
        } catch (UserNotFoundError $unfe) {}
        if ($exfUser) {
            // Es existiert ein Exface-Nutzer mit dem Namen, welcher geloescht wird.
            $userContextScope->deleteUser($exfUser);
        }
        
        break;
}