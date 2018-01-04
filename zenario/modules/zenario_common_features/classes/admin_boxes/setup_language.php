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


class zenario_common_features__admin_boxes__setup_language extends module_base_class {

	public function fillAdminBox($path, $settingGroup, &$box, &$fields, &$values) {
		
		if (!$box['key']['id']) {
			exit;
	
		} elseif ($lang = getRow('languages', true, $box['key']['id'])) {
			$values['settings/detect'] = $lang['detect'];
			$values['settings/detect_lang_codes'] = $lang['detect_lang_codes'];
			$values['settings/search_type'] = $lang['search_type'];
			$values['settings/translate_phrases'] = $lang['translate_phrases'];
			$values['settings/show_untranslated_content_items'] = $lang['show_untranslated_content_items'];
	
			if ($lang['domain']) {
				$values['settings/use_domain'] = 1;
				$values['settings/domain'] = $lang['domain'];
			}
	
			$box['title'] = adminPhrase('Editing settings for "[[language]]"', array('language' => getLanguageName($box['key']['id'])));

		} else {
			$box['title'] = adminPhrase('Enabling the Language "[[language]]" to the Site', array('language' => getLanguageName($box['key']['id'])));
			exitIfNotCheckPriv('_PRIV_MANAGE_LANGUAGE_CONFIG');
			$box['save_button_message'] = adminPhrase('Enable Language');
	
			$box['tabs']['settings']['edit_mode']['always_on'] = true;
	
			//Don't default translations to on for English
			if ($box['key']['id'] == 'en'
			 || substr($box['key']['id'], 0, 3) == 'en-') {
			} else {
				$values['settings/translate_phrases'] = 1;
			}
	
			switch ($box['key']['id']) {
				case 'zh-hans':
				case 'zh-hant':
				case 'ko':
				case 'ja':
				case 'vi':
					$values['settings/search_type'] = 'simple';
					break;
				default:
					$values['settings/search_type'] = 'full_text';
					break;
			}

		}

		$fields['settings/domain']['placeholder'] = $box['key']['id']. '.'. primaryDomain();
		
		$defaultName = $this->lookupLangPhrase('__LANGUAGE_ENGLISH_NAME__', cms_core::$defaultLang);
		$engName = $this->lookupLangPhrase('__LANGUAGE_ENGLISH_NAME__', $box['key']['id']);
		$localName = $this->lookupLangPhrase('__LANGUAGE_LOCAL_NAME__', $box['key']['id']);
		$flag = $this->lookupLangPhrase('__LANGUAGE_FLAG_FILENAME__', $box['key']['id']);
		

		$values['dummy/default_name'] = $this->lookupLangPhrase('__LANGUAGE_ENGLISH_NAME__', cms_core::$defaultLang);
		$values['settings/english_name'] = $this->lookupLangPhrase('__LANGUAGE_ENGLISH_NAME__', $box['key']['id']);
		$values['settings/language_local_name'] = $this->lookupLangPhrase('__LANGUAGE_LOCAL_NAME__', $box['key']['id']);
		$values['settings/flag_filename'] = $this->lookupLangPhrase('__LANGUAGE_FLAG_FILENAME__', $box['key']['id']);

		if (empty($values['settings/detect_lang_codes'])) {
			$values['settings/detect_lang_codes'] = $box['key']['id'];
		}
		
		$fields['settings/show_untranslated_content_items']['label'] =
			adminPhrase('When showing banners/menus in [[settings/english_name]], and the site is only partially translated into [[settings/english_name]]:', $values);
		$fields['settings/show_untranslated_content_items']['values'][0]['label'] =
			adminPhrase('Hide banner/menu links when no [[settings/english_name]] page exists', $values);
		$fields['settings/show_untranslated_content_items']['values'][1]['label'] =
			adminPhrase('Show all banner/menu links, but link to the [[dummy/default_name]] page when no [[settings/english_name]] page exists', $values);
	}
	
