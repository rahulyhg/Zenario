<?php
/*
 * Copyright (c) 2018, Tribal Limited
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


class zenario_common_features__admin_boxes__content extends ze\moduleBaseClass {

	public function fillAdminBox($path, $settingGroup, &$box, &$fields, &$values) {
		//Include an option to create a Menu Node and/or Content Item as a new child of an existing menu Node
		if ($box['key']['id_is_menu_node_id'] || $box['key']['id_is_parent_menu_node_id']) {
	
			if ($box['key']['id'] && $box['key']['id_is_parent_menu_node_id']) {
				//Create a new Content Item/Menu Node under an existing one
				$box['key']['target_menu_parent'] = $box['key']['id'];
		
				$box['key']['target_menu_section'] = ze\row::get('menu_nodes', 'section_id', $box['key']['id']);
	
			} elseif ($box['key']['id'] && ($menuContentItem = ze\menu::getContentItem($box['key']['id']))) {
				//Edit an existing Content Item based on its Menu Node
				$box['key']['cID'] = $menuContentItem['equiv_id'];
				$box['key']['cType'] = $menuContentItem['content_type'];
				ze\content::langEquivalentItem($box['key']['cID'], $box['key']['cType'], ze::ifNull($box['key']['target_language_id'], ($_GET['languageId'] ?? false), ze::$defaultLang));
				$box['key']['source_cID'] = $box['key']['cID'];
		
				$box['key']['target_menu_section'] = ze\row::get('menu_nodes', 'section_id', $box['key']['id']);
		
			} else {
				$box['key']['target_menu_section'] = ze::ifNull($box['key']['target_menu_section'], ($_REQUEST['sectionId'] ?? false), ($_REQUEST['refiner__section'] ?? false));
			}
			$box['key']['id'] = false;
		}

		if ($path == 'zenario_content') {
			//Include the option to duplicate a Content Item based on a MenuId
			if ($box['key']['duplicate_from_menu']) {
				//Handle the case where a language id is in the primary key
				if ($box['key']['id'] && !is_numeric($box['key']['id']) && ($_GET['refiner__menu_node_translations'] ?? false)) {
					$box['key']['target_language_id'] = $box['key']['id'];
					$box['key']['id'] = $_GET['refiner__menu_node_translations'] ?? false;
		
				} elseif (is_numeric($box['key']['id']) && ($_GET['refiner__language'] ?? false)) {
					$box['key']['target_language_id'] = $_GET['refiner__language'] ?? false;
				}
		
				if ($menuContentItem = ze\menu::getContentItem($box['key']['id'])) {
					$box['key']['source_cID'] = $menuContentItem['equiv_id'];
					$box['key']['cType'] = $menuContentItem['content_type'];
					$box['key']['id'] = false;
		
				} else {
					echo ze\admin::phrase('No content item was found for this menu node');
					exit;
				}
	
			//Include the option to duplicate to create a ghost in an Translation Chain,
			//and handle the case where a language id is in the primary key
			} else
			//Version for opening from the "translation chain" panel in Organizer:
			if (
				$box['key']['translate']
			 && ($_REQUEST['refinerName'] ?? false) == 'zenario_trans__chained_in_link'
			 && !ze\content::getCIDAndCTypeFromTagId($box['key']['source_cID'], $box['key']['cType'], $box['key']['id'])
			 && ze\content::getCIDAndCTypeFromTagId($box['key']['source_cID'], $box['key']['cType'], ($_REQUEST['refiner__zenario_trans__chained_in_link'] ?? false))
			) {
				$box['key']['target_language_id'] = $box['key']['id'];
				$box['key']['id'] = null;
			} else
			//Version for opening from the "translation chain" panel in the menu area in Organizer:
			if (
				$box['key']['translate']
			 && ($_REQUEST['refinerName'] ?? false) == 'zenario_trans__chained_in_link__from_menu_node'
			 && ($_REQUEST['equivId'] ?? false)
			 && ($_REQUEST['cType'] ?? false)
			 && ($_REQUEST['language'] ?? false)
			) {
				$box['key']['target_language_id'] = $box['key']['id'];
				$box['key']['source_cID'] = $_REQUEST['equivId'] ?? false;
				$box['key']['id'] = null;
			} else
			//Version for opening from the Admin Toolbar
			if (
				$box['key']['translate']
			 && ($_REQUEST['cID'] ?? false) && ($_REQUEST['cType'] ?? false)
			 && !ze\content::getCIDAndCTypeFromTagId($box['key']['source_cID'], $box['key']['cType'], $box['key']['id'])
			) {
				$box['key']['target_language_id'] = $box['key']['id'];
				$box['key']['id'] = null;
				$box['key']['source_cID'] = $_REQUEST['cID'] ?? false;
				$box['key']['cType'] = $_REQUEST['cType'] ?? false;
				$box['key']['cID'] = '';
			}
		}


		//If creating a new Content Item from the Content Items (and missing translations) in Language Panel,
		//or the Content Items in the Language X Panel, don't allow the language to be changed
		if (($_GET['refinerName'] ?? false) == 'language'
		 || (isset($_GET['refiner__language_equivs']) && ($_GET['refiner__language'] ?? false))) {
			$box['key']['target_language_id'] = $_GET['refiner__language'] ?? false;
		}
		
		
		//Only allow the language to be changed when duplicating or translating
		$lockLanguageId = false;
		if ($box['key']['target_language_id'] || $box['key']['duplicate'] || $box['key']['translate']) {
			$lockLanguageId = true;
		}

		//Populate the language select list
		ze\contentAdm::getLanguageSelectListOptions($fields['meta_data/language_id']);

		//Set up the primary key from the requests given
		if ($box['key']['id'] && !$box['key']['cID']) {
			ze\content::getCIDAndCTypeFromTagId($box['key']['cID'], $box['key']['cType'], $box['key']['id']);

		} elseif (!$box['key']['id'] && $box['key']['cID'] && $box['key']['cType']) {
			$box['key']['id'] = $box['key']['cType'].  '_'. $box['key']['cID'];
		}

		if ($box['key']['cID'] && !$box['key']['cVersion']) {
			$box['key']['cVersion'] = ze\content::latestVersion($box['key']['cID'], $box['key']['cType']);
		}

		if ($box['key']['cID'] && !$box['key']['source_cID']) {
			$box['key']['source_cID'] = $box['key']['cID'];
			$box['key']['source_cVersion'] = $box['key']['cVersion'];

		} elseif ($box['key']['source_cID'] && !$box['key']['source_cVersion']) {
			$box['key']['source_cVersion'] = ze\content::latestVersion($box['key']['source_cID'], $box['key']['cType']);
		}

		//If we're duplicating a Content Item, check to see if it has a Menu Node as well
		if ($box['key']['duplicate'] || $box['key']['translate']) {
			$box['key']['cID'] = $box['key']['cVersion'] = false;
	
			if ($menu = ze\menu::getFromContentItem($box['key']['source_cID'], $box['key']['cType'])) {
				$box['key']['target_menu_parent'] = $menu['parent_id'];
				$box['key']['target_menu_section'] = $menu['section_id'];
			}
		}

		//Enforce a specific Content Type
		$box['key']['target_cType'] = $_REQUEST['refiner__content_type'] ?? $box['key']['cType'] ?? false;
		
		if (!empty($box['key']['create_from_toolbar'])) {
			$fields['meta_data/language_id']['disabled'] = true;
		}
		
		//Set the from_cID if the source_cID is set
		if ($box['key']['source_cID']) {
			$box['key']['from_cID'] = $box['key']['source_cID'];
			$box['key']['from_cType'] = $box['key']['cType'];
		}

		$contentType = ze\row::get('content_types', true, $box['key']['cType']);

		$content = $version = $status = $tag = false;
	
		//Specific Logic for Full Create
		//Try to load details on the source Content Item, if one is set
		if ($box['key']['source_cID']) {
			$content =
				ze\row::get(
					'content_items',
					['id', 'type', 'tag_id', 'language_id', 'alias', 'visitor_version', 'admin_version', 'status'],
					['id' => $box['key']['source_cID'], 'type' => $box['key']['cType']]);
		}


		if ($content) {
			if ($box['key']['duplicate'] || $box['key']['translate']) {
				//Don't allow the layout to be changed when duplicating
				$fields['meta_data/layout_id']['readonly'] = true;
				
				if ($box['key']['translate']) {
					$values['meta_data/alias'] = $content['alias'];
					$box['tabs']['categories']['hidden'] = true;
					$box['tabs']['privacy']['hidden'] = true;
		
					if (!ze::setting('translations_different_aliases')) {
						$fields['meta_data/alias']['readonly'] = true;
						$values['meta_data/actual_alias'] = ze\content::formatTag($content['id'], $content['type'], $content['alias'], $box['key']['target_language_id']);
						$box['tabs']['meta_data']['fields']['alias']['note_below'] = ze\admin::phrase('Note: on this site, aliases are the same on all content items in a translation chain.');
					}
				}
				
				
				//Check to see if there are any library plugins on this page set at the item level
				$slots = [];
				ze\plugin::slotContents($slots, $box['key']['source_cID'], $box['key']['cType'], $box['key']['source_cVersion'],
					$layoutId = false, $templateFamily = false, $templateFileBaseName = false,
					$specificInstanceId = false, $specificSlotName = false, $ajaxReload = false,
					$runPlugins = false
				);
				
				$numPlugins = 0;
				foreach ($slots as $slotName => $slot) {
					if (!empty($slot['instance_id'])
					 && empty($slot['instance_id']['content_id'])
					 && $slot['level'] == 1) {
						
						$instance = ze\plugin::details($slot['instance_id']);
						
						++$numPlugins;
						$suffix = '__'. $numPlugins;
						$values['plugins/slotname'. $suffix] = $slotName;
						$values['plugins/module'. $suffix] = ze\module::displayName($slot['module_id']);
						$values['plugins/instance_id'. $suffix] = $slot['instance_id'];
						$values['plugins/plugin'. $suffix] = $instance['instance_name'];
						$values['plugins/new_name'. $suffix] =  ze\admin::phrase('[[name]] (copy)', $instance);
					}
				}
				
				//If there are, show the plugins tab, with options for each one
				if ($numPlugins) {
					$box['tabs']['plugins']['hidden'] = false;
					
					$fields['plugins/desc']['snippet']['p'] =
						ze\admin::nphrase('There is 1 library plugin in use on this content item. Please select what you wish to do with this.',
							'There are [[count]] library plugins in use on this content item. Please select what you wish to do with them.',
							$numPlugins
						);
						
					
					$changes = [];
					ze\tuix::setupMultipleRows(
						$box, $fields, $values, $changes, $filling = true,
						$box['tabs']['plugins']['custom_template_fields'],
						$numPlugins,
						$minNumRows = 0,
						$tabName = 'plugins'
					);
				}
				
	
			} else {
				//The options to set the alias, categories or privacy (if it is there!) should be hidden when not creating something
				$values['meta_data/actual_alias'] = ze\content::formatTag($content['id'], $content['type'], $content['alias'], $content['language_id']);
				$fields['meta_data/alias']['hidden'] = true;
				$box['tabs']['categories']['hidden'] = true;
				$box['tabs']['privacy']['hidden'] = true;
				
				$box['identifier']['css_class'] = ze\contentAdm::getItemIconClass($content['id'], $content['type'], true, $content['status']);
			}
	
			$values['meta_data/language_id'] = $content['language_id'];
	
			$fields['meta_data/layout_id']['pick_items']['path'] = 
				'zenario__layouts/panels/layouts/refiners/content_type//' . $content['type']. '//';
	
			if ($version =
				ze\row::get(
					'content_item_versions',
					true,
					['id' => $box['key']['source_cID'], 'type' => $box['key']['cType'], 'version' => $box['key']['source_cVersion']])
			) {
				$values['meta_data/title'] = $version['title'];
				$values['meta_data/description'] = $version['description'];
				$values['meta_data/keywords'] = $version['keywords'];
				$values['meta_data/release_date'] = $version['release_date'];
				$values['meta_data/writer_id'] = $version['writer_id'];
				$values['meta_data/writer_name'] = $version['writer_name'];
				$values['meta_data/content_summary'] = $version['content_summary'];
				$values['meta_data/layout_id'] = $version['layout_id'];
				#$values['meta_data/in_sitemap'] = $version['in_sitemap'];
				$values['meta_data/exclude_from_sitemap'] = !$version['in_sitemap'];
				$values['css/css_class'] = $version['css_class'];
				$values['css/background_image'] = $version['bg_image_id'];
				$values['css/bg_color'] = $version['bg_color'];
				$values['css/bg_position'] = $version['bg_position'];
				$values['css/bg_repeat'] = $version['bg_repeat'];
				$values['file/file'] = $version['file_id'];
		
				if ($box['key']['cID'] && $contentType['enable_summary_auto_update']) {
					$values['meta_data/lock_summary_view_mode'] =
					$values['meta_data/lock_summary_edit_mode'] = $version['lock_summary'];
					$fields['meta_data/lock_summary_view_mode']['hidden'] =
					$fields['meta_data/lock_summary_edit_mode']['hidden'] = false;
				}
		
				if (isset($box['tabs']['categories']['fields']['categories'])) {
					ze\categoryAdm::setupFABCheckboxes(
						$fields['categories/categories'], true,
						$box['key']['source_cID'], $box['key']['cType'], $box['key']['source_cVersion']);
				}
		
				$tag = ze\content::formatTag($box['key']['source_cID'], $box['key']['cType'], ($content['alias'] ?? false));
		
				$status = ze\admin::phrase('archived');
				if ($box['key']['source_cVersion'] == $content['visitor_version']) {
					$status = ze\admin::phrase('published');
		
				} elseif ($box['key']['source_cVersion'] == $content['admin_version']) {
					if ($content['admin_version'] > $content['visitor_version']) {
						$status = ze\admin::phrase('draft');
					} elseif ($content['status'] == 'hidden' || $content['status'] == 'hidden_with_draft') {
						$status = ze\admin::phrase('hidden');
					} elseif ($content['status'] == 'trashed' || $content['status'] == 'trashed_with_draft') {
						$status = ze\admin::phrase('trashed');
					}
				}
			}
		} else {
			//If we are enforcing a specific Content Type, ensure that only layouts of that type can be picked
			if ($box['key']['target_cType']) {
				$fields['meta_data/layout_id']['pick_items']['path'] =
					'zenario__layouts/panels/layouts/refiners/content_type//'. $box['key']['target_cType']. '//';
				
				
				//T10208, Creating content items: auto-populate release date and author where used
				$contentTypeDetails = ze\contentAdm::cTypeDetails($box['key']['target_cType']);

				if ($contentTypeDetails['writer_field'] != 'hidden'
				 && isset($fields['meta_data/writer_id'])
				 && ($adminDetails = ze\admin::details(ze\admin::id()))) {
					$values['meta_data/writer_id'] = ze\admin::id();
					$values['meta_data/writer_name'] = $adminDetails['first_name']. ' '. $adminDetails['last_name'];
				}

				if ($contentTypeDetails['release_date_field'] != 'hidden'
				 && isset($fields['meta_data/release_date'])) {
					$values['meta_data/release_date'] = ze\date::ymd();
				}
			}
		}


		//We should have loaded or found the cID by now, if this was for editing an existing content item.
		//If there's no cID then we're creating a new content item
		if ($box['key']['cID']) {
			//Require _PRIV_VIEW_CONTENT_ITEM_SETTINGS for viewing an existing content item's settings
			ze\priv::exitIfNot('_PRIV_VIEW_CONTENT_ITEM_SETTINGS');

		} elseif ($box['key']['translate']) {
			//Require _PRIV_CREATE_TRANSLATION_FIRST_DRAFT for creating a translation
			if (!ze\priv::onLanguage('_PRIV_CREATE_TRANSLATION_FIRST_DRAFT', $box['key']['target_language_id'])) {
				exit;
			}

		} else {
			//Otherwise require _PRIV_CREATE_FIRST_DRAFT for creating a new content item
			ze\priv::exitIfNot('_PRIV_CREATE_FIRST_DRAFT');
		}



		//Set default values
		if ($content) {
			if ($box['key']['duplicate'] || $box['key']['translate']) {
				$values['meta_data/language_id'] = ze::ifNull($box['key']['target_language_id'], ze::ifNull($_GET['languageId'] ?? false, ($_GET['language'] ?? false), $content['language_id']));
			}
		} else {
			$values['meta_data/language_id'] = ze::ifNull($box['key']['target_language_id'], ($_GET['languageId'] ?? false), ze::$defaultLang);
		}
		
		if (!$version) {
			//Attempt to work out the default template and Content Type for a new Content Item
			if (($layoutId = ze::ifNull($box['key']['target_template_id'], ($_GET['refiner__template'] ?? false)))
			 && ($box['key']['cType'] = ze\row::get('layouts', 'content_type', $layoutId))) {
		
	
			} elseif ($box['key']['target_menu_parent']
				   && ($cItem = ze\row::get('menu_nodes', ['equiv_id', 'content_type'], ['id' => $box['key']['target_menu_parent'], 'target_loc' => 'int']))
				   && ($cItem['admin_version'] = ze\row::get('content_items', 'admin_version', ['id' => $cItem['equiv_id'], 'type' => $cItem['content_type']]))
				   && ($layoutId = ze\content::layoutId($cItem['equiv_id'], $cItem['content_type'], $cItem['admin_version']))) {
		
				$box['key']['cType'] = $cItem['content_type'];
	
			} else {
				$box['key']['cType'] = ($box['key']['target_cType'] ?: ($box['key']['cType'] ?: 'html'));
				$layoutId = $contentType['default_layout_id'];
			}
			$contentType = ze\row::get('content_types', true, $box['key']['cType']);
			
			$values['meta_data/layout_id'] = $layoutId;
			
			if (isset($box['tabs']['categories']['fields']['categories'])) {
				
				ze\categoryAdm::setupFABCheckboxes($box['tabs']['categories']['fields']['categories'], true);
		
				if ($categories = $_GET['refiner__category'] ?? false) {
					
					$categories = ze\ray::explodeAndTrim($categories);
					$inCategories = array_flip($categories);
			
					foreach ($categories as $catId) {
						$categoryAncestors = [];
						ze\categoryAdm::ancestors($catId, $categoryAncestors);
				
						foreach ($categoryAncestors as $catAnId) {
							if (!isset($inCategories[$catAnId])) {
								$categories[] = $catAnId;
							}
						}
					}
			
					$box['tabs']['categories']['fields']['categories']['value'] = implode(',', $categories);
				}
			}
		}
		
		if (!$version && $box['key']['target_alias']) {
			$values['meta_data/alias'] = $box['key']['target_alias'];
		}
		if (!$version && $box['key']['target_title']) {
			$values['meta_data/title'] = $box['key']['target_title'];
		}

		//Don't let the language be changed if this Content Item already exists, or will be placed in a set language for the menu
		if (!$box['key']['duplicate'] && ($content || $box['key']['target_menu_section'])) {
			$lockLanguageId = true;
		}

		if (isset($box['tabs']['categories']['fields']['desc'])) {
			$box['tabs']['categories']['fields']['desc']['snippet']['html'] = 
				ze\admin::phrase('You can put content item(s) into one or more categories. (<a[[link]]>Define categories</a>.)',
					['link' => ' href="'. htmlspecialchars(ze\link::absolute(). 'zenario/admin/organizer.php#zenario__content/categories'). '" target="_blank"']);
		
				if (ze\row::exists('categories', [])) {
					$fields['categories/no_categories']['hidden'] = true;
				} else {
					$fields['categories/categories']['hidden'] = true;
				}
		}



		//Turn edit mode on if we will be creating a new Content Item
		if (!$box['key']['cID'] || $box['key']['cID'] != $box['key']['source_cID']) {
			foreach ($box['tabs'] as $i => &$tab) {
				if (is_array($tab) && isset($tab['edit_mode'])) {
					$tab['edit_mode']['enabled'] = true;
					$tab['edit_mode']['on'] = true;
					$tab['edit_mode']['always_on'] = true;
				}
			}

		//And turn it off if we are looking at an archived version of an existing Content Item, or a locked Content Item
		} elseif ($box['key']['cID']
			   && $content
			   && ($box['key']['cVersion'] < $content['admin_version'] || !ze\priv::check('_PRIV_EDIT_DRAFT', $box['key']['cID'], $box['key']['cType']))
		) {
			foreach ($box['tabs'] as $i => &$tab) {
				if (is_array($tab) && isset($tab['edit_mode'])) {
					$tab['edit_mode']['enabled'] = false;
				}
			}

		} else {
			foreach ($box['tabs'] as $i => &$tab) {
				if (is_array($tab) && isset($tab['edit_mode'])) {
					$tab['edit_mode']['enabled'] = true;
				}
			}
		}

		if ($box['key']['source_cID']) {
			if ($box['key']['cID'] != $box['key']['source_cID']) {
				if ($box['key']['target_language_id'] && $box['key']['target_language_id'] != $content['language_id']) {
					$box['title'] =
						ze\admin::phrase('Creating a translation in "[[lang]]" of the content item "[[tag]]" ([[old_lang]]).',
							['tag' => $tag, 'old_lang' => $content['language_id'], 'lang' => ze\lang::name($box['key']['target_language_id'])]);
			
				} elseif ($box['key']['source_cVersion'] < $content['admin_version']) {
					$box['title'] =
						ze\admin::phrase('Duplicating the [[status]] (version [[version]]) Content Item "[[tag]]"',
							['tag' => $tag, 'status' => $status, 'version' => $box['key']['source_cVersion']]);
				} else {
					$box['title'] =
						ze\admin::phrase('Duplicating the [[status]] content item "[[tag]]"',
							['tag' => $tag, 'status' => $status]);
				}
			} else {
				if ($box['key']['source_cVersion'] < $content['admin_version']) {
					$box['title'] =
						ze\admin::phrase('Viewing metadata of content item "[[tag]]", version [[version]] ([[status]])',
							['tag' => $tag, 'status' => $status, 'version' => $box['key']['source_cVersion']]);
				} else {
					$box['title'] =
						ze\admin::phrase('Editing metadata (version-controlled) of content item "[[tag]]", version [[version]] ([[status]])',
							['tag' => $tag, 'status' => $status, 'version' => $box['key']['source_cVersion']]);
				}
			}
		} elseif (($box['key']['target_cType'] || (!$box['key']['id'] && $box['key']['cType'])) && $contentType) {
			$box['title'] = ze\admin::phrase('Creating a content item, [[content_type_name_en]]', $contentType);
		}

		if ($lockLanguageId) {
			$box['tabs']['meta_data']['fields']['language_id']['readonly'] = true;
		}


		//Attempt to load the content into the content tabs for each WYSIWYG Editor
		if (isset($box['tabs']['content1'])) {
			$i = 0;
			$slots = [];
			if ($box['key']['source_cID']
			 && $box['key']['cType']
			 && $box['key']['source_cVersion']
			 && ($slots = ze\contentAdm::mainSlot($box['key']['source_cID'], $box['key']['cType'], $box['key']['source_cVersion'], false, false))
			 && (!empty($slots))) {
	
				//Set the content for each slot, with a limit of four slots
				foreach ($slots as $slot) {
					if (++$i > 4) {
						break;
					}
					$values['content'. $i. '/content'] =
						ze\contentAdm::getContent($box['key']['source_cID'], $box['key']['cType'], $box['key']['source_cVersion'], $slot);
					$fields['content'. $i. '/content']['pre_field_html'] =
						'<div class="zfab_content_in">'. ze\admin::phrase('Content in [[slotName]]:', ['slotName' => $slot]). '</div>';
				}
			}
		}

		// Hide categories if not enabled by cType
		if (!$contentType['enable_categories']) {
			$box['tabs']['categories']['hidden'] = true;
		}


		if ($box['key']['cID']) {
			$box['key']['id'] = $box['key']['cType']. '_'. $box['key']['cID'];
			$fields['meta_data/layout_id']['hidden'] = true;
		} else {
			$box['key']['id'] = null;
		}
		
		
		$this->fillMenu($box, $fields, $values, $contentType, $content, $version);
	}
	

	public function formatAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		$box['tabs']['file']['hidden'] = true;

		if (!$box['key']['cID']) {
			if ($values['meta_data/layout_id']) {
				$box['key']['cType'] = ze\row::get('layouts', 'content_type', $values['meta_data/layout_id']);
			}
		}
		$fields['css/background_image']['side_note'] = '';
		$fields['css/bg_color']['side_note'] = '';
		$fields['css/bg_position']['side_note'] = '';
		$fields['css/bg_repeat']['side_note'] = '';
		$box['tabs']['meta_data']['notices']['archived_template']['show'] = false;

		if ($values['meta_data/layout_id']
		 && ($layout = ze\content::layoutDetails($values['meta_data/layout_id']))) {
	
			if ($layout['status'] != 'active') {
				$box['tabs']['meta_data']['notices']['archived_template']['show'] = true;
			}
	
			if ($layout['bg_image_id']) {
				$fields['css/background_image']['side_note'] = htmlspecialchars(
					ze\admin::phrase("Setting a background image here will override the background image set on this item's layout ([[id_and_name]]).", $layout));
			}
			if ($layout['bg_color']) {
				$fields['css/bg_color']['side_note'] = htmlspecialchars(
					ze\admin::phrase("Setting a background color here will override the background color set on this item's layout ([[id_and_name]]).", $layout));
			}
			if ($layout['bg_position']) {
				$fields['css/bg_position']['side_note'] = htmlspecialchars(
					ze\admin::phrase("Setting a background position here will override the background position set on this item's layout ([[id_and_name]]).", $layout));
			}
			if ($layout['bg_repeat']) {
				$fields['css/bg_repeat']['side_note'] = htmlspecialchars(
					ze\admin::phrase("Setting an option here will override the option set on this item's layout ([[id_and_name]]).", $layout));
			}
		}
		
		$fields['meta_data/description']['hidden'] = false;
		$fields['meta_data/writer']['hidden'] = false;
		$fields['meta_data/keywords']['hidden'] = false;
		$fields['meta_data/release_date']['hidden'] = false;
		$fields['meta_data/content_summary']['hidden'] = false;
		if ($box['key']['cType'] && $details = ze\contentAdm::cTypeDetails($box['key']['cType'])) {
			if ($details['description_field'] == 'hidden') {
				$fields['meta_data/description']['hidden'] = true;
			}
			if ($details['keywords_field'] == 'hidden') {
				$fields['meta_data/keywords']['hidden'] = true;
			}
			if ($details['release_date_field'] == 'hidden') {
				$fields['meta_data/release_date']['hidden'] = true;
			}
			if ($details['writer_field'] == 'hidden') {
				$fields['meta_data/writer_id']['hidden'] = true;
				$fields['meta_data/writer_name']['hidden'] = true;
			}
			if ($details['summary_field'] == 'hidden') {
				$fields['meta_data/content_summary']['hidden'] = true;
			}
		}

		if (isset($box['tabs']['meta_data']['fields']['writer_id'])
		 && !ze\ring::engToBoolean($box['tabs']['meta_data']['fields']['writer_id']['hidden'] ?? false)) {
			if ($values['meta_data/writer_id']) {
				if (ze\ring::engToBoolean($box['tabs']['meta_data']['edit_mode']['on'] ?? false)) {
					if (empty($box['tabs']['meta_data']['fields']['writer_name']['current_value'])
					 || empty($box['tabs']['meta_data']['fields']['writer_id']['last_value'])
					 || $box['tabs']['meta_data']['fields']['writer_id']['last_value'] != $values['meta_data/writer_id']) {
						$adminDetails = ze\admin::details($values['meta_data/writer_id']);
						$box['tabs']['meta_data']['fields']['writer_name']['current_value'] = $adminDetails['first_name'] . " " . $adminDetails['last_name'];
					}
				}
		
				$fields['meta_data/writer_name']['hidden'] = false;
			} else {
				$fields['meta_data/writer_name']['hidden'] = true;
				$box['tabs']['meta_data']['fields']['writer_name']['current_value'] = "";
			}
	
			$box['tabs']['meta_data']['fields']['writer_id']['last_value'] = $values['meta_data/writer_id'];
		}


		if ($box['key']['cID']) {
			$languageId = ze\content::langId($box['key']['cID'], $box['key']['cType']);
			$specialPage = ze\content::isSpecialPage($box['key']['cID'], $box['key']['cType']);
		} else {
			$languageId = ($values['meta_data/language_id'] ?: ($box['key']['target_template_id'] ?: ze::$defaultLang));
			$specialPage = false;
		}

		$titleCounterHTML = '
			<div class="snippet__title" >
				<div id="snippet__title_length" class="[[initial_class_name]]">
					<span id="snippet__title_counter">[[initial_characters_count]]</span>
				</div>
			</div>';

		$descriptionCounterHTML = '
			<div class="snippet__description" >
				<div id="snippet__description_length" class="[[initial_class_name]]">
					<span id="snippet__description_counter">[[initial_characters_count]]</span>
				</div>
			</div>';

		$keywordsCounterHTML = '
			<div class="snippet__keywords" >
				<div id="snippet__keywords_length" >
					<span id="snippet__keywords_counter">[[initial_characters_count]]</span>
				</div>
			</div>';


	
		if (strlen($values['meta_data/title'])<1) {
			$titleCounterHTML = str_replace('[[initial_class_name]]', 'title_red', $titleCounterHTML);
		} elseif (strlen($values['meta_data/title'])<20)  {
			$titleCounterHTML = str_replace('[[initial_class_name]]', 'title_orange', $titleCounterHTML);
		} elseif (strlen($values['meta_data/title'])<40)  {
			$titleCounterHTML = str_replace('[[initial_class_name]]', 'title_yellow', $titleCounterHTML);
		} elseif (strlen($values['meta_data/title'])<66)  {
			$titleCounterHTML = str_replace('[[initial_class_name]]', 'title_green', $titleCounterHTML);
		} else {
			$titleCounterHTML = str_replace('[[initial_class_name]]', 'title_yellow', $titleCounterHTML);
		}
		$titleCounterHTML = str_replace('[[initial_characters_count]]', strlen($values['meta_data/title']), $titleCounterHTML);
		$box['tabs']['meta_data']['fields']['title']['post_field_html'] = $titleCounterHTML;


		if (strlen($values['meta_data/description'])<1) {
			$descriptionCounterHTML = str_replace('[[initial_class_name]]', 'description_red', $descriptionCounterHTML);
		} elseif (strlen($values['meta_data/description'])<50)  {
			$descriptionCounterHTML = str_replace('[[initial_class_name]]', 'description_orange', $descriptionCounterHTML);
		} elseif (strlen($values['meta_data/description'])<100)  {
			$descriptionCounterHTML = str_replace('[[initial_class_name]]', 'description_yellow', $descriptionCounterHTML);
		} elseif (strlen($values['meta_data/description'])<156)  {
			$descriptionCounterHTML = str_replace('[[initial_class_name]]', 'description_green', $descriptionCounterHTML);
		} else {
			$descriptionCounterHTML = str_replace('[[initial_class_name]]', 'description_yellow', $descriptionCounterHTML);
		}
		$descriptionCounterHTML = str_replace('[[initial_characters_count]]', strlen($values['meta_data/description']), $descriptionCounterHTML);
		$box['tabs']['meta_data']['fields']['description']['post_field_html'] = $descriptionCounterHTML;


		$keywordsCounterHTML = str_replace('[[initial_characters_count]]', strlen($values['meta_data/keywords']) , $keywordsCounterHTML);
		$box['tabs']['meta_data']['fields']['keywords']['post_field_html'] = $keywordsCounterHTML;
		
		
		//Set up content tabs (up to four of them), for each WYSIWYG Editor
		if (isset($box['tabs']['content1'])) {
			$i = 0;
			$slots = [];
			if ($box['key']['source_cID']
			 && $box['key']['cType']
			 && $box['key']['source_cVersion']) {
				$slots = ze\contentAdm::mainSlot($box['key']['source_cID'], $box['key']['cType'], $box['key']['source_cVersion'], false, false, $values['meta_data/layout_id']);
			} else {
				$slots = ze\layoutAdm::mainSlot($values['meta_data/layout_id'], false, false);
			}

			if (!empty($slots)) {
				foreach ($slots as $slot) {
					if (++$i > 4) {
						break;
					}
		
					$box['tabs']['content'. $i]['hidden'] = false;
					if (count($slots) == 1) {
						$box['tabs']['content'. $i]['label'] = ze\admin::phrase('Main content');
			
					} elseif (strlen($slot) <= 20) {
						$box['tabs']['content'. $i]['label'] = $slot;
			
					} else {
						$box['tabs']['content'. $i]['label'] = substr($slot, 0, 8). '...'. substr($slot, -8);
					}
					ze\contentAdm::addAbsURLsToAdminBoxField($box['tabs']['content'. $i]['fields']['content']);
			
					if (ze\gridAdm::readLayoutCode($values['meta_data/layout_id'], $justCheck = true, $quickCheck = true)) {
						$fields['content'. $i. '/thumbnail']['hidden'] = false;
						$fields['content'. $i. '/thumbnail']['snippet']['html'] = '
							<p style="text-align: center;">
								<a>
									<img src="'. htmlspecialchars(
										ze\link::absolute(). 'zenario/admin/grid_maker/ajax.php?loadDataFromLayout='. (int) $values['meta_data/layout_id']. '&highlightSlot='. rawurlencode($slot). '&thumbnail=1&width=150&height=200'
									). '" width="150" height="200" style="border: 1px solid black;"/>
								</a>
							</p>';
			
					} else {
						$fields['content'. $i. '/thumbnail']['hidden'] = true;
						$fields['content'. $i. '/thumbnail']['snippet']['html'] = '';
					}
				}
			}
			
			// Hide dropdown if no content tabs are visible
			if ($i <= 1) {
				$box['tabs']['content_dropdown']['hidden'] = true;
				if ($i == 1) {
					unset($box['tabs']['content1']['parent']);
				}
			}
			
			// Hide extra content tabs
			while (++$i <= 4) {
				$box['tabs']['content'. $i]['hidden'] = true;
				$fields['content'. $i. '/thumbnail']['snippet']['html'] = '';
			}
		}
		if (isset($box['tabs']['meta_data']['fields']['content_summary'])) {
			ze\contentAdm::addAbsURLsToAdminBoxField($box['tabs']['meta_data']['fields']['content_summary']);
		}
		
		//Show the options for the site-map/search engine preview by default
		$fields['meta_data/excluded_from_sitemap']['hidden'] = true;
		$fields['meta_data/included_in_sitemap']['hidden'] = false;
		
		if ($box['key']['cID']
		 && ze::in(ze\content::isSpecialPage($box['key']['cID'], $box['key']['cType']), 'zenario_not_found', 'zenario_no_access')) {
			
			//Hide these options for the 403/404 pages
			$fields['meta_data/excluded_from_sitemap']['hidden'] = false;
			$fields['meta_data/included_in_sitemap']['hidden'] = true;
		}
		
		
		$this->autoSetTitle($box, $fields, $values);
		$this->formatMenu($box, $fields, $values, $changes);
	}
	
	public function autoSetTitle(&$box, &$fields, &$values) {
		
		//If we've creating a new content item...
		if (!$box['key']['cID'] && !$box['key']['source_cID']) {
			
			//...and the admin just changed the title...
			if ($box['key']['last_title'] != $values['meta_data/title']) {
				
				//Check if there's a main content area
				if (isset($box['tabs']['content1']['hidden'])
				 && empty($box['tabs']['content1']['hidden'])) {
					
					//Check if the main content area is empty, or was set by this algorithm before.
					if (empty($values['content1/content'])
					 || !($existingText = trim(str_replace('&nbsp;', ' ', strip_tags($values['content1/content']))))
					 || ($existingText == $box['key']['last_title'])
					 || ($existingText == htmlspecialchars($box['key']['last_title']))) {
						$values['content1/content'] = '<h1>'. htmlspecialchars($values['meta_data/title']). '</h1>';
					}
				}
				
				$box['key']['last_title'] = $values['meta_data/title'];
			}
		}
		
	}


	public function validateAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes, $saving) {
		$this->autoSetTitle($box, $fields, $values);
		
		$box['confirm']['show'] = false;
		$box['confirm']['message'] = '';
		
		if (!$box['key']['cID']) {
			if (!$values['meta_data/layout_id']) {
				$box['tab'] = 'meta_data';
				$fields['meta_data/layout_id']['error'] = ze\admin::phrase('Please select a layout.');
			} else {
				$box['key']['cType'] = ze\row::get('layouts', 'content_type', $values['meta_data/layout_id']);
			}

		} else {
			ze\layoutAdm::validateChangeSingleLayout($box, $box['key']['cID'], $box['key']['cType'], $box['key']['cVersion'], $values['meta_data/layout_id'], $saving);
		}

		if (!ze\contentAdm::isCTypeRunning($box['key']['cType'])) {
			$box['tabs']['meta_data']['errors'][] =
				ze\admin::phrase(
					'Drafts of "[[cType]]" type content items cannot be created as their handler module is missing or not running.',
					['cType' => $box['key']['cType']]);
		}

		if (!$values['meta_data/title']) {
			$fields['meta_data/title']['error'] = ze\admin::phrase('Please enter a title.');
		}

		if (!empty($values['meta_data/alias'])) {
			$errors = false;
			if ($box['key']['translate']) {
				if (ze::setting('translations_different_aliases')) {
					$errors = ze\contentAdm::validateAlias($values['meta_data/alias'], false, $box['key']['cType'], ze\content::equivId($box['key']['source_cID'], $box['key']['cType']));
				}
			} else {
				$errors = ze\contentAdm::validateAlias($values['meta_data/alias']);
			}
			if (!empty($errors) && is_array($errors)) {
				$box['tabs']['meta_data']['errors'] = array_merge($box['tabs']['meta_data']['errors'], $errors);
			}
		}


		if ($box['key']['cType'] && $details = ze\contentAdm::cTypeDetails($box['key']['cType'])) {
			if ($details['description_field'] == 'mandatory' && !$values['meta_data/description']) {
				$fields['meta_data/description']['error'] = ze\admin::phrase('Please enter a description.');
			}
			if ($details['keywords_field'] == 'mandatory' && !$values['meta_data/keywords']) {
				$fields['meta_data/keywords']['error'] = ze\admin::phrase('Please enter keywords.');
			}
			if ($details['release_date_field'] == 'mandatory' && !$values['meta_data/release_date']) {
				$fields['meta_data/release_date']['error'] = ze\admin::phrase('Please enter a release date.');
			}
			if ($details['writer_field'] == 'mandatory' && !$values['meta_data/writer_id']) {
				$fields['meta_data/writer_id']['error'] = ze\admin::phrase('Please select a writer.');
			}
			if ($details['summary_field'] == 'mandatory' && !$values['meta_data/content_summary']) {
				$fields['meta_data/content_summary']['error'] = ze\admin::phrase('Please enter a summary.');
			}
		}

		if (ze\ray::issetArrayKey($values,'meta_data/writer_id') && !ze\ray::issetArrayKey($values,'meta_data/writer_name')) {
			$fields['meta_data/writer_name']['error'] = ze\admin::phrase('Please enter a writer name.');
		}

		if ($box['key']['translate']) {
			$equivId = ze\content::equivId($box['key']['source_cID'], $box['key']['cType']);
	
			if (ze\row::exists('content_items', ['equiv_id' => $equivId, 'type' => $box['key']['cType'], 'language_id' => $values['meta_data/language_id']])) {
				$box['tabs']['meta_data']['errors'][] = ze\admin::phrase('This translation already exists.');
			}
		}
	}
	
	
	public function saveAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		if ($box['key']['cID'] && !ze\priv::check('_PRIV_EDIT_DRAFT', $box['key']['cID'], $box['key']['cType'])) {
			exit;
		}
		
		$isNewContentItem = !$box['key']['cID'];
		
		//Create a new Content Item, or a new Draft of a Content Item, as needed.
		$newDraftCreated = ze\contentAdm::createDraft($box['key']['cID'], $box['key']['source_cID'], $box['key']['cType'], $box['key']['cVersion'], $box['key']['source_cVersion'], $values['meta_data/language_id']);
		$forceMarkAsEditsMade = $newDraftCreated;

		if (!$box['key']['cID']) {
			exit;
		} else {
			$box['key']['id'] = $box['key']['cType'].  '_'. $box['key']['cID'];
		}

		$version = [];
		$newLayoutId = false;
		
		//If we're creating a new content item in the front-end, try to start off in Edit mode
		if ($isNewContentItem && !$box['key']['create_from_content_panel']) {
			$_SESSION['page_toolbar'] = 'edit';
			$_SESSION['page_mode'] = 'edit';
			$_SESSION['last_item'] = $box['key']['cType'].  '_'. $box['key']['cID'];
		}


		//Save the values of each field in the Meta Data tab
		if (ze\ring::engToBoolean($box['tabs']['meta_data']['edit_mode']['on'])
		 && ze\priv::check('_PRIV_EDIT_DRAFT', $box['key']['cID'], $box['key']['cType'])) {
			//Only save aliases for first drafts
			if (!empty($values['meta_data/alias']) && $box['key']['cVersion'] == 1) {
				if (!$box['key']['translate'] || ze::setting('translations_different_aliases')) {
					ze\row::set('content_items', ['alias' => ze\contentAdm::tidyAlias($values['meta_data/alias'])], ['id' => $box['key']['cID'], 'type' => $box['key']['cType']]);
				}
			}

			//Set the title
			$version['title'] = $values['meta_data/title'];
			
			$version['description'] = $values['meta_data/description'];
			$version['keywords'] = $values['meta_data/keywords'];
			$version['release_date'] = $values['meta_data/release_date'];
			$version['writer_id'] = $values['meta_data/writer_id'];
			$version['writer_name'] = $values['meta_data/writer_name'];
			#$version['in_sitemap'] = $values['meta_data/in_sitemap'];
			$version['in_sitemap'] = !$values['meta_data/exclude_from_sitemap'];
	
			ze\contentAdm::stripAbsURLsFromAdminBoxField($box['tabs']['meta_data']['fields']['content_summary']);
			$version['content_summary'] = $values['meta_data/content_summary'];
	
			if (isset($fields['meta_data/lock_summary_edit_mode']) && !$fields['meta_data/lock_summary_edit_mode']['hidden']) {
				$version['lock_summary'] = (int) $values['meta_data/lock_summary_edit_mode'];
			}
		}

		//Set the Layout
		if (ze\ring::engToBoolean($box['tabs']['meta_data']['edit_mode']['on'] ?? false)
		 && ze\priv::check('_PRIV_EDIT_CONTENT_ITEM_TEMPLATE', $box['key']['cID'], $box['key']['cType'])) {
			$newLayoutId = $values['meta_data/layout_id'];
		}
		
		
		//If the admin selected the duplicate option for any plugins, duplicate those plugins and put the copies in the slots
		//where the old ones were.
		if ($box['key']['duplicate'] || $box['key']['translate']) {
			$startAt = 1;
			for ($n = $startAt; (($suffix = '__'. $n) && (!empty($fields['plugins/instance_id'. $suffix]))); ++$n) {
				
				if ($values['plugins/action'. $suffix] == 'duplicate') {
					$newName = $values['plugins/new_name'. $suffix];
					$slotName = $values['plugins/slotname'. $suffix];
					$instanceId = $values['plugins/instance_id'. $suffix];
					$eggId = false;
					ze\pluginAdm::rename($instanceId, $eggId, $newName, $createNewInstance = true);
					ze\pluginAdm::updateItemSlot($instanceId, $slotName, $box['key']['cID'], $box['key']['cType'], $box['key']['cVersion']);
				}
			}
		}
		

		//Save the CSS and background
		if (ze\ring::engToBoolean($box['tabs']['css']['edit_mode']['on'] ?? false)
		 && ze\priv::check('_PRIV_EDIT_CONTENT_ITEM_TEMPLATE', $box['key']['cID'], $box['key']['cType'])) {
			$version['css_class'] = $values['css/css_class'];
	
			if (($filepath = ze\file::getPathOfUploadInCacheDir($values['css/background_image']))
			 && ($imageId = ze\file::addToDatabase('background_image', $filepath, false, $mustBeAnImage = true))) {
				$version['bg_image_id'] = $imageId;
			} else {
				$version['bg_image_id'] = $values['css/background_image'];
			}
	
			$version['bg_color'] = $values['css/bg_color'];
			$version['bg_position'] = $values['css/bg_position']? $values['css/bg_position'] : null;
			$version['bg_repeat'] = $values['css/bg_repeat']? $values['css/bg_repeat'] : null;
		}

		//Save the chosen file, if a file was chosen
		if (ze\ring::engToBoolean($box['tabs']['file']['edit_mode']['on'] ?? false)) {
			if ($values['file/file']
			 && ($path = ze\file::getPathOfUploadInCacheDir($values['file/file']))
			 && ($filename = preg_replace('/([^.a-z0-9]+)/i', '_', basename($path)))
			 && ($fileId = ze\file::addToDocstoreDir('content', $path, $filename))) {
				$version['file_id'] = $fileId;
				$version['filename'] = $filename;
			} else {
				$version['file_id'] = $values['file/file'];
			}
		}
		
		$changes = !empty($version);

		//Update the layout
		if ($newLayoutId) {
			ze\layoutAdm::changeContentItemLayout($box['key']['cID'], $box['key']['cType'], $box['key']['cVersion'], $newLayoutId);
			$changes = true;
		}

		//Save the content tabs (up to four of them), for each WYSIWYG Editor
		if (isset($box['tabs']['content1'])
		 && ze\priv::check('_PRIV_EDIT_DRAFT', $box['key']['cID'], $box['key']['cType'])) {
			$i = 0;
			$slots = ze\contentAdm::mainSlot($box['key']['cID'], $box['key']['cType'], $box['key']['cVersion'], false, false, $values['meta_data/layout_id']);

			if (!empty($slots)) {
				foreach ($slots as $slot) {
					if (++$i > 4) {
						break;
					}
			
					if (!empty($box['tabs']['content'. $i]['edit_mode']['on'])) {
						ze\contentAdm::stripAbsURLsFromAdminBoxField($box['tabs']['content'. $i]['fields']['content']);
						ze\contentAdm::saveContent($values['content'. $i. '/content'], $box['key']['cID'], $box['key']['cType'], $box['key']['cVersion'], $slot);
						$changes = true;
					}
				}
			}
		}
		
		//Update the content_item_versions table
		if ($changes) {
			ze\contentAdm::updateVersion($box['key']['cID'], $box['key']['cType'], $box['key']['cVersion'], $version, $forceMarkAsEditsMade);
		}


		//Update item Categories
		if (empty($box['tabs']['categories']['hidden'])
		 && ze\ring::engToBoolean($box['tabs']['categories']['edit_mode']['on'] ?? false)
		 && isset($values['categories/categories'])
		 && ze\priv::check('_PRIV_EDIT_CONTENT_ITEM_CATEGORIES')) {
			ze\categoryAdm::setContentItemCategories($box['key']['cID'], $box['key']['cType'], ze\ray::explodeAndTrim($values['categories/categories']));
		}

		//Record and equivalence if this Content Item was duplicated into another Language
		$equivId = false;
		if ($box['key']['translate']) {
			$equivId = ze\contentAdm::recordEquivalence($box['key']['source_cID'], $box['key']['cID'], $box['key']['cType']);
		}

		if (isset($version['bg_image_id'])) {
			ze\contentAdm::deleteUnusedBackgroundImages();
		}
		
		
		$this->saveMenu($box, $fields, $values, $changes, $equivId);
	}
	
	public function adminBoxSaveCompleted($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		if ($box['key']['id_is_menu_node_id'] || $box['key']['id_is_parent_menu_node_id']) {
			$sectionId = isset($box['key']['target_menu_section']) ? $box['key']['target_menu_section'] : false;
			if ($menu = ze\menu::getFromContentItem($box['key']['cID'], $box['key']['cType'], $fetchSecondaries = false, $sectionId)) {
				$box['key']['id'] = $menu['id'];
			}
		}
	}
	
	
	
	
	
	
	protected function fillMenu(&$box, &$fields, &$values, $contentType, $content, $version) {
		
		$defaultPos = '';
		
		//If a content item was set as the "from" or "source", attempt to get details of its primary menu node
		if ($box['key']['from_cID']) {
			$menu = ze\menu::getFromContentItem($box['key']['from_cID'], $box['key']['from_cType']);
			
			//Change the default to "after" if there's a known position
			$defaultPos = 'after';
		
		//Watch out for the "create a child" option from Organizer
		} elseif ($box['key']['target_menu_parent']) {
			$menu = ze\menu::details($box['key']['target_menu_parent']);
			$defaultPos = 'under';
		
		} else {
			$menu = false;
		}
		

		//Don't show the option to add a menu node when editing an existing content item...
		if ($box['key']['cID']
		 
		//...or if an Admin does not have the permissions to create a menu node...
		 || ($box['key']['translate'] && !ze\priv::check('_PRIV_EDIT_MENU_TEXT'))
		 || (!$box['key']['translate'] && !ze\priv::check('_PRIV_ADD_MENU_ITEM'))
		
		//...or when translating a content item without a menu node.
		 || ($box['key']['translate'] && !$menu)) {
			
			$fields['meta_data/menu']['hidden'] = true;
			$fields['meta_data/create_menu_node']['readonly'] = true;
			$values['meta_data/create_menu_node'] = '';
		
		//If we're translating, add the ability to add the text but hide all of the options about setting a position
		} elseif ($box['key']['translate']) {
			$fields['meta_data/menu_pos'] =
			$fields['meta_data/menu_pos_before'] =
			$fields['meta_data/menu_pos_under'] =
			$fields['meta_data/menu_pos_after'] =
			$fields['meta_data/menu_pos_specific']['hidden'] = true;
			$fields['meta_data/create_menu_node']['readonly'] = true;
			$values['meta_data/create_menu_node'] = 1;
		
		} else {
			$beforeNode = 0;
			$underNode = 1;
			
			if ($menu) {
				//Set the menu positions for before/after/under
				$values['meta_data/menu_pos_before'] = $menu['section_id']. '_'. $menu['id']. '_'. $beforeNode;
				$values['meta_data/menu_pos_under'] = $menu['section_id']. '_'. $menu['id']. '_'. $underNode;
				$values['meta_data/menu_pos_after'] =
				$values['meta_data/menu_pos_specific'] = $menu['section_id']. '_'. $menu['parent_id']. '_'. $underNode;
			
				//That last line of code above will actually place the new menu node at the end of the current line.
				//If there's a menu node after the current one, then that's not technically the position after this one,
				//so we'll need to correct this.
				if ($nextNodeId = ze\sql::fetchValue('
					SELECT id
					FROM '. DB_PREFIX. 'menu_nodes
					WHERE section_id = '. (int) $menu['section_id']. '
					  AND parent_id = '. (int) $menu['parent_id']. '
					  AND ordinal > '. (int) $menu['ordinal']. '
					ORDER BY ordinal ASC
					LIMIT 1
				')) {
					$values['meta_data/menu_pos_after'] = $menu['section_id']. '_'. $nextNodeId. '_'. $beforeNode;
				}
				
				$values['meta_data/menu_pos'] = $defaultPos;
			
			} else {
				//Remove the before/under/after options if we didn't find them above
				unset($fields['meta_data/menu_pos']['values']['before']);
				unset($fields['meta_data/menu_pos']['values']['under']);
				unset($fields['meta_data/menu_pos']['values']['after']);
				
				//If we know the menu section we're aiming to create in, at least pre-populate that
				if ($box['key']['target_menu_section']) {
					$values['meta_data/menu_pos_specific'] = $box['key']['target_menu_section']. '_0_'. $underNode;
				}
			}
			
			if ($contentType['default_parent_menu_node']) {
				
				$values['meta_data/menu_pos'] = 'specific';
				$values['meta_data/menu_pos_specific'] = ze\contentAdm::getDefaultMenuPositionFromSettings($contentType);
				
				if ($contentType['menu_node_position_edit'] == 'force') {
					$fields['meta_data/menu_pos']['readonly'] =
					$fields['meta_data/menu_pos_specific']['readonly'] = true;
					$fields['meta_data/menu_pos_locked_warning']['hidden'] = false;
				}
			}
		}
		
		
		
		
		
		
		
		
		
		//Old code
		//
		//
		////Remove the ability to create a Menu Node if location information for the menu has not been provided
		//if (!$box['key']['target_menu_section']) {
		//	$fields['meta_data/create_menu']['hidden'] = true;
		//
		//} elseif ($box['key']['target_menu_parent']) {
		//	$values['meta_data/menu_parent_path'] = ze\menuAdm::path($box['key']['target_menu_parent'], ze::ifNull($box['key']['target_language_id'], ($_GET['languageId'] ?? false), ($_GET['language'] ?? false)));
		//}
		//
		//
		//if ($content) {
		//	if ($box['key']['duplicate'] || $box['key']['translate']) {
		//		
		//		//If duplicating, check for a Menu Node
		//		if ((($box['key']['translate'] && ze\priv::check('_PRIV_EDIT_MENU_TEXT')) || (!$box['key']['translate'] && ze\priv::check('_PRIV_ADD_MENU_ITEM')))
		//		 && ($currentMenu = ze\menu::getFromContentItem($box['key']['source_cID'], $box['key']['cType']))
		//
		//		//When duplicating to a new/different language, if Menu Text already exists in the new Language,
		//		//but a Content Item does not, rely on the existing Menu Text and don't offer to create a new one
		//		 && (!($lang = ze::ifNull($box['key']['target_language_id'], ($_GET['languageId'] ?? false), ($_GET['language'] ?? false)))
		//		  || ($lang == $content['language_id'])
		//		  || !(ze\row::exists('menu_text', ['menu_id' => $currentMenu['id'], 'language_id' => $lang])))) {
		//			
		//			$box['key']['target_menu_section'] = $currentMenu['section_id'];
		//			$values['meta_data/menu_title'] = $currentMenu['name'];
		//			$values['meta_data/menu_path'] = ze\menuAdm::path($currentMenu['parent_id'], $lang);
		//			$values['meta_data/create_menu'] = 1;
		//
		//			
		//		} else {
		//			$values['meta_data/create_menu'] = '';
		//			$fields['meta_data/create_menu']['hidden'] = true;
		//			$box['key']['target_menu_section'] = null;
		//			$box['key']['target_menu_parent'] = null;
		//		}
		//		
		//		if ($box['key']['translate']) {
		//			$fields['meta_data/create_menu']['hidden'] = true;
		//		}
		//	
		//	} else {
		//		//The options to set the menu text should be hidden when not creating something
		//		$fields['meta_data/create_menu']['hidden'] = true;
		//		$values['meta_data/create_menu'] = '';
		//		$box['key']['target_menu_section'] = null;
		//		$box['key']['target_menu_parent'] = null;
		//	}
		//}
		//
		//
		//if (!$version) {
		//	
		//	// Load content type default menu options
		//	if ($contentType['default_parent_menu_node']) {
		//		
		//		$values['meta_data/at_position'] = 'specific_position';
		//		
		//		$values['meta_data/specific_position'] = ze\contentAdm::getDefaultMenuPositionFromSettings($contentType);
		//		
		//		if ($contentType['menu_node_position_edit'] == 'force') {
		//			$fields['meta_data/warning_message']['snippet']['html'] = ze\admin::phrase('The initial menu position for content items of this type has been locked.');
		//			$fields['meta_data/warning_message']['hidden'] = false;
		//			$fields['meta_data/at_position']['disabled'] = true;
		//			$fields['meta_data/specific_position']['readonly'] = true;
		//		}
		//	}
		//	
		//	//Check the FAB was opened from an existing content item in the front-end
		//	if ($box['key']['from_cID']
		//	 && $box['key']['from_cType']
		//	 && ($menu = ze\menu::getFromContentItem($box['key']['from_cID'], $box['key']['from_cType']))) {
		//		
		//		$box['key']['from_mID'] = $menu['id'];
		//		
		//		//Default the specific location option to "under the current content item", if a default wasn't set in the content type settings
		//		if (!$values['meta_data/specific_position']) {
		//			$dummyNode = 1;
		//			$values['meta_data/specific_position'] = $menu['section_id'] . '_' . $menu['id'] . '_' . $dummyNode;
		//		}
		//	
		//	} else {
		//		//Don't allow the "after current" option if the was no relative content item to base this off
		//		$fields['meta_data/at_position']['values']['after_current']['hidden'] = true;
		//	}
		//}
		//
		//
		//if (!$version && $box['key']['target_menu_title'] && isset($box['tabs']['meta_data']['fields']['menu_title'])) {
		//	$values['meta_data/menu_title'] = $box['key']['target_menu_title'];
		//}
		//
		//if (!empty($values['meta_data/menu_title'])) {
		//	if (ze\ray::value($box,'tabs','meta_data','fields','menu_parent_path','value')) {
		//		$values['meta_data/menu_path'] =
		//			$values['meta_data/menu_parent_path'].
		//			' -> '.
		//			$values['meta_data/menu_title'];
		//	} else {
		//		$values['meta_data/menu_path'] =
		//			$values['meta_data/menu_title'];
		//	}
		//}
		//
		//
		//
		////Remove the Menu Creation options if an Admin does not have the permissions to create a Menu Item
		//if (($box['key']['translate'] && !ze\priv::check('_PRIV_EDIT_MENU_TEXT'))
		// || (!$box['key']['translate'] && !ze\priv::check('_PRIV_ADD_MENU_ITEM'))) {
		//	$values['meta_data/create_menu'] = '';
		//	$fields['meta_data/create_menu']['hidden'] = true;
		//	$box['key']['target_menu_section'] = null;
		//	$box['key']['target_menu_parent'] = null;
		//}
		//
		//
		//if (empty($fields['meta_data/create_menu']['hidden'])) {
		//	$values['meta_data/create_menu'] =
		//	$box['tabs']['meta_data']['fields']['create_menu']['current_value'] = 1;
		//}
		//
		//
		//
		////For top-level menu modes, add a note to the "path" field to make it clear that it's
		////at the top level
		//if (!$values['meta_data/menu_parent_path']) {
		//	$fields['meta_data/menu_path']['label'] = ze\admin::phrase('Path (top level):');
		//}
		//
		//
		//
		//
	}

	public function formatMenu(&$box, &$fields, &$values, $changes) {
	
	
		//Old code
		
		//$fields['meta_data/menu_title']['hidden'] =
		//$fields['meta_data/menu_path']['hidden'] =
		//$fields['meta_data/menu_parent_path']['hidden'] =
		//	empty($box['key']['target_menu_section'])
		//		|| !$values['meta_data/create_menu'];
		//
		//$showMenuOptions = !empty($box['key']['create_from_toolbar']) || !empty($box['key']['create_from_content_panel']);
		//
		//if ($showMenuOptions) {
		//	$fields['meta_data/menu_title']['hidden'] = !ze::in($values['meta_data/at_position'], 'specific_position', 'after_current');
		//	$fields['meta_data/menu_title']['indent'] = 1;
		//}
		//
		//if (!$values['meta_data/menu_title']) {
		//	$values['meta_data/menu_title'] = $values['meta_data/title'];
		//}
		//
		//$fields['meta_data/at_position']['hidden'] = 
		//	!$showMenuOptions;
		//
		//$fields['meta_data/specific_position']['hidden'] = 
		//	!$showMenuOptions || $values['meta_data/at_position'] != 'specific_position';
	//
	}
	
	
	public function saveMenu(&$box, &$fields, &$values, $changes, $equivId) {
		
		if ($box['key']['cVersion'] == 1) {
		
			//If translating a content item with a menu node, add the translated menu text
			if ($box['key']['translate']) {
				if ($equivId
				 && $values['meta_data/create_menu_node']
				 && ze\priv::check('_PRIV_EDIT_MENU_TEXT')) {
		
					//Create copies of any Menu Node Text into this language
					$sql = "
						INSERT IGNORE INTO ". DB_PREFIX. "menu_text
							(menu_id, language_id, name, descriptive_text)
						SELECT menu_id, '". ze\escape::sql($values['meta_data/language_id']). "', '". ze\escape::sql($values['meta_data/menu_text']). "', descriptive_text
						FROM ". DB_PREFIX. "menu_nodes AS mn
						INNER JOIN ". DB_PREFIX. "menu_text AS mt
						   ON mt.menu_id = mn.id
						  AND mt.language_id = '". ze\escape::sql(ze\content::langId($box['key']['source_cID'], $box['key']['cType'])). "'
						WHERE mn.equiv_id = ". (int) $equivId. "
						  AND mn.content_type = '". ze\escape::sql($box['key']['cType']). "'";
					ze\sql::update($sql);
				}
			
			//If creating a new content item, add a new menu node at the specified position
			} else {
				if ($values['meta_data/create_menu_node']
				 && ze\priv::check('_PRIV_ADD_MENU_ITEM')) {
				
					$menuIds = [];
					switch ($values['meta_data/menu_pos']) {
						case 'before':
							$menuIds = ze\menuAdm::addContentItems($box['key']['id'], $values['meta_data/menu_pos_before']);
							break;
						case 'after':
							$menuIds = ze\menuAdm::addContentItems($box['key']['id'], $values['meta_data/menu_pos_after']);
							break;
						case 'under':
							$menuIds = ze\menuAdm::addContentItems($box['key']['id'], $values['meta_data/menu_pos_under']);
							break;
						case 'specific':
							$menuIds = ze\menuAdm::addContentItems($box['key']['id'], $values['meta_data/menu_pos_specific']);
							break;
					}
				
					if ($menuId = array_shift($menuIds)) {
						ze\menuAdm::saveText($menuId, $values['meta_data/language_id'], ['name' => $values['meta_data/menu_text']]);
					}
				}
			}
		}
		
		
		
		
		
		
		
		
		
		
		//Old code
		//
		////Create Menu Nodes for first drafts
		//if ($values['meta_data/create_menu'] && $box['key']['cVersion'] == 1 && $box['key']['target_menu_section']) {
		//	if (($box['key']['translate'] && ze\priv::check('_PRIV_EDIT_MENU_TEXT')) || (!$box['key']['translate'] && ze\priv::check('_PRIV_ADD_MENU_ITEM'))) {
		//		
		//		$menu = ze\menu::getFromContentItem($box['key']['source_cID'], $box['key']['cType']);
		//		
		//		if (($box['key']['duplicate'] || $box['key']['translate'])
		//		 && ze\contentAdm::recordEquivalence($box['key']['source_cID'], $box['key']['cID'], $box['key']['cType'])
		//		 && ($menu)) {
		//			//Try to use one equivalent Menu Node rather than creating two copies, if duplicationg into a new Language
		//			$menuId = $menu['mID'];
		//
		//		} elseif (!$box['key']['translate']) {
		//			$submission = $menu? $menu : [];
		//			
		//			unset(
		//				$submission['id'], $submission['mID'], $submission['menu_id'],
		//				$submission['redundancy'], $submission['equiv_id'],
		//				$submission['parent_id'], $submission['ordinal']
		//			);
		//			
		//			$submission['target_loc'] = 'int';
		//			$submission['content_id'] = $box['key']['cID'];
		//			$submission['content_type'] = $box['key']['cType'];
		//			$submission['content_version'] = $box['key']['cVersion'];
		//			$submission['parent_id'] = $box['key']['target_menu_parent'];
		//			$submission['section_id'] = $box['key']['target_menu_section'];
		//	
		//			$menuId = ze\menuAdm::save($submission);
		//		}
		//
		//		ze\menuAdm::saveText($menuId, $values['meta_data/language_id'], ['name' => $values['meta_data/menu_title']]);
		//	}
		//}
		//
		//$showMenuOptions = !empty($box['key']['create_from_toolbar']) || !empty($box['key']['create_from_content_panel']);
		////Create menu node from toolbar
		//if ($showMenuOptions && ze::in($values['meta_data/at_position'], 'specific_position', 'after_current')) {
		//	
		//	
		//	if ($values['meta_data/at_position'] == 'specific_position' && $values['meta_data/specific_position']) {
		//		ze\menuAdm::addContentItems($box['key']['id'], $values['meta_data/specific_position'], $afterNeighbour = 0);
		//	
		//	} elseif ($values['meta_data/at_position'] == 'after_current' && $box['key']['from_mID']) {
		//		ze\menuAdm::addContentItems($box['key']['id'], $box['key']['from_mID'], $afterNeighbour = 1);
		//	}
		//	
		//	$menuId = ze\row::get('menu_nodes', 'id', ['equiv_id' => $box['key']['cID'], 'content_type' => $box['key']['cType']]);
		//	ze\menuAdm::saveText($menuId, $values['meta_data/language_id'], ['name' => $values['meta_data/menu_title']]);
		//}
	}
}
