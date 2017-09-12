<?php
// sfl: Aenderungen um Variablen zu initialisieren.
global $modx;

// provide english $_lang for error-messages
$_lang = array();
include MODX_MANAGER_PATH . 'includes' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'english.inc.php';

// get the settings from the database
include MODX_MANAGER_PATH . 'includes' . DIRECTORY_SEPARATOR . 'settings.inc.php'; //dort IN_MANAGER_MODE-Check auskommentiert, aka's Aenderung?
// sfl: Ende der Aenderungen um Variablen zu initialisieren.

// sfl: IN_MANAGER_MODE-Check ausgeschaltet.
//if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.");
if(!$modx->hasPermission('delete_document')) {
    //$modx->webAlertAndQuit($_lang["error_no_privileges"]);
    throw new Exception($_lang["error_no_privileges"]);
}

$id = isset($_GET['id'])? intval($_GET['id']) : 0;
if($id==0) {
    //$modx->webAlertAndQuit($_lang["error_no_id"]);
    throw new Exception($_lang["error_no_id"]);
}

/*******ищем родителя чтобы к нему вернуться********/
$content=$modx->db->getRow($modx->db->select('parent, pagetitle', $modx->getFullTableName('site_content'), "id='{$id}'"));
$pid=($content['parent']==0?$id:$content['parent']);

/************ а заодно и путь возврата (сам путь внизу файла) **********/
$sd=isset($_REQUEST['dir'])?'&dir='.$_REQUEST['dir']:'&dir=DESC';
$sb=isset($_REQUEST['sort'])?'&sort='.$_REQUEST['sort']:'&sort=createdon';
$pg=isset($_REQUEST['page'])?'&page='.(int)$_REQUEST['page']:'';
$add_path=$sd.$sb.$pg;

/*****************************/

$deltime = time();
$children = array();

// check permissions on the document
include_once MODX_MANAGER_PATH . "processors/user_documents_permissions.class.php";
$udperms = new udperms();
$udperms->user = $modx->getLoginUserID();
$udperms->document = $id;
$udperms->role = $_SESSION['mgrRole'];

if(!$udperms->checkPermissions()) {
    //$modx->webAlertAndQuit($_lang["access_permission_denied"]);
    throw new Exception($_lang["access_permission_denied"]);
}

// sfl: Aus irgendeinem Grund funktioniert das global hier nicht richtig. Die
// Variablen sind in der Funktion nicht zugaenglich.
if (!function_exists('getChildren')) {
function getChildren($parent, $children, $site_start, $site_unavailable_page, $error_page, $unauthorized_page) {
    
    global $modx;
    //global $children;
    //global $site_start;
    //global $site_unavailable_page;
    //global $error_page;
    //global $unauthorized_page;
    
    $rs = $modx->db->select('id', $modx->getFullTableName('site_content'), "parent={$parent} AND deleted=0");
    // the document has children documents, we'll need to delete those too
    while ($childid=$modx->db->getValue($rs)) {
        if($childid==$site_start) {
            //$modx->webAlertAndQuit("The document you are trying to delete is a folder containing document {$childid}. This document is registered as the 'Site start' document, and cannot be deleted. Please assign another document as your 'Site start' document and try again.");
            throw new Exception("The document you are trying to delete is a folder containing document {$childid}. This document is registered as the 'Site start' document, and cannot be deleted. Please assign another document as your 'Site start' document and try again.");
        }
        if($childid==$site_unavailable_page) {
            //$modx->webAlertAndQuit("The document you are trying to delete is a folder containing document {$childid}. This document is registered as the 'Site unavailable page' document, and cannot be deleted. Please assign another document as your 'Site unavailable page' document and try again.");
            throw new Exception("The document you are trying to delete is a folder containing document {$childid}. This document is registered as the 'Site unavailable page' document, and cannot be deleted. Please assign another document as your 'Site unavailable page' document and try again.");
        }
        if($childid==$error_page) {
            //$modx->webAlertAndQuit("The document you are trying to delete is a folder containing document {$childid}. This document is registered as the 'Site error page' document, and cannot be deleted. Please assign another document as your 'Site error page' document and try again.");
            throw new Exception("The document you are trying to delete is a folder containing document {$childid}. This document is registered as the 'Site error page' document, and cannot be deleted. Please assign another document as your 'Site error page' document and try again.");
        }
        if($childid==$unauthorized_page) {
            //$modx->webAlertAndQuit("The document you are trying to delete is a folder containing document {$childid}. This document is registered as the 'Site unauthorized page' document, and cannot be deleted. Please assign another document as your 'Site unauthorized page' document and try again.");
            throw new Exception("The document you are trying to delete is a folder containing document {$childid}. This document is registered as the 'Site unauthorized page' document, and cannot be deleted. Please assign another document as your 'Site unauthorized page' document and try again.");
        }
        $children[] = $childid;
        getChildren($childid, $children, $site_start, $site_unavailable_page, $error_page, $unauthorized_page);
        //echo "Found childNode of parentNode $parent: ".$childid."<br />";
    }
}
}

getChildren($id, $children, $site_start, $site_unavailable_page, $error_page, $unauthorized_page);

// invoke OnBeforeDocFormDelete event
$modx->invokeEvent("OnBeforeDocFormDelete",
    array(
        "id"=>$id,
        "children"=>$children
    ));

if(count($children)>0) {
    $modx->db->update(
        array(
            'deleted'   => 1,
            'deletedby' => $modx->getLoginUserID(),
            'deletedon' => $deltime,
        ), $modx->getFullTableName('site_content'), "id IN (".implode(", ", $children).")");
}

if($site_start==$id){
    //$modx->webAlertAndQuit("Document is 'Site start' and cannot be deleted!");
    throw new Exception("Document is 'Site start' and cannot be deleted!");
}

if($site_unavailable_page==$id){
    //$modx->webAlertAndQuit("Document is used as the 'Site unavailable page' and cannot be deleted!");
    throw new Exception("Document is used as the 'Site unavailable page' and cannot be deleted!");
}

if($error_page==$id) {
    //$modx->webAlertAndQuit("Document is used as the 'Site error page' and cannot be deleted!");
    throw new Exception("Document is used as the 'Site error page' and cannot be deleted!");
}

if($unauthorized_page==$id){
    //$modx->webAlertAndQuit("Document is used as the 'Site unauthorized page' and cannot be deleted!");
    throw new Exception("Document is used as the 'Site unauthorized page' and cannot be deleted!");
}

// delete the document.
$modx->db->update(
    array(
        'deleted'   => 1,
        'deletedby' => $modx->getLoginUserID(),
        'deletedon' => $deltime,
    ), $modx->getFullTableName('site_content'), "id='{$id}'");

// invoke OnDocFormDelete event
$modx->invokeEvent("OnDocFormDelete",
    array(
        "id"=>$id,
        "children"=>$children
    ));

// Set the item name for logger
$_SESSION['itemname'] = $content['pagetitle'];

// empty cache
$modx->clearCache('full');

// sfl: Kein Redirect.
// finished emptying cache - redirect
//$header="Location: index.php?r=1&a=7&id=$pid&dv=1".$add_path;
//header($header);
?>