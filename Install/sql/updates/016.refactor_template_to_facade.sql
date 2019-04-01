/* Add the template parameter to all current templates */
UPDATE modx_site_templates SET content = REPLACE(content, 'ExFace?  &template=', 'ExFace? &facade=');
UPDATE modx_site_templates SET content = REPLACE(content, 'ExFace? &template=', 'ExFace? &facade=');

UPDATE modx_site_templates SET content = REPLACE(content, 'JEasyUiTemplate.JEasyUiTemplate', 'JEasyUIFacade.JEasyUIFacade');
UPDATE modx_site_templates SET content = REPLACE(content, 'AdminLteTemplate.AdminLteTemplate', 'AdminLTEFacade.AdminLTEFacade');
UPDATE modx_site_templates SET content = REPLACE(content, 'JQueryMobileTemplate.JQueryMobileTemplate', 'JQueryMobileFacade.JQueryMobileFacade');
UPDATE modx_site_templates SET content = REPLACE(content, 'NativeDroid2Template.NativeDroid2Template', 'NativeDroid2Facade.NativeDroid2Facade');
UPDATE modx_site_templates SET content = REPLACE(content, 'OpenUI5Template.OpenUI5Template', 'UI5Facade.UI5Facade');

UPDATE modx_site_templates SET content = REPLACE(content, 'exface.AdminLTEFacade.html', 'exface.AdminLTETemplate.html');
UPDATE modx_site_templates SET content = REPLACE(content, 'exface.JEasyUIFacade.html', 'exface.JEasyUITemplate.html');
UPDATE modx_site_templates SET content = REPLACE(content, 'exface.JQueryMobileFacade.html', 'exface.JQueryMobileTemplate.html');
UPDATE modx_site_templates SET content = REPLACE(content, 'exface.NativeDroid2Facade.html', 'exface.NativeDroid2Template.html');
UPDATE modx_site_templates SET content = REPLACE(content, 'exface.UI5FacadeFacade.html', 'exface.UI5FacadeTemplate.html');
