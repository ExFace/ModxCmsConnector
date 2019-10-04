UPDATE modx_site_content SET content = REPLACE(content, 'exface.Core.ShowWidgetPrefilled', 'exface.Core.GoToPage');
UPDATE modx_site_content SET content = REPLACE(content, 'exface.Core.GoToWidget', 'exface.Core.GoToPage');
UPDATE modx_site_content SET content = REPLACE(content, '"document_id":', '"page_id":');
UPDATE modx_site_content SET content = REPLACE(content, '"action_document_id":', '"action_page_id":');