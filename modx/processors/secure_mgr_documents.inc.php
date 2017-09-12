<?php
// sfl: angepasst aus ModX Evolution 1.2.1 secure_mgr_documents.inc.php
// sfl: wichtig: Die Formatierung dieser Datei darf auf keinen Fall veraendert werden, um einen
// einfachen Vergleich mit der Orginaldatei zu gewährleisten.

// sfl: IN_MANAGER_MODE-Check ausgeschaltet.

/**
 *	Secure Manager Documents
 *	This script will mark manager documents as private
 *
 *	A document will be marked as private only if a manager user group 
 *	is assigned to the document group that the document belongs to.
 *
 */

function secureMgrDocument($docid='') {
	global $modx;
		
	$modx->db->update('privatemgr = 0', $modx->getFullTableName("site_content"), ($docid>0 ? "id='$docid'":"privatemgr = 1"));
	$rs = $modx->db->select(
		'DISTINCT sc.id',
		$modx->getFullTableName("site_content")." sc
			LEFT JOIN ".$modx->getFullTableName("document_groups")." dg ON dg.document = sc.id
			LEFT JOIN ".$modx->getFullTableName("membergroup_access")." mga ON mga.documentgroup = dg.document_group",
		($docid>0 ? " sc.id='{$docid}' AND ":"")."mga.id>0"
		);
	$ids = $modx->db->getColumn("id",$rs);
	if(count($ids)>0) {
		$modx->db->update('privatemgr = 1', $modx->getFullTableName("site_content"), "id IN (".implode(", ",$ids).")");	
	}
}
?>