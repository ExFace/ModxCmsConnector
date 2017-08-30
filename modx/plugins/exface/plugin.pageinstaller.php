<?php
switch ($modx->event->name) {
    case "OnStripAlias":
        // Alias setzen. Zunaechst wird der uebergebene Alias entsprechend dem trans-
        // alias-Plugin verarbeitet. Anschliessend wird der Namespace der App vorange-
        // stellt, falls eine App angegeben ist.
        
        // Start angepasst aus plugin.transalias.php
        require_once $modx->config['base_path'] . 'assets/plugins/transalias/transalias.class.php';
        $trans = new TransAlias($modx);
        $trans->loadTable('common', 'No');
        $alias = $trans->stripAlias($alias, 'lowercase alphanumeric', 'dash');
        // Ende angepasst aus plugin.transalias.php
        
        // IDs der Template-Variablen bestimmen.
        $tvIds = [];
        $result = $modx->db->select('id, name', $modx->getFullTableName('site_tmplvars'));
        while ($row = $modx->db->getRow($result)) {
            $tvIds[$row['name']] = $row['id'];
        }
        
        // ExfacePageApp TV auslesen
        if ($appId = $_POST['tv' . $tvIds['ExfacePageApp']]) {
            $appId = substr($appId, 0, 2) === '0x' ? substr($appId, 2) : $appId;
            $result = $modx->db->select('app_alias', 'exf_app', 'oid = UNHEX("' . $appId . '")');
            $appAlias = $modx->db->getRow($result)['app_alias'];
        }
        
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
        
        // UID setzen. TODO besser ueber eine custom TV loesen, jetzt ist die UID von
        // Hand bearbeitbar.
        if (! $_POST['tv' . $tvIds['ExfacePageUID']]) {
            $_POST['tv' . $tvIds['ExfacePageUID']] = '0x' . bin2hex(openssl_random_pseudo_bytes(16));
        }
        
        break;
}