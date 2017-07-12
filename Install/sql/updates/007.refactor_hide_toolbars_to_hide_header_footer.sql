UPDATE modx_site_content SET content = REPLACE(content, 'hide_toolbar_top', 'hide_header');
UPDATE modx_site_content SET content = REPLACE(content, 'hide_toolbar_bottom', 'hide_footer');
UPDATE modx_site_content SET content = REPLACE(content, '"hide_toolbars":true', '"hide_header":true,"hide_footer":true');
UPDATE modx_site_content SET content = REPLACE(content, '"hide_toolbars": true', '"hide_header":true,"hide_footer":true');