	protected function lookupLangPhrase($code, $langId) {
		return getRow('visitor_phrases', 'local_text', array('code' => $code, 'language_id' => $langId, 'module_class_name' => 'zenario_common_features'));
	}

	public function formatAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		
	}


	public function validateAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes, $saving) {
		
		if ($values['settings/detect']) {
			if (!$values['settings/detect_lang_codes']) {
				$box['tabs']['settings']['errors'][] = adminPhrase('Please enter a language code to detect');
	
			} else {
				$sql = "
					SELECT id, detect_lang_codes
					FROM ". DB_NAME_PREFIX. "languages
					WHERE detect = 1
					  AND id != '". sqlEscape($box['key']['id']). "'";
		
				$siteLangs = array();
				$result = sqlQuery($sql);
				while ($row = sqlFetchAssoc($result)) {
					$siteLangs[strtolower($row['id'])] = $row['id'];
			
					foreach (explodeAndTrim($row['detect_lang_codes']) as $lang) {
						$siteLangs[strtolower($lang)] = $row['id'];
					}
				}
		
				foreach(explodeAndTrim($values['settings/detect_lang_codes']) as $lang) {
					if (isset($siteLangs[$lang])) {
						$box['tabs']['settings']['errors'][] =
							adminPhrase('The language code "[[code]]" is already used by another language.', array('code' => $lang));
					}
				}
			}
		}

		if ($values['settings/use_domain'] && setting('primary_domain') && getNumLanguages() > 1) {
			if (!$values['settings/domain']) {
				$box['tabs']['settings']['errors'][] = adminPhrase('Please enter a domain.');
	
			} elseif (!aliasURLIsValid($values['settings/domain'])) {
				$box['tabs']['settings']['errors'][] = adminPhrase('Please enter a valid domain.');
	
			} elseif (checkRowExists('languages', array('id' => array('!' => $box['key']['id']), 'domain' => $values['settings/domain']))) {
				$box['tabs']['settings']['errors'][] = adminPhrase('The domain "[[settings/domain]]" is already used by another language.', $values);
	
			} elseif (checkRowExists('spare_domain_names', $values['settings/domain'])) {
				$box['tabs']['settings']['errors'][] = adminPhrase('The domain "[[settings/domain]]" is already used as a spare domain name.', $values);
	
			//Don't run any validation if the language's domain is set to the admin or primary domain.
			} elseif ($values['settings/domain'] == adminDomain()) {
			} elseif ($values['settings/domain'] == primaryDomain()) {
	
			} else {
				$path = 'zenario/has_database_changed_and_is_cache_out_of_date.php';
				$post = true;
				if ($thisDomainCheck = curl(absCMSDirURL(). $path, $post)) {
					if ($cookieFreeDomainCheck = curl(($domain = httpOrHttps(). $values['settings/domain']. SUBDIRECTORY). $path, $post)) {
						if ($thisDomainCheck == $cookieFreeDomainCheck) {
							//Success, looks correct
						} else {
							$box['tabs']['settings']['errors'][] = adminPhrase('[[domain]] is pointing to a different site, or possibly an out-of-date copy of this site.', array('domain' => $domain));
						}
					} else {
						$box['tabs']['settings']['errors'][] = adminPhrase('A CURL request to [[domain]] failed. Either this is an invalid URL or Zenario is not at this location.', array('domain' => $domain));
					}
				}
			}
		}


		$maxEnabledLanguageCount = siteDescription('max_enabled_languages');
		$enabledLanguages = getLanguages();
		if ($maxEnabledLanguageCount && (count($enabledLanguages) >= $maxEnabledLanguageCount)) {
			$box['tabs']['settings']['errors'][] = adminPhrase('The maximun number of enabled languages on this site is [[count]]', array('count' => $maxEnabledLanguageCount));
		}
	}
	
	
	public function saveAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		
		exitIfNotCheckPriv('_PRIV_MANAGE_LANGUAGE_CONFIG');

		if (!$langId = $box['key']['id']) {
			exit;
		}

		$cItemsInLangKey = array('language_id' => $box['key']['id'], 'status' => array('!' => 'deleted'));
		$pagesExist = checkRowExists('content_items', $cItemsInLangKey);


		if (engToBoolean($box['tabs']['settings']['edit_mode']['on'] ?? false)) {
			setRow('languages', array(), $langId);

			setRow(
				'visitor_phrases',
				array(
					'local_text' => $values['settings/english_name'],
					'protect_flag' => 1),
				array(
					'code' => '__LANGUAGE_ENGLISH_NAME__',
					'language_id' => $box['key']['id'],
					'module_class_name' => 'zenario_common_features'));
	
			setRow(
				'visitor_phrases',
				array(
					'local_text' => $values['settings/language_local_name'],
					'protect_flag' => 1),
				array(
					'code' => '__LANGUAGE_LOCAL_NAME__',
					'language_id' => $box['key']['id'],
					'module_class_name' => 'zenario_common_features'));
	
			setRow(
				'visitor_phrases',
				array(
					'local_text' => decodeItemIdForOrganizer($values['settings/flag_filename']),
					'protect_flag' => 1),
				array(
					'code' => '__LANGUAGE_FLAG_FILENAME__',
					'language_id' => $box['key']['id'],
					'module_class_name' => 'zenario_common_features'));
	
			updateRow(
				'languages',
				array(
					'detect' => $values['settings/detect'], 
					'detect_lang_codes' => $values['settings/detect_lang_codes'], 
					'translate_phrases' => $values['settings/translate_phrases'], 
					'show_untranslated_content_items' => $values['settings/show_untranslated_content_items'], 
					'search_type'=> ($values['settings/search_type'] == 'simple'? 'simple' : 'full_text'),
					'domain'=> ($values['settings/use_domain'] && getNumLanguages() > 1? $values['domain'] : '')
				),
				$box['key']['id']);
		}

		//Check if a default language has been set, and set it now if not
		if ($addedFirstLanguage = !cms_core::$defaultLang) {
			setSetting('default_language', $langId);
			cms_core::$defaultLang = $langId;
		}

		//Update the special pages, creating new ones if needed
		addNeededSpecialPages();

		//Add any new phrases for this language
		importPhrasesForModules($langId);

		//If we're adding a new language (i.e. no content items previously existed), show a message
		//warning the admin what pages were just made.
		if (!$pagesExist) {
	
			//Check for the pages that were just made
			$contentItems = getRowsArray('content_items', array('id', 'type', 'alias'), $cItemsInLangKey, 'id');
	
			if (!empty($contentItems)) {
				if (count($contentItems) < 2) {
					$toastMessage =
						adminPhrase(
							'&quot;[[tag]]&quot; was created as a home page. You should review and publish this content item.',
							array('tag' => htmlspecialchars(formatTag($contentItems[0]['id'], $contentItems[0]['type'], $contentItems[0]['alias'], $box['key']['id']))));
		
				} else {
					$toastMessage =
						adminPhrase('The following content items were just created, you should review and publish them:').
						'<ul>';
			
					foreach ($contentItems as $contentItem) {
						$toastMessage .= '<li>'. htmlspecialchars(formatTag($contentItem['id'], $contentItem['type'], $contentItem['alias'], $box['key']['id'])). '</li>';
					}
			
					$toastMessage .= '</ul>';
				}
		
				$box['toast'] = array(
					'message' => $toastMessage,
					'options' => array('timeOut' => 0, 'extendedTimeOut' => 0));
			}

		}
	}
	
	public function adminBoxSaveCompleted($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		
		//Go to the language in the enabled languages panel.
		//We should also reload the page if any of the language names were changed, or if this was the first language to be added
		closeFABWithFlags(['go_to_url' => 'zenario/admin/welcome.php?task=reload_sk&og='. rawurlencode('zenario__languages/panels/languages//'. $box['key']['id'])]);
		exit;
	}
}
