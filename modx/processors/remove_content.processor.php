<?php
// sfl: angepasst aus ModX Evolution 1.2.1 remove_content.processor.php
// sfl: wichtig: Die Formatierung dieser Datei darf auf keinen Fall veraendert werden, um einen
// einfachen Vergleich mit der Orginaldatei zu gewährleisten.

// sfl: Aenderungen um Variablen zu initialisieren.
global $modx;

// provide english $_lang for error-messages
$_lang = array();
include MODX_MANAGER_PATH . 'includes' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'english.inc.php';
// sfl: Ende der Aenderungen um Variablen zu initialisieren.

// sfl: IN_MANAGER_MODE-Check ausgeschaltet.
if(!$modx->hasPermission('delete_document')) {
	throw new Exception($_lang["error_no_privileges"]);
}

$rs = $modx->db->select('id', $modx->getFullTableName('site_content'), "deleted=1");
$ids = $modx->db->getColumn('id', $rs); 

// invoke OnBeforeEmptyTrash event
$modx->invokeEvent("OnBeforeEmptyTrash",
						array(
							"ids"=>$ids
						));

// remove the document groups link.
$sql = "DELETE document_groups
		FROM ".$modx->getFullTableName('document_groups')." AS document_groups
		INNER JOIN ".$modx->getFullTableName('site_content')." AS site_content ON site_content.id = document_groups.document
		WHERE site_content.deleted=1";
$modx->db->query($sql);

// remove the TV content values.
$sql = "DELETE site_tmplvar_contentvalues
		FROM ".$modx->getFullTableName('site_tmplvar_contentvalues')." AS site_tmplvar_contentvalues
		INNER JOIN ".$modx->getFullTableName('site_content')." AS site_content ON site_content.id = site_tmplvar_contentvalues.contentid
		WHERE site_content.deleted=1";
$modx->db->query($sql);

//'undelete' the document.
$modx->db->delete($modx->getFullTableName('site_content'), "deleted=1");

	// invoke OnEmptyTrash event
	$modx->invokeEvent("OnEmptyTrash",
						array(
							"ids"=>$ids
						));

	// empty cache
	$modx->clearCache('full');

// sfl: Kein Redirect.

?>