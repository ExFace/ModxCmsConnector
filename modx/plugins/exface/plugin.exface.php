<?php
use Ramsey\Uuid\Uuid;

const TV_APP_ALIAS_NAME = 'ExfacePageAppAlias';

const TV_REPLACE_ALIAS_NAME = 'ExfacePageReplaceAlias';

const TV_UID_NAME = 'ExfacePageUID';

const TV_DO_UPDATE_NAME = 'ExfacePageDoUpdate';

$eventName = $modx->event->name;

$vendorPath = MODX_BASE_PATH . 'exface' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
require_once $vendorPath . 'exface' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'CommonLogic' . DIRECTORY_SEPARATOR . 'Workbench.php';
require_once $vendorPath . 'autoload.php';

global $exface;
if (! isset($exface)) {
    $exface = new \exface\Core\CommonLogic\Workbench();
    $exface->start();
}

switch ($eventName) {
    /**
     * Synchronizes a modx user account with an ExFace core user
     */
    case "OnManagerSaveUser":
//         $sql = "SELECT mu.username,mu.password,ma.*  
// 				FROM " . $modx->getFullTableName("manager_users") . " mu 
// 				INNER JOIN " . $modx->getFullTableName("user_attributes") . " ma ON ma.internalKey = mu.id 
// 				WHERE mu.id = '$userid'";
//         $rs = $modx->dbQuery($sql);
//         if (! $rs)
//             $e->alert("Error while reading database " . mysql_error());
//         else {
//             $row = $modx->fetchRow($rs);
//             exfUserUpdate($row);
//         }
        break;
    
    case "OnStripAlias":
        // Alias setzen. Zunaechst wird der uebergebene Alias entsprechend dem trans-
        // alias-Plugin verarbeitet. Anschliessend wird der Namespace der App vorange-
        // stellt, falls eine App angegeben ist.
        
        // Start: angepasst aus plugin.transalias.php
        require_once $modx->config['base_path'] . 'assets/plugins/transalias/transalias.class.php';
        $trans = new TransAlias($modx);
        $trans->loadTable('common', 'No');
        $alias = $trans->stripAlias($alias, 'lowercase alphanumeric', 'dash');
        // Ende: angepasst aus plugin.transalias.php
        
        // ExfacePageAppAlias TV auslesen
        $tvIds = $exface->getCMS()->getTemplateVariableIds();
        $appAlias = $_POST['tv' . $tvIds[TV_APP_ALIAS_NAME]];
        
        // Alias mit Namespace erzeugen und zurueckgeben
        if ($_POST['alias'] === '') {
            if ($appAlias) {
                $modx->event->output($appAlias . '.' . $alias);
            } else {
                $modx->event->output($alias);
            }
        } else {
            if ($appAlias && stripos($alias, $appAlias) === false) {
                $modx->event->output($appAlias . '.' . $alias);
            } else {
                $modx->event->output($alias);
            }
        }
        
        // UID setzen.
        if (! $_POST['tv' . $tvIds[TV_UID_NAME]]) {
            $_POST['tv' . $tvIds[TV_UID_NAME]] = '0x' . Uuid::uuid1()->getHex();
        }
        
        break;
        
    case 'OnDocFormSave':
        // ExfacePageAppAlias TV auslesen
        $tvIds = $exface->getCMS()->getTemplateVariableIds();
        $appAlias = $_POST['tv' . $tvIds[TV_APP_ALIAS_NAME]];
        
        if ($appAlias) {
            // Wird eine Seite mit gesetztem App-Alias gespeichert, so wird eine Warnung
            // angezeigt, dass die Aenderungen beim naechsten Update ueberschrieben werden
            // koennten.
            
            // TODO Es gibt hier ein Problem mit der Anzeige der Meldung wenn sie uebersetzt
            // wird. Wird z.B. $exface->getLogger()->... oder $exface->getApp(...)->getTranslator()->translate()
            // aufgerufen, wird der SessionContextScope instanziiert und die aktive Session
            // geschlossen. Selbst wenn sie direkt danach wieder geoeffnet wird, wird die
            // Meldung nicht angezeigt. Problem mit Sessions und globalen Variablen?
            // (global $SystemAlertMsgQueque)?
            
            //$modx->event->alert($exface->getApp('exface.ModxCmsConnector')->getTranslator()->translate('WARNING_SAVE_DIALOG_WITH_APP_ALIAS'));
            $modx->event->alert('You made changes to a dialog, which may be overwritten during the next update.');
        }
        
        break;
}

/**
 * TODO
 */
// function exfUserUpdate($mngr)
// {
//     global $modx;
//     $uid = $mngr['username'];
//     // check for existing account
//     $rs = $modx->dbQuery("SELECT * FROM " . $modx->getFullTableName("web_users") . " WHERE username='$uid'");
//     $count = $modx->recordCount($rs);
//     if ($count > 0) {
//         // update existing web account
//         $fields = array();
//         $allowed = array(
//             "fullname",
//             "email",
//             "phone",
//             "mobilephone",
//             "blocked",
//             "blockeduntil",
//             "dob",
//             "gender",
//             "country",
//             "state",
//             "zip",
//             "fax",
//             "photo",
//             "comment",
//             "blockedafter"
//         );
//         foreach ($mngr as $key => $vlaue) {
//             if (in_array($key, $allowed)) {
//                 $fields[$key] = $mngr[$key];
//             }
//         }
//         $modx->updIntTableRow($fields, "web_user_attributes", " internalKey='" . $web['id'] . "'");
//         $modx->updIntTableRow(array(
//             "password" => $mngr["password"]
//         ), "web_users", " id='" . $web["id"] . "'");
//     } else {
//         // create new account
//         $fields = array();
//         $allowed = array(
//             "fullname",
//             "email",
//             "phone",
//             "mobilephone",
//             "blocked",
//             "blockeduntil",
//             "dob",
//             "gender",
//             "country",
//             "state",
//             "zip",
//             "fax",
//             "photo",
//             "comment",
//             "blockedafter"
//         );
//         foreach ($mngr as $key => $vlaue) {
//             if (in_array($key, $allowed)) {
//                 $fields[$key] = $mngr[$key];
//             }
//         }
//         $modx->putIntTableRow(array(
//             "username" => $mngr["username"],
//             "password" => $mngr["password"]
//         ), "web_users");
//         $fields["internalKey"] = mysqli_insert_id();
//         $modx->putIntTableRow($fields, "web_user_attributes");
//     }
// }