/* Add the template parameter to all current templates */
UPDATE modx_site_templates SET content = REPLACE(content, '[[ExFace? &template=', '[[ExFace? &facade=');
UPDATE modx_site_templates SET content = REPLACE(content, '[!ExFace? &template=', '[!ExFace? &facade=');

UPDATE modx_site_templates SET content = REPLACE(content, 'JEasyUiTemplate', 'JEasyUIFacade');
UPDATE modx_site_templates SET content = REPLACE(content, 'AdminLteTemplate', 'AdminLTEFacade');
UPDATE modx_site_templates SET content = REPLACE(content, 'JQueryMobileTemplate', 'JQueryMobileFacade');
UPDATE modx_site_templates SET content = REPLACE(content, 'NativeDroid2Template', 'NativeDroid2Facade');
UPDATE modx_site_templates SET content = REPLACE(content, 'OpenUI5Template', 'UI5Facade');

UPDATE modx_site_templates SET content = REPLACE(content, 'exface.AdminLTEFacade.html', 'exface.AdminLTETemplate.html');
UPDATE modx_site_templates SET content = REPLACE(content, 'exface.JEasyUIFacade.html', 'exface.JEasyUITemplate.html');
UPDATE modx_site_templates SET content = REPLACE(content, 'exface.JQueryMobileFacade.html', 'exface.JQueryMobileTemplate.html');
UPDATE modx_site_templates SET content = REPLACE(content, 'exface.NativeDroid2Facade.html', 'exface.NativeDroid2Template.html');
UPDATE modx_site_templates SET content = REPLACE(content, 'exface.UI5FacadeFacade.html', 'exface.UI5FacadeTemplate.html');
