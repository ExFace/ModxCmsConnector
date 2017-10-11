<?php
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\UserFactory;
use exface\Core\Exceptions\UserNotFoundError;

$eventName = $modx->event->name;

$vendorPath = MODX_BASE_PATH . 'exface' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
require_once $vendorPath . 'exface' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'CommonLogic' . DIRECTORY_SEPARATOR . 'Workbench.php';

global $exface;
if (! isset($exface)) {
    $exface = new \exface\Core\CommonLogic\Workbench();
    $exface->start();
}

$langLocalMap = $exface->getApp('exface.ModxCmsConnector')->getConfig()->getOption('USERS.LANGUAGE_LOCALE_MAPPING')->toArray();

switch ($eventName) {
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