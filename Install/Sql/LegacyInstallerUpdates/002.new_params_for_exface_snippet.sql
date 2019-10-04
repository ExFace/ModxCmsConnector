/* Add a fallback to the content in the main call of the ExFace snippet */
UPDATE modx_site_templates SET content = REPLACE(content, '[[ExFace]]', '[[ExFace? &fallback_field=`content`]]');
UPDATE modx_site_templates SET content = REPLACE(content, '[!ExFace!]', '[[ExFace? &fallback_field=`content`]]');

/* Replace function parameter by the new action parameter */
UPDATE modx_site_templates SET content = REPLACE(content, '&function=`headers`', '&action=`exface.Core.ShowHeaders`');

/* Add the template parameter to all current templates */
UPDATE modx_site_templates SET content = REPLACE(content, '[[ExFace?', '[[ExFace? &template=`exface.AdminLteTemplate`') WHERE id IN (11,13,14);
UPDATE modx_site_templates SET content = REPLACE(content, '[!ExFace?', '[!ExFace? &template=`exface.AdminLteTemplate`') WHERE id IN (11,13,14);
UPDATE modx_site_templates SET content = REPLACE(content, '&template=`exface.AdminLteTemplate` &template=`exface.AdminLteTemplate`', '&template=`exface.AdminLteTemplate`') WHERE id IN (11,13,14);
UPDATE modx_site_templates SET content = REPLACE(content, '[[ExFace?', '[[ExFace? &template=`exface.JQueryMobileTemplate`') WHERE id = 10;
UPDATE modx_site_templates SET content = REPLACE(content, '[!ExFace?', '[!ExFace? &template=`exface.JQueryMobileTemplate`') WHERE id = 10;
UPDATE modx_site_templates SET content = REPLACE(content, '&template=`exface.JQueryMobileTemplate` &template=`exface.JQueryMobileTemplate`', '&template=`exface.JQueryMobileTemplate`') WHERE id = 10;
UPDATE modx_site_templates SET content = REPLACE(content, '[[ExFace?', '[[ExFace? &template=`exface.JEasyUiTemplate`') WHERE id IN (5,12);
UPDATE modx_site_templates SET content = REPLACE(content, '[!ExFace?', '[!ExFace? &template=`exface.JEasyUiTemplate`') WHERE id IN (5,12);
UPDATE modx_site_templates SET content = REPLACE(content, '&template=`exface.JEasyUiTemplate` &template=`exface.JEasyUiTemplate`', '&template=`exface.JEasyUiTemplate`') WHERE id IN (5,12);