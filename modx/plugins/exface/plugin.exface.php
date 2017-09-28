<?php
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Traits\ExfaceUserFunctions;

$eventName = $modx->event->name;

$vendorPath = MODX_BASE_PATH . 'exface' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
require_once $vendorPath . 'exface' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'CommonLogic' . DIRECTORY_SEPARATOR . 'Workbench.php';
require_once $vendorPath . 'exface' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'CommonLogic' . DIRECTORY_SEPARATOR . 'Traits' . DIRECTORY_SEPARATOR . 'ExfaceUserFunctions.php';

global $exface;
if (! isset($exface)) {
    $exface = new \exface\Core\CommonLogic\Workbench();
    $exface->start();
}

// Mappt Sprachen auf Locales
// TODO: unvollstaendig siehe Sprachdateien in manager/includes/lang
$lang_local_map = [
    'english-british' => 'en_GB',
    'english' => 'en_US',
    'francais-utf8' => 'fr_FR',
    'francais' => 'fr_FR',
    'german' => 'de_DE',
    'default' => ''
];

// Mappt Laendercodes auf Locales
// TODO: unvollstaendig siehe Laendercodes in manager/includes/lang/country/german_country.inc.php
$country_local_map = [
    '73' => 'fr_FR',
    '74' => 'fr_FR',
    '81' => 'de_DE',
    '222' => 'en_GB',
    '223' => 'en_US',
    'default' => ''
];

switch ($eventName) {
    // Verhindert, dass Modx Web- und Manager-Nutzer mit dem gleichen Nutzernamen existieren.
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
        // Vor- und Nachname aus dem vollen Namen ermitteln.
        if (($seppos = strrpos($userfullname, ' ')) !== false) {
            $firstname = substr($userfullname, 0, $seppos);
            $lastname = substr($userfullname, $seppos + 1);
        } else {
            $firstname = '';
            $lastname = $userfullname;
        }
        
        // Locale ermitteln
        if ($_POST['manager_language']) {
            $lang = $_POST['manager_language'];
            if (! array_key_exists($lang, $lang_local_map)) {
                $lang = 'default';
            }
            $locale = $lang_local_map[$lang];
        } else {
            $country = $_POST['country'];
            if (! array_key_exists($country, $country_local_map)) {
                $country = 'default';
            }
            $locale = $country_local_map[$country];
        }
        
        // Wenn der Nutzername geaendert wurde enthaelt $oldusername den alten, $username den
        // neuen, sonst ist $oldusername leer.
        $exf_user_old = $oldusername ? ExfaceUserFunctions::exfaceUserRead($oldusername) : null;
        $exf_user = ExfaceUserFunctions::exfaceUserRead($username);
        if ($exf_user_old) {
            if ($exf_user) {
                ExfaceUserFunctions::exfaceUserDelete($oldusername, $exf_user_old);
                ExfaceUserFunctions::exfaceUserUpdate($username, $username, $firstname, $lastname, $locale, $exf_user);
            } else {
                ExfaceUserFunctions::exfaceUserUpdate($oldusername, $username, $firstname, $lastname, $locale, $exf_user_old);
            }
        } else {
            if ($exf_user) {
                ExfaceUserFunctions::exfaceUserUpdate($username, $username, $firstname, $lastname, $locale, $exf_user);
            } else {
                ExfaceUserFunctions::exfaceUserCreate($username, $firstname, $lastname, $locale);
            }
        }
        
        break;
    
    case 'OnManagerDeleteUser':
    case 'OnWebDeleteUser':
        if ($exf_user = ExfaceUserFunctions::exfaceUserRead($username)) {
            ExfaceUserFunctions::exfaceUserDelete($username, $exf_user);
        }
        
        break;
}