<?php
// sfl: angepasst aus ModX Evolution 1.2.1 secure_web_documents.inc.php
// sfl: wichtig: Die Formatierung dieser Datei darf auf keinen Fall veraendert werden, um einen
// einfachen Vergleich mit der Orginaldatei zu gewährleisten.

// sfl: IN_MANAGER_MODE-Check ausgeschaltet.

/**
 *	Secure Web Documents
 *	This script will mark web documents as private
 *
 *	A document will be marked as private only if a web user group 
 *	is assigned to the document group that the document belongs to.
 *
 */

function secureWebDocument($docid='') {
	global $modx;
		
	$modx->db->update('privateweb = 0', $modx->getFullTableName("site_content"), ($docid>0 ? "id='$docid'":"privateweb = 1"));
	$rs = $modx->db->select(
		'DISTINCT sc.id',
		$modx->getFullTableName("site_content")." sc
			LEFT JOIN ".$modx->getFullTableName("document_groups")." dg ON dg.document = sc.id
			LEFT JOIN ".$modx->getFullTableName("webgroup_access")." wga ON wga.documentgroup = dg.document_group",
		($docid>0 ? " sc.id='{$docid}' AND ":"")."wga.id>0"
		);
	$ids = $modx->db->getColumn("id",$rs);
	if(count($ids)>0) {
		$modx->db->update('privateweb = 1', $modx->getFullTableName("site_content"), "id IN (".implode(", ",$ids).")");	
	}
}
?>