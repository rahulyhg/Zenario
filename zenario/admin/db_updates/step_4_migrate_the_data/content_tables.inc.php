<?php
/*
 * Copyright (c) 2017, Tribal Limited
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of Zenario, Tribal Limited nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL TRIBAL LTD BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
if (!defined('NOT_ACCESSED_DIRECTLY')) exit('This file may not be directly accessed');



//Code for converting table data, after the more drastic database structure changes


//Look through the banners that have been created, trying to migrate their frameworks as best we can
//This won't be perfect, but should at least reduce the amount of changes that need to be made manually
if (needRevision(28710)) {
	if ($bannerModuleId = getRow('modules', 'id', array('class_name' => 'zenario_banner', 'status' => 'module_running'))) {
		
		//Look for all Banner Plugins that used More Links
		//Also try and look up the visitor phrase that would be used for the "More Link" text
		$sql = "
			SELECT pi.id, pi.framework, pi.content_id, vp.local_text
			FROM ". DB_NAME_PREFIX. "plugin_instances AS pi
			LEFT JOIN ". DB_NAME_PREFIX. "content_items AS c
			   ON pi.content_id = c.id
			  AND pi.content_type = c.type
			LEFT JOIN ". DB_NAME_PREFIX. "visitor_phrases AS vp
			   ON vp.code = '_FIND_OUT_MORE'
			  AND vp.module_class_name = 'zenario_banner'
			  AND vp.language_id = IFNULL(c.language_id, '". sqlEscape(setting('default_language')). "')
			WHERE pi.framework LIKE '%more_link%' 
			  AND pi.module_id = ". (int) $bannerModuleId;
	
		$result = sqlQuery($sql);
		while ($plugin = sqlFetchAssoc($result)) {
			//A couple of the removed frameworks have direct replacements, so we may as well fix them now
			if ($plugin['framework'] == 'image_then_title_then_text_with_more_link') {
				updateRow('plugin_instances', array('framework' => 'image_then_title_then_text'), $plugin['id']);
			}
			if ($plugin['framework'] == 'title_then_image_then_text_with_more_link') {
				updateRow('plugin_instances', array('framework' => 'title_then_image_then_text'), $plugin['id']);
			}
			
			if (!$plugin['local_text']) {
				$plugin['local_text'] = 'Find out more';
			}
			
			//Insert a new setting for the "find out more" text
			insertRow(
				'plugin_settings',
				array(
					'instance_id' => $plugin['id'],
					'name' => 'more_link_text',
					'nest' => 0,
					'value' => $plugin['local_text'],
					'is_content' => $plugin['content_id']? 'version_controlled_content' : 'synchronized_setting',
					'format' => 'translatable_text'),
				true);
		}
	}
	
	revision(28710);
}


//Similar to above, look for Content Summary Lists that use the "More" link and replace this with a phrase
if (needRevision(29560)) {
	$sql = "
		SELECT pi.id, pi.module_id, pi.content_id, vp.local_text
		FROM ". DB_NAME_PREFIX. "modules AS m
		INNER JOIN ". DB_NAME_PREFIX. "plugin_instances AS pi
		   ON pi.module_id = m.id
		LEFT JOIN ". DB_NAME_PREFIX. "content_items AS c
		   ON pi.content_id = c.id
		  AND pi.content_type = c.type
		LEFT JOIN ". DB_NAME_PREFIX. "visitor_phrases AS vp
		   ON vp.code = '_MORE'
		  AND vp.module_class_name = 'zenario_content_list'
		  AND vp.language_id = IFNULL(c.language_id, '". sqlEscape(setting('default_language')). "')
		WHERE m.class_name LIKE 'zenario_content_list%'";

	$result = sqlQuery($sql);
	while ($plugin = sqlFetchAssoc($result)) {
		if (!$plugin['local_text']) {
			$plugin['local_text'] = 'More...';
		}
		
		//Insert a new setting for the "more" text
		insertRow(
			'plugin_settings',
			array(
				'instance_id' => $plugin['id'],
				'name' => 'more_link_text',
				'nest' => 0,
				'value' => $plugin['local_text'],
				'is_content' => $plugin['content_id']? 'version_controlled_content' : 'synchronized_setting',
				'format' => 'translatable_text'),
			true);
	}
	
	revision(29560);
}


if (needRevision(30130)) {
	foreach (getRowsArray(
		'documents',
		array('id', 'file_id', 'filename', 'file_datetime'),
		array('type' => 'file')
	) as $document) {
		if ($fileDetails = getRow('files', array('filename', 'created_datetime'), array('id' => $document['file_id']))) {
			
			if (!$document['filename']) {
				updateRow('documents', array('filename' => $fileDetails['filename']), array('id' => $document['id']));
			}
			if (!$document['file_datetime']) {
				updateRow('documents', array('file_datetime' => $fileDetails['created_datetime']), array('id' => $document['id']));
			}
		}
	}
	
	revision(30130);
}


//Rename some old site settings with "storekeeper" in the name
if (needRevision(30150)) {
	
	if (setting('storekeeper_date_format') && !setting('organizer_date_format')) {
		setSetting('organizer_date_format', setting('storekeeper_date_format'), true, false);
		deleteRow('site_settings', array('name' => 'storekeeper_date_format'));
	}
	
	revision(30150);
}


//Remove the library_images tables if they were ever created for people running early versions of 7.0.5
if (needRevision(30500)) {
	deleteDataset('library_images');
	
	revision(30500);
}


//Change the checksum column in the files table to use base 64 rather than base 16.
if (needRevision(30600)) {
	$sql = "
		SELECT id, checksum
		FROM ". DB_NAME_PREFIX. "files
		WHERE LENGTH(checksum) = 32";

	$result = sqlQuery($sql);
	while ($file = sqlFetchAssoc($result)) {
		updateRow('files', array('checksum' => base16To64($file['checksum'])), $file['id']);
	}
	
	revision(30600);
}

//Set the "archived" flag in the inline_images table
if (needRevision(30700)) {
	flagImagesInArchivedVersions();
	
	revision(30700);
}


//Scan anything related to a Content Item and sync the inline_images table properly
if (needRevision(30731)) {
	
	$result = getRows('content_items', array('id', 'type', 'visitor_version', 'admin_version'), array(), array('type', 'id'));
	while ($row = sqlFetchAssoc($result)) {
		
		if ($row['visitor_version']) {
			syncInlineFileContentLink($row['id'], $row['type'], $row['visitor_version']);
		}
		if ($row['admin_version'] && $row['admin_version'] != $row['visitor_version']) {
			syncInlineFileContentLink($row['id'], $row['type'], $row['admin_version']);
		}
	}
	
	revision(30731);
}

//Update Organizer's image thumbnails
if (needRevision(31200)) {
	$docstoreDir = setting('docstore_dir');
	
	foreach (array(
		array('thumbnail_180x130_data', 'thumbnail_180x130_width', 'thumbnail_180x130_height', 180, 130),
		array('thumbnail_64x64_data', 'thumbnail_64x64_width', 'thumbnail_64x64_height', 64, 64),
		array('thumbnail_24x23_data', 'thumbnail_24x23_width', 'thumbnail_24x23_height', 24, 23)
	) as $c) {

		$sql = "
			SELECT id, location, path, filename, data, mime_type, width, height
			FROM ". DB_NAME_PREFIX. "files
			WHERE mime_type IN ('image/gif', 'image/png', 'image/jpeg')
			  AND width != 0
			  AND height != 0
			  AND (`". sqlEscape($c[0]). "` IS NULL
				OR `". sqlEscape($c[1]). "` > ". (int) $c[3]. "
				OR `". sqlEscape($c[2]). "` > ". (int) $c[4]. "
				OR (	width > ". (int) $c[3]. "
					AND height > ". (int) $c[4]. "
					AND `". sqlEscape($c[1]). "` < ". (int) $c[3]. "
					AND `". sqlEscape($c[2]). "` < ". (int) $c[4]. "
			))";
		$result = sqlQuery($sql);

		while($img = sqlFetchAssoc($result)) {
			if ($img['location'] == 'docstore') {
				if ($docstoreDir && is_file($docstoreDir. '/'. $img['path'])) {
					$img['data'] = file_get_contents($docstoreDir. '/'. $img['path']. '/'. $img['filename']);
				} else {
					continue;
				}
			}
	
			resizeImageString($img['data'], $img['mime_type'], $img['width'], $img['height'], $c[3], $c[4]);
			$img['data'] = "
				UPDATE ". DB_NAME_PREFIX. "files SET
					`". sqlEscape($c[0]). "` = '". sqlEscape($img['data']). "',
					`". sqlEscape($c[1]). "` = ". (int) $img['width']. ",
					`". sqlEscape($c[2]). "` = ". (int) $img['height']. "
				WHERE id = ". (int) $img['id'];
			sqlUpdate($img['data']);
			unset($img);
		}
	}
	
	revision(31200);
}

//Populate the short checksum field
if (needRevision(31255)) {
	updateRow('files', array('short_checksum' => null), array());
	setSetting('short_checksum_length', 5);
	updateShortChecksums();
	
	revision(31255);
}

//The directory structure for the cache/public/private directories has changed slightly.
//Attempt to remove the last access date for the cleanDownloads() function
//to force it to rerun and create any new directories the next time it is called.
if (needRevision(31260)) {
	if (@file_exists(CMS_ROOT. 'cache/stats/clean_downloads/accessed')) {
		@unlink(CMS_ROOT. 'cache/stats/clean_downloads/accessed');
	}
	
	revision(31260);
}


//Force the yaml files to be rescanned
if (needRevision(31410)) {
	setSetting('yaml_files_last_changed', '');
	
	revision(31410);
}

// Update news content type if exists to have categories by default
if (needRevision(31520)) {
	
	if (checkRowExists('content_types', array('content_type_id' => 'news'))) {
		updateRow('content_types', array('enable_categories' => 1), array('content_type_id' => 'news'));
	}
	revision(31520);
}

// Force old site disabled messages that mention Tribiq to be updated
if (needRevision(31542)) {
	
	if (strpos(setting('site_disabled_title'), 'Welcome to Tribiq CMS') !== false) {
		setSetting('site_disabled_title', 'Welcome');
	}
	if (strpos(setting('site_disabled_message'), 'A Tribiq CMS site') !== false) {
		setSetting('site_disabled_message', '<p>A site is being built at this location.</p><p><span class="x-small">If you are the Site Administrator please <a href="[[admin_link]]">click here</a> to manage your site.</span></p>');
	}
	
	revision(31542);
}

// Try to update email templates module_class_name from the code name
if (needRevision(32025)) {
	$result = getRows('email_templates', array('id', 'code', 'module_class_name'), array());
	while ($row = sqlFetchAssoc($result)) {
		if (!$row['module_class_name']) {
			$codeParts = explode('__', $row['code']);
			if ($codeParts) {
				$class_name = $codeParts[0];
				if (checkRowExists('modules', array('class_name' => $class_name))) {
					updateRow('email_templates', array('module_class_name' => $class_name), $row['id']);
				}
			}
		}
	}
	revision(32025);
}

//Migrate the caching site settings to the new format
if (needRevision(32160)) {
	
	if (setting('cache_ajax')) {
		setSetting('cache_css_js_wrappers', 1);
		
		if (setting('cache_web_pages')) {
			setSetting('caching_enabled', 1);
		}
	}
	
	revision(32160);
}

//Add the new admin_domain site setting, and
//migrate the old admin_use_ssl_port site setting to the new format
if (needRevision(33200)) {
	
	if (!setting('admin_domain')
	 && ($primaryDomain = setting('primary_domain'))) {
		if (setting('admin_use_ssl')) {
			
			$primaryDomain = explode(':', $primaryDomain);
			$adminDomain = $primaryDomain[0];
			
			if (setting('admin_use_ssl_port')) {
				$adminDomain .= ':'. setting('admin_use_ssl_port');
			
			} elseif (!empty($primaryDomain[1])) {
				$adminDomain .= ':'. $primaryDomain[1];
			
			} else {
				$adminDomain .= ':'. 443;
			}
			
			setSetting('admin_domain', $adminDomain);
		} else {
			setSetting('admin_domain', $primaryDomain);
		}
	}
	
	unset($primaryDomain);
	unset($adminDomain);
	revision(33200);
}

//Make sure that the admin_domain_is_public option is set by default
if (needRevision(33270)) {
	setSetting('admin_domain_is_public', 1);
	revision(33270);
}

//trim() all of the codes in the the visitor phrases table
if (needRevision(33760)) {
	//Attempt to find a record of the phrase in the database
	$sql = "
		SELECT id, code
		FROM ". DB_NAME_PREFIX. "visitor_phrases
		WHERE code LIKE ' %'
		   OR code LIKE '% '";

	$result = sqlQuery($sql);
	while ($row = sqlFetchAssoc($result)) {
		//Check the trimmed phrase doesn't already exist
		if (!checkRowExists('visitor_phrases', array('code' => trim($row['code']), 'id' => $row['id']))) {
			//Change the code
			updateRow('visitor_phrases', array('code' => trim($row['code'])), array('id' => $row['id']), $ignore = true);
		}
	}
	revision(33760);
}


//This update is aimed at people who are running SVN, and had their first two template files renamed
//when they did an svn up.
//Look for the old names in the database, and check to see if they are missing in the filesystem.
//If so, change them to the new names
if (needRevision(33945)) {
	foreach (array('Home page' => 'L02', 'Text-heavy' => 'L01') as $oldBaseName => $newBaseName) {
		if (file_exists(CMS_ROOT. 'zenario_custom/templates/grid_templates/'. $newBaseName. '.tpl.php')
		 && !file_exists(CMS_ROOT. 'zenario_custom/templates/grid_templates/'. $oldBaseName. '.tpl.php')) {
			updateRow(
				'layouts',
				array('file_base_name' => $newBaseName),
				array('file_base_name' => $oldBaseName, 'family_name' => 'grid_templates'),
				true);
		}
	}
	revision(33945);
}

//Fix a bug in more recent installs where the short_checksum_length was not set!
if (needRevision(34050)) {
	if (!setting('short_checksum_length')
	 || 5 > (int) setting('short_checksum_length')) {
		
		setSetting('short_checksum_length', 5);
		updateShortChecksums();
	}
	
	revision(34050);
}


//Populate the menu_hierarchy table if it's not populated already
if (needRevision(34150)) {
	recalcAllMenuHierarchy();
	revision(34150);
}

//Default existing sites to "development" mode (i.e. the existing functionality)
if (needRevision(34630)) {
	setSetting('site_mode', 'development');
	
	revision(34630);
}

//Attempt to clear the cache/frameworks directory
if (needRevision(36380)) {
	if (is_dir(CMS_ROOT. 'cache/frameworks')
	 && !windowsServer()
	 && execEnabled()) {
		exec('rm -r '. escapeshellarg(CMS_ROOT. 'cache/frameworks'));
		cleanDownloads();
	}
	
	revision(36380);
}

//We've added some new columns to the template_slot_link table, and made some changes to the format of our .tpl files.
//Try to load the information from each layout on the disk, and re-render the files if they are writable.
if (needRevision(36995)) {
	
	require_once CMS_ROOT. 'zenario/admin/grid_maker/grid_maker.inc.php';
	
	//Look for all grid layouts
	foreach (getRowsArray(
		'layouts',
		array('layout_id', 'family_name', 'file_base_name'),
		array('family_name' => 'grid_templates')
	) as $layout) {
		
		//Attempt to read the grid data from the template file
		if (($data = zenario_grid_maker::readLayoutCode($layout['layout_id']))
		 && (!empty($data['cells']))) {
			
			//Attempt to regenerate the .tpl and .css files.
			//This may fail (e.g. if the files were not writable), but even if it fails it may still return
			//the slot information.
			$slots = array();
			zenario_grid_maker::generateDirectory($data, $slots, $writeToFS = true, $preview = false, $layout['file_base_name']);
			
			//Update the slot information in the database
			if (!empty($slots)) {
				zenario_grid_maker::updateMetaInfoInDB($data, $slots, $layout);
			}
			unset($data);
			unset($slots);
		}
	}

	
	revision(36995);
}