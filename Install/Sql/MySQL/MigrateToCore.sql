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
	default_menu_position,
	replace_page_alias,
	auto_update_disabled,
	created_on,
	modified_on,
	created_by_user_oid,
	modified_by_user_oid,
	parent_oid
) SELECT
     UNHEX(
     	SUBSTR(
     		(SELECT
 				mstc.value
     		FROM `alexa5`.`modx_site_tmplvar_contentvalues` mstc
 				LEFT JOIN `alexa5`.`modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     		WHERE msc.id = mstc.contentid
 				AND mst.name = "ExfacePageUID"
			)
		, 3)
	) as uid,
	msc.pagetitle as name,
    msc.description,
    msc.introtext as intro,
    msc.alias as alias,
    msc.menuindex as menuIndex,
    IF(msc.hidemenu = 1, 0, 1) as menu_visible,
    msc.content as contents,
    UNHEX(
    	SUBSTR(
    		(SELECT
 				mstc.value
 			FROM `alexa5`.`modx_site_tmplvar_contentvalues` mstc
				LEFT JOIN `alexa5`.`modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     		WHERE msc.id = mstc.contentid
 				AND mst.name = "ExfacePageAppAlias"
			)
		, 3)
	) as app_uid,
 	(SELECT
 		mstc.value
     FROM `alexa5`.`modx_site_tmplvar_contentvalues` mstc
 		LEFT JOIN `alexa5`.`modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     WHERE msc.id = mstc.contentid
 		AND mst.name = "ExfacePageDefaultParentAlias"
	) as default_menu_position,
  	(SELECT
		mstc.value
     FROM `alexa5`.`modx_site_tmplvar_contentvalues` mstc
 		LEFT JOIN `alexa5`.`modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     WHERE msc.id = mstc.contentid
 		AND mst.name = "ExfacePageReplaceAlias"
	) as replace_alias,
    IFNULL(
    	(SELECT
 			mstc.value
		FROM `alexa5`.`modx_site_tmplvar_contentvalues` mstc
 			LEFT JOIN `alexa5`.`modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     	WHERE msc.id = mstc.contentid
 			AND mst.name = "ExfacePageDoUpdate"
		)
	, 1) as do_update,
  	from_unixtime(msc.createdon),
  	from_unixtime(msc.editedon),
  	(SELECT u.oid FROM exf_user u INNER JOIN modx_manager_users mu ON mu.username = u.username WHERE mu.id = msc.createdby), 
  	(SELECT u.oid FROM exf_user u INNER JOIN modx_manager_users mu ON mu.username = u.username WHERE mu.id = msc.editedby),
  	IF(
	  	msc.parent > 0, 
	  	UNHEX(
	  		SUBSTR(
	  			(SELECT
					mstc.value
	 			FROM `alexa5`.`modx_site_tmplvar_contentvalues` mstc
					LEFT JOIN `alexa5`.`modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
	 			WHERE msc.parent = mstc.contentid
					AND mst.name = "ExfacePageUID"
				)
			, 3)
		)
	, NULL) AS parent_oid,
	CASE
	    WHEN mst.content LIKE '%exface.OpenUI5Template.modx.html%' THEN 0x11EA2033058CB468AA030205857FEB80
	    WHEN mst.content LIKE '%exface.OpenUI5TemplateMobile.modx.html%' THEN 0x11EA2033058CB54EAA030205857FEB80
	    WHEN mst.content LIKE '%exface.JEasyUITemplate.html%' THEN 0x11EA2033058CB236AA030205857FEB80
	    WHEN mst.content LIKE '%exface.AdminLTETemplate.html%' THEN 0x11EA2033058CB41EAA030205857FEB80
	    WHEN mst.content LIKE '%exface.NativeDroidTemplate.html%' THEN 0x11EA2033058CB3CCAA030205857FEB80
	    WHEN mst.content LIKE '%alexa.RMS.JEasyUiEmbeddedTemplate.html%' THEN 0x11EA2033058CB4B2AA030205857FEB80
 		ELSE NULL
	END AS template_oid
FROM `alexa5`.`modx_site_content` msc
WHERE
(SELECT
 mstc.value
     FROM `alexa5`.`modx_site_tmplvar_contentvalues` mstc
 LEFT JOIN `alexa5`.`modx_site_tmplvars` mst ON mstc.tmplvarid = mst.id
     WHERE msc.id = mstc.contentid
 AND mst.name = "ExfacePageUID") IS NOT NULL
ORDER BY msc.id;