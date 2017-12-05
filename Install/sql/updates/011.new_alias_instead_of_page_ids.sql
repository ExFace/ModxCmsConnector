#Systemeinstellungen
UPDATE modx_system_settings SET setting_value = '1' WHERE setting_name = 'friendly_urls';
UPDATE modx_system_settings SET setting_value = '0' WHERE setting_name = 'allow_duplicate_alias';
UPDATE modx_system_settings SET setting_value = '1' WHERE setting_name = 'automatic_alias';

#Transalias Plugin deaktivieren
UPDATE modx_site_plugins SET disabled = 1 WHERE name = 'TransAlias';

#Exface Plugin aktivieren
UPDATE modx_site_plugins SET disabled = 0 WHERE name = 'ExFace';

#Im Seiten-Content page_id durch page_alias ersetzen
UPDATE modx_site_content SET content = REPLACE(content, 'page_id', 'page_alias');
