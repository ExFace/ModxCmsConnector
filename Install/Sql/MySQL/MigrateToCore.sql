/* Move Pages to exf_page */
DELETE FROM exf_page;
INSERT INTO exf_page (
	oid,
	`name`,
	description,
	intro,
	alias,
	menu_index,
	menu_visible,
	content,
	app_oid,
	replace_page_oid,
	auto_update_with_app,
	created_on,
	modified_on,
	created_by_user_oid,
	modified_by_user_oid,
	parent_oid,
	page_template_oid,
	published,
	default_menu_parent_oid,
	default_menu_index
) SELECT
-- OID
   UNHEX(
     	SUBSTR(
     		(SELECT
 				mstc.value
     		FROM `modx_site_tmplvar_contentvalues` mstc
 				LEFT JOIN `modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     		WHERE msc.id = mstc.contentid
 				AND mst.name = "ExfacePageUID"
			)
		, 3)
	) as uid,
-- name
	msc.pagetitle as NAME,
-- description
   msc.description,
-- intro
   msc.introtext as intro,
-- alias
   msc.alias as alias,
-- menu_index
   msc.menuindex as menu_index,
-- menu_visible
   IF(msc.hidemenu = 1, 0, 1) as menu_visible,
-- content
   CASE
		WHEN msc.alias = 'login' THEN '{"widget_type":"LoginPrompt","object_alias":"exface.Core.LOGIN_DATA"}'
		WHEN msc.alias = 'not-found' THEN NULL
 		ELSE msc.content
	END AS contents,
-- app_oid
   UNHEX(
    	SUBSTR(
    		(SELECT
 				mstc.value
 			FROM `modx_site_tmplvar_contentvalues` mstc
				LEFT JOIN `modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     		WHERE msc.id = mstc.contentid
 				AND mst.name = "ExfacePageAppAlias"
			)
		, 3)
	) as app_uid,
-- replace_page_oid
	UNHEX(
     	SUBSTR(
     		(SELECT
 				mstc.value
     		FROM `modx_site_tmplvar_contentvalues` mstc
 				LEFT JOIN `modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     		WHERE
			  mstc.contentid = (
     				SELECT 
					  mscrp.id 
					FROM modx_site_content mscrp
					WHERE 
						mscrp.alias = (SELECT
							mstcrp.value
					     FROM `modx_site_tmplvar_contentvalues` mstcrp
					 		LEFT JOIN `modx_site_tmplvars` mstrp ON mstcrp.tmplvarid = mstrp.id
					     WHERE msc.id = mstcrp.contentid
					 		AND mstrp.name = "ExfacePageReplaceAlias"
						)
     			)
 				AND mst.name = "ExfacePageUID"
			)
		, 3)
	),
-- auto_update_with_app
    IFNULL(
    	(SELECT
 			mstc.value
		FROM `modx_site_tmplvar_contentvalues` mstc
 			LEFT JOIN `modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     	WHERE msc.id = mstc.contentid
 			AND mst.name = "ExfacePageDoUpdate"
		)
	, 1) as do_update,
-- created_on
  	from_unixtime(msc.createdon),
-- modified_on
  	from_unixtime(msc.editedon),
-- created_by_user_oid
  	(SELECT u.oid FROM exf_user u INNER JOIN modx_manager_users mu ON mu.username = u.username WHERE mu.id = msc.createdby), 
-- modified_by_user_oid
  	(SELECT u.oid FROM exf_user u INNER JOIN modx_manager_users mu ON mu.username = u.username WHERE mu.id = msc.editedby),
-- parent_oid
  	IF(
	  	msc.parent > 0, 
	  	UNHEX(
	  		SUBSTR(
	  			(SELECT
					mstc.value
	 			FROM `modx_site_tmplvar_contentvalues` mstc
					LEFT JOIN `modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
	 			WHERE msc.parent = mstc.contentid
					AND mst.name = "ExfacePageUID"
				)
			, 3)
		)
	, NULL) AS parent_oid,
-- page_template_oid
	CASE
	    WHEN mst.content LIKE '%exface.OpenUI5Template.modx.html%' THEN 0x11EA2033058CB468AA030205857FEB80
	    WHEN mst.content LIKE '%exface.OpenUI5TemplateMobile.modx.html%' THEN 0x11EA2033058CB54EAA030205857FEB80
	    WHEN mst.content LIKE '%exface.JEasyUITemplate.html%' THEN 0x11EA2033058CB236AA030205857FEB80
	    WHEN mst.content LIKE '%exface.AdminLTETemplate.html%' THEN 0x11EA2033058CB41EAA030205857FEB80
	    WHEN mst.content LIKE '%exface.NativeDroidTemplate.html%' THEN 0x11EA2033058CB3CCAA030205857FEB80
	    WHEN mst.content LIKE '%alexa.RMS.JEasyUiEmbeddedTemplate.html%' THEN 0x11EA2033058CB4B2AA030205857FEB80
 		ELSE NULL
	END AS template_oid,
-- published
	msc.published,
-- default_menu_parent_oid
	UNHEX(
     	SUBSTR(
     		(SELECT
 				mstc.value
     		FROM `modx_site_tmplvar_contentvalues` mstc
 				LEFT JOIN `modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     		WHERE
			  mstc.contentid = (
     				SELECT 
					  mscdmp.id 
					FROM modx_site_content mscdmp
					WHERE 
						mscdmp.alias = (SELECT 
						 		if (INSTR(mstcdmp.value, ':'), LEFT(mstcdmp.value,LOCATE(':',mstcdmp.value) - 1), mstcdmp.value)
					 		FROM `modx_site_tmplvar_contentvalues` mstcdmp
					 			LEFT JOIN `modx_site_tmplvars` mstdmp ON mstcdmp.tmplvarid = mstdmp.id
					     	WHERE msc.id = mstcdmp.contentid
					 			AND mstdmp.name = "ExfacePageDefaultParentAlias"
						)
					LIMIT 1
     			)
 				AND mst.name = "ExfacePageUID"
			)
		, 3)
	),
-- default_menu_index
 	(SELECT 
	 	if (INSTR(mstc.value, ':'), SUBSTRING_INDEX(mstc.value,':',-1), NULL)
     FROM `modx_site_tmplvar_contentvalues` mstc
 		LEFT JOIN `modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     WHERE msc.id = mstc.contentid
 		AND mst.name = "ExfacePageDefaultParentAlias"
	)
FROM `modx_site_content` msc
	LEFT JOIN modx_site_templates mst ON mst.id = msc.template
WHERE
	(SELECT
 		mstc.value
     FROM `modx_site_tmplvar_contentvalues` mstc
 		LEFT JOIN `modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     WHERE 
     	msc.id = mstc.contentid
 		AND mst.name = "ExfacePageUID"
 	) IS NOT NULL
ORDER BY msc.id;

/* Set default password for user models without a password */
UPDATE exf_user u SET 
	u.password = '$2y$10$yu4DmatXeGBHPqBCcvDTteq8bj8kkfBYeRCa8zDcVMAb4mvO9Y3du' 
WHERE 
	u.password IS NULL 
	AND (
		EXISTS (SELECT 1 FROM modx_manager_users mmu WHERE mmu.username = u.username)
		OR EXISTS (SELECT 1 FROM modx_web_users mwu WHERE mwu.username = u.username)
	);