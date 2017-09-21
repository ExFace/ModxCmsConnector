#Exface Plugin aktivieren
UPDATE modx_site_plugins SET disabled = 0, name = 'ExFace' WHERE name = 'ExFace User Connector' OR name = 'ExFace';
#Exface Plugin mit OnDocFormSave Event verbinden
INSERT INTO modx_site_plugin_events (pluginid, evtid, priority) VALUES
((SELECT msp.id FROM modx_site_plugins msp WHERE msp.name = 'ExFace'), (SELECT msen.id FROM modx_system_eventnames msen WHERE name = 'OnWebSaveUser'), 1),
((SELECT msp.id FROM modx_site_plugins msp WHERE msp.name = 'ExFace'), (SELECT msen.id FROM modx_system_eventnames msen WHERE name = 'OnWebDeleteUser'), 1),
((SELECT msp.id FROM modx_site_plugins msp WHERE msp.name = 'ExFace'), (SELECT msen.id FROM modx_system_eventnames msen WHERE name = 'OnBeforeUserFormSave'), 1),
((SELECT msp.id FROM modx_site_plugins msp WHERE msp.name = 'ExFace'), (SELECT msen.id FROM modx_system_eventnames msen WHERE name = 'OnBeforeWUsrFormSave'), 1);