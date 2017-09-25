<?php
use exface\Core\CommonLogic\Workbench;

$eventName = $modx->event->name;

$vendorPath = MODX_BASE_PATH . 'exface' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
require_once $vendorPath . 'exface' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'CommonLogic' . DIRECTORY_SEPARATOR . 'Workbench.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'plugin.exface.functions.php';

global $exface;
if (! isset($exface)) {
    $exface = new \exface\Core\CommonLogic\Workbench();
    $exface->start();
}

// Entsperren des Plugins bevor ein Fehler entsteht oder umgeleitet wird.
$exface->getCMS()->unlockPlugin($modx->event->activePlugin);

switch ($eventName) {
    // Verhindert, dass Modx Web- und Manager-Nutzer mit dem gleichen Nutzernamen existieren.
    case 'OnBeforeUserFormSave':
    case 'OnBeforeWUsrFormSave':
        // Kontrolle auf existierenden Web/Mgr-Nutzernamen entsprechend den Kontrollen in
        // save_user.processor.php und save_web_user.processor.php auf existierenden
        // Mgr/Web-Nutzernamen.
        $username = !empty ($_POST['newusername']) ? trim($_POST['newusername']) : "New User";
        if (($eventName == 'OnBeforeUserFormSave' && $exface->getCMS()->isModxWebUser($username)) || ($eventName == 'OnBeforeWUsrFormSave' && $exface->getCMS()->isModxMgrUser($username))) {
            $mode = $_POST['mode'];
            $editMode = $eventName == 'OnBeforeUserFormSave' ? '12' : '88';
            $id = intval($_POST['id']);
            $modx->manager->saveFormValues($mode);
            $modx->webAlertAndQuit('User name is already in use!', "index.php?a={$mode}" . ($mode == $editMode ? "&id={$id}" : ''));
        }
        
        break;
        
    // Synchronizes a modx user account with an ExFace core user
    case 'OnManagerSaveUser':
    case 'OnWebSaveUser':
        // Vor- und Nachname aus dem vollen Namen ermitteln.
        if (($seppos = strrpos($userfullname, ' ')) !== false) {
            $firstname = substr($userfullname, 0, $seppos);
            $lastname = substr($userfullname, $seppos + 1);
        } else {
            $firstname = '';
            $lastname = $userfullname;
        }
        
        // Wenn der Nutzername geaendert wurde enthaelt $oldusername den alten, $username den
        // neuen, sonst ist $oldusername leer.
        $exf_user_old = $oldusername ? readExfaceUser($oldusername) : null;
        $exf_user = readExfaceUser($username);
        if ($exf_user_old) {
            if ($exf_user) {
                deleteExfaceUser($oldusername, $exf_user_old);
                updateExfaceUser($username, $username, $firstname, $lastname, $exf_user);
            } else {
                updateExfaceUser($oldusername, $username, $firstname, $lastname, $exf_user_old);
            }
        } else {
            if ($exf_user) {
                updateExfaceUser($username, $username, $firstname, $lastname, $exf_user);
            } else {
                createExfaceUser($username, $firstname, $lastname);
            }
        }
        
        break;
        
    case 'OnManagerDeleteUser':
    case 'OnWebDeleteUser':
        if ($exf_user = readExfaceUser($username)) {
            deleteExfaceUser($username, $exf_user);
        }
        
        break;
}