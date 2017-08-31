<?php

const TV_APP_ALIAS_NAME = 'ExfacePageAppAlias';

const TV_REPLACE_ALIAS_NAME = 'ExfacePageReplaceAlias';

const TV_UID_NAME = 'ExfacePageUID';

const TV_DO_UPDATE_NAME = 'ExfacePageDoUpdate';

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
        $result = $modx->db->select('id, name', $modx->getFullTableName('site_tmplvars'));
        $tvIds = [];
        while ($row = $modx->db->getRow($result)) {
            $tvIds[$row['name']] = $row['id'];
        }
        
        // ExfacePageAppAlias TV auslesen
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
        
        // UID setzen. TODO besser ueber eine custom TV loesen, jetzt ist die UID von
        // Hand bearbeitbar.
        if (! $_POST['tv' . $tvIds[TV_UID_NAME]]) {
            $_POST['tv' . $tvIds[TV_UID_NAME]] = '0x' . bin2hex(openssl_random_pseudo_bytes(16));
        }
        
        break;
}
