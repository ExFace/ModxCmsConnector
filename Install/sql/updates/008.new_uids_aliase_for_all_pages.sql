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
((SELECT msp.id FROM modx_site_plugins msp WHERE msp.name = 'ExFace User Connector' OR msp.name = 'ExFace'), (SELECT msen.id FROM modx_system_eventnames msen WHERE msen.name = 'OnDocFormSave'), 1);

#Template-Variablen hinzufuegen
INSERT INTO modx_site_tmplvars (type, name, caption, description, editor_type, category, locked, elements, rank, display, display_params, default_text) VALUES
('text', 'ExfacePageUID', 'UID', 'UID of the page (is filled in automatically if left empty).', 0, 0, 0, '', (SELECT MAX(mstv.rank) + 1 FROM modx_site_tmplvars mstv), '', '&output=[+value+]', '');
INSERT INTO modx_site_tmplvars (type, name, caption, description, editor_type, category, locked, elements, rank, display, display_params, default_text) VALUES
('dropdown', 'ExfacePageAppAlias', 'App', 'Assigns the page to an app.', 0, 0, 0, '@SELECT \'\' as app_alias UNION ALL SELECT app_alias FROM exf_app', (SELECT MAX(mstv.rank) + 1 FROM modx_site_tmplvars mstv), '', '', '');
INSERT INTO modx_site_tmplvars (type, name, caption, description, editor_type, category, locked, elements, rank, display, display_params, default_text) VALUES
('dropdown', 'ExfacePageDoUpdate', 'Update page', 'Specifies if the page is updated automatically.', 0, 0, 0, 'Yes==1||No==0', (SELECT MAX(mstv.rank) + 1 FROM modx_site_tmplvars mstv), '', '', '1');
INSERT INTO modx_site_tmplvars (type, name, caption, description, editor_type, category, locked, elements, rank, display, display_params, default_text) VALUES
('dropdown', 'ExfacePageReplaceAlias', 'Replace page alias', 'The specified page is replaced by this page.', 0, 0, 0, '@SELECT \'\' AS alias UNION ALL SELECT alias FROM [+PREFIX+]site_content where alias != \'\'', (SELECT MAX(mstv.rank) + 1 FROM modx_site_tmplvars mstv), '', '', '');
INSERT INTO modx_site_tmplvars (type, name, caption, description, editor_type, category, locked, elements, rank, display, display_params, default_text) VALUES
('text', 'ExfacePageDefaultParentAlias', 'Default parent', 'The default parent of the page.', 0, 0, 0, '', (SELECT MAX(mstv.rank) + 1 FROM modx_site_tmplvars mstv), '', '&output=[+value+]', '');
#Template-Variablen mit Templates verbinden
INSERT INTO modx_site_tmplvar_templates (tmplvarid, templateid, rank) VALUES
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageUID'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Desktop' OR mstp.templatename = 'Desktop (jEasyUI)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageUID'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa AdminLTE' OR mstp.templatename = 'Responsive (AdminLTE)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageUID'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Mobile' OR mstp.templatename = 'Mobile (jQueryMobile) - experimental' OR mstp.templatename = 'Mobile (jQuery mobile)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageUID'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Desktop embedded' OR mstp.templatename = 'alexa RMS embedded'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageAppAlias'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Desktop' OR mstp.templatename = 'Desktop (jEasyUI)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageAppAlias'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa AdminLTE' OR mstp.templatename = 'Responsive (AdminLTE)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageAppAlias'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Mobile' OR mstp.templatename = 'Mobile (jQueryMobile) - experimental' OR mstp.templatename = 'Mobile (jQuery mobile)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageAppAlias'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Desktop embedded' OR mstp.templatename = 'alexa RMS embedded'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageDoUpdate'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Desktop' OR mstp.templatename = 'Desktop (jEasyUI)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageDoUpdate'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa AdminLTE' OR mstp.templatename = 'Responsive (AdminLTE)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageDoUpdate'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Mobile' OR mstp.templatename = 'Mobile (jQueryMobile) - experimental' OR mstp.templatename = 'Mobile (jQuery mobile)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageDoUpdate'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Desktop embedded' OR mstp.templatename = 'alexa RMS embedded'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageReplaceAlias'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Desktop' OR mstp.templatename = 'Desktop (jEasyUI)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageReplaceAlias'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa AdminLTE' OR mstp.templatename = 'Responsive (AdminLTE)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageReplaceAlias'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Mobile' OR mstp.templatename = 'Mobile (jQueryMobile) - experimental' OR mstp.templatename = 'Mobile (jQuery mobile)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageReplaceAlias'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Desktop embedded' OR mstp.templatename = 'alexa RMS embedded'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageDefaultParentAlias'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Desktop' OR mstp.templatename = 'Desktop (jEasyUI)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageDefaultParentAlias'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa AdminLTE' OR mstp.templatename = 'Responsive (AdminLTE)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageDefaultParentAlias'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Mobile' OR mstp.templatename = 'Mobile (jQueryMobile) - experimental' OR mstp.templatename = 'Mobile (jQuery mobile)'), 0),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageDefaultParentAlias'), (SELECT mstp.id FROM modx_site_templates mstp WHERE mstp.templatename = 'alexa Desktop embedded' OR mstp.templatename = 'alexa RMS embedded'), 0);
#mm_rules setzen um ExfacePageUID readonly, ExfacePageDefaultParentAlias nicht sichtbar zu machen
UPDATE modx_site_htmlsnippets SET snippet = CONCAT(snippet, '

mm_ddReadonly(\'ExfacePageUID\');
mm_ddReadonly(\'ExfacePageDefaultParentAlias\');') WHERE name = 'mm_rules';

#UIDs fuer vorhandene Seiten setzen
INSERT INTO modx_site_tmplvar_contentvalues (tmplvarid, contentid, value)
SELECT mstv.id AS tmplvarid, msc.id AS contentid, UUID() AS value FROM modx_site_content msc LEFT JOIN modx_site_tmplvars mstv ON mstv.name = 'ExfacePageUID';
UPDATE modx_site_tmplvar_contentvalues SET value = CONCAT('0x', REPLACE(value, '-', '')) WHERE tmplvarid = (SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageUID');

#Funktion definieren um Seitentitel in Aliase umzuwandeln
DROP FUNCTION IF EXISTS alphanum;
DELIMITER |
CREATE FUNCTION alphanum( str VARCHAR(255) ) RETURNS VARCHAR(255)
BEGIN
  DECLARE i, len SMALLINT DEFAULT 1;
  DECLARE ret VARCHAR(255) DEFAULT '';
  DECLARE c VARCHAR(1);
  SET len = CHAR_LENGTH( str );
  REPEAT
    BEGIN
      SET c = MID( str, i, 1 );
      IF c LIKE BINARY 'Ä' THEN
        SET ret=CONCAT(ret,'Ae');
      ELSEIF c LIKE BINARY 'ä' THEN
        SET ret=CONCAT(ret,'ae');
      ELSEIF c LIKE BINARY 'Ö' THEN
        SET ret=CONCAT(ret,'Oe');
      ELSEIF c LIKE BINARY 'ö' THEN
        SET ret=CONCAT(ret,'oe');
      ELSEIF c LIKE BINARY 'Ü' THEN
        SET ret=CONCAT(ret,'Ue');
      ELSEIF c LIKE BINARY 'ü' THEN
        SET ret=CONCAT(ret,'ue');
      ELSEIF c LIKE BINARY 'ß' THEN
        SET ret=CONCAT(ret,'ss');
      ELSEIF c LIKE BINARY '&' THEN
        SET ret=CONCAT(ret,'and');
      ELSEIF c LIKE BINARY '-' THEN
        SET ret=CONCAT(ret,'-');
      ELSEIF c LIKE BINARY '\_' THEN
        SET ret=CONCAT(ret,'_');
      ELSEIF c LIKE BINARY '.' THEN
        SET ret=CONCAT(ret,'.');
      ELSEIF c REGEXP '[[:space:]]' THEN
        SET ret=CONCAT(ret,'-');
      ELSEIF c REGEXP '[[:alnum:]]' THEN
        SET ret=CONCAT(ret,c);
      END IF;
      SET i = i + 1;
    END;
  UNTIL i > len END REPEAT;
  RETURN ret;
END |
DELIMITER ;
#Aliase fuer vorhandene Seiten setzen
UPDATE modx_site_content SET alias = LOWER(alphanum(pagetitle)) WHERE alias IS NULL OR alias = '';

#PageDefaultParentAlias fuer vorhandene Seiten setzen
INSERT INTO modx_site_tmplvar_contentvalues (tmplvarid, contentid, value)
SELECT mstv.id AS tmplvarid, msc.id AS contentid, (SELECT alias FROM modx_site_content WHERE id = msc.parent) AS value FROM modx_site_content msc LEFT JOIN modx_site_tmplvars mstv ON mstv.name = 'ExfacePageDefaultParentAlias';

#Container fuer neue Seiten ohne Parent hinzufuegen
INSERT INTO modx_site_content (type, contentType, pagetitle, longtitle, description, alias, link_attributes, published, pub_date, unpub_date, parent, isfolder, introtext, content, richtext, template, menuindex, searchable, cacheable, createdby, createdon, editedby, editedon, deleted, deletedon, deletedby, publishedon, publishedby, menutitle, donthit, haskeywords, hasmetatags, privateweb, privatemgr, content_dispo, hidemenu, alias_visible) VALUES
('document', 'text/html', 'Neue Seiten', '', '', 'exface.core.new-pages', '', 1, 0, 0, 0, 1, '', '', 1, (SELECT mss.setting_value FROM modx_system_settings mss WHERE mss.setting_name = 'default_template'), 99, 1, 1, 1, 1504688617, 1, 1505309447, 0, 0, 0, 1504688617, 1, '', 0, 0, 0, 0, 0, 0, 0, 1);
#Template-Variablen fuer neue Seite hinzufuegen
INSERT INTO modx_site_tmplvar_contentvalues (tmplvarid, contentid, value) VALUES
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageUID'), (SELECT msc.id FROM modx_site_content msc WHERE msc.alias = 'exface.core.new-pages'), '0xc4c93592949f11e7ad66028037ec0200'),
((SELECT mstv.id FROM modx_site_tmplvars mstv WHERE mstv.name = 'ExfacePageAppAlias'), (SELECT msc.id FROM modx_site_content msc WHERE msc.alias = 'exface.core.new-pages'), 'exface.Core');

#Alias fuer Anmeldeseite setzen
UPDATE modx_site_content SET alias = 'login' WHERE id = (SELECT setting_value FROM modx_system_settings WHERE setting_name = 'unauthorized_page');

#Im Seiten-Content page_id durch page_alias ersetzen
UPDATE modx_site_content SET content = REPLACE(content, 'page_id', 'page_alias');
