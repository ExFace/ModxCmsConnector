INSERT INTO `modx_system_settings`(`setting_name`, `setting_value`) VALUES ('which_editor','UXONeditor') ON DUPLICATE KEY UPDATE setting_value = 'UXONeditor';
INSERT INTO `modx_system_settings`(`setting_name`, `setting_value`) VALUES ('enable_mootools','0') ON DUPLICATE KEY UPDATE setting_value = '0';
