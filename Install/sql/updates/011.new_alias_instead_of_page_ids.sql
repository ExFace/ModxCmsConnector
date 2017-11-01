#Systemeinstellungen
UPDATE modx_system_settings SET setting_value = '1' WHERE setting_name = 'friendly_urls';
UPDATE modx_system_settings SET setting_value = '0' WHERE setting_name = 'allow_duplicate_alias';
UPDATE modx_system_settings SET setting_value = '1' WHERE setting_name = 'automatic_alias';

#Transalias Plugin deaktivieren
UPDATE modx_site_plugins SET disabled = 1 WHERE name = 'TransAlias';

#Exface Plugin aktivieren
UPDATE modx_site_plugins SET disabled = 0, name = 'ExFace' WHERE name = 'ExFace User Connector' OR name = 'ExFace';
#Exface Plugin mit OnDocFormSave Event verbinden
INSERT INTO modx_site_plugin_events (pluginid, evtid, priority) VALUES
((SELECT msp.id FROM modx_site_plugins msp WHERE msp.name = 'ExFace User Connector' OR msp.name = 'ExFace'), (SELECT msen.id FROM modx_system_eventnames msen WHERE msen.name = 'OnDocFormSave'), 1) ON DUPLICATE KEY UPDATE;

#mm_rules setzen um ExfacePageUID readonly, ExfacePageDefaultParentAlias nicht sichtbar zu machen
UPDATE modx_site_htmlsnippets SET snippet = CONCAT(snippet, '

mm_ddReadonly(\'ExfacePageUID\');
mm_ddReadonly(\'ExfacePageDefaultParentAlias\');') WHERE name = 'mm_rules';

#Im Seiten-Content page_id durch page_alias ersetzen
UPDATE modx_site_content SET content = REPLACE(content, 'page_id', 'page_alias');
