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

class zenario_content_notifications extends ze\moduleBaseClass {
	
	
	public static function getNotes($cID, $cType, $cVersion) {
		return ze\row::getAssocs(
			ZENARIO_CONTENT_NOTIFICATIONS_PREFIX. 'versions_mirror', true, 
			['content_id' => $cID, 'content_type' => $cType, 'content_version' => $cVersion],
			'datetime_requested');
	}
	
	public static function noteHeader($note) {
		$note['formatted_datetime_requested'] = ze\date::formatDateTime($note['datetime_requested']);
		$note['admin_name'] = ze\admin::formatName($note['admin_id']);
		$note['tag'] = ze\content::formatTag($note['content_id'], $note['content_type']);
		$note['link'] = ze\link::toItem($note['content_id'], $note['content_type'], $fullPath = true);
		
		return ze\admin::phrase(
'Action requested: [[action_requested]]
Requested by: [[admin_name]]
On: [[formatted_datetime_requested]]
Content item: [[tag]]
Link: [[link]]', $note);
	}

	protected function newNote(&$box, &$values) {
		return [
				'content_id' => $box['key']['cID'],
				'content_type' => $box['key']['cType'],
				'content_version' => $box['key']['cVersion'],
				'admin_id' => ze\admin::id(),
				'action_requested' => $values['action_requested'],
				'datetime_requested' => ze\date::now(),
				'note' => $values['note']
			];
	}
	
	protected static function getListOfAdminsWhoReceiveNotifications($adminId = false) {
		$sql = "
			SELECT DISTINCT a.id, a.email
			FROM ". DB_PREFIX. "admins AS a
			INNER JOIN ". DB_PREFIX. ZENARIO_CONTENT_NOTIFICATIONS_PREFIX. "admins_mirror AS am
			   ON am.admin_id = a.id
			  AND am.content_request_notification = 1
			INNER JOIN " . DB_PREFIX . "action_admin_link as aal 
			   ON aal.admin_id = a.id
			  AND aal.action_name IN ('_ALL', '_PRIV_APPEAR_ON_CONTENT_REQUEST_RECIPIENT_LIST')
			WHERE a.status = 'active'";
		
		if ($adminId) {
			$sql .= "
			  AND a.id = ". (int) $adminId;
		}

		$result = ze\sql::select($sql);
		
		if ($adminId) {
			return (bool) ze\sql::fetchRow($result);
		
		} else {
			$admins_list = [];
			while ($row = ze\sql::fetchAssoc($result)) {
				$admins_list[$row['id']] = $row;
			}
			return $admins_list;
		}
	}

	public function fillAdminToolbar(&$adminToolbar, $cID, $cType, $cVersion) {

		if (ze\content::isDraft(ze::$status)
		 && ($notes = self::getNotes($cID, $cType, $cVersion))
		 && (!empty($notes))) {
			$adminToolbar['sections']['icons']['buttons']['no_request']['hidden'] = true;
		} else {
			$adminToolbar['sections']['icons']['buttons']['request']['hidden'] = true;
		}
		
		//Replace the status button with the Request to Publish button if someone can't publish a draft.
		//Also, if this item could be published, but this admin doesn't have the rights to publish it,
		//show the Request to Publish button where the Publish button usually is.
		if (ze\content::isDraft(ze::$status) && !ze\priv::check('_PRIV_PUBLISH_CONTENT_ITEM', $cID, $cType)) {
			unset($adminToolbar['sections']['status_button']['buttons']['status_button']);
		
		} elseif (!ze\content::isDraft(ze::$status) || ze\priv::check('_PRIV_PUBLISH_CONTENT_ITEM', $cID, $cType)) {
			unset($adminToolbar['sections']['status_button']['buttons']['request_publish']);
			unset($adminToolbar['sections']['edit']['buttons']['request_publish']);
			unset($adminToolbar['sections']['restricted_editing']['buttons']['request_publish']);
		}
		
		//If this item could be trashed, but this admin doesn't have the rights to trashed it,
		//show the Request to Rrash button where the Rrash button usually is.
		if (!ze\contentAdm::allowTrash($cID, $cType, ze::$status) || ze\priv::check('_PRIV_HIDE_CONTENT_ITEM', $cID, $cType)) {
			unset($adminToolbar['sections']['edit']['buttons']['request_trash']);
			unset($adminToolbar['sections']['restricted_editing']['buttons']['request_trash']);
		}
	}
	
	protected static function getAdminNotifications($admin_id) {
		return ze\row::get(ZENARIO_CONTENT_NOTIFICATIONS_PREFIX . 'admins_mirror', 
					['content_request_notification', 'draft_notification', 
						'published_notification', 'menu_node_notification'], $admin_id);
	}


	public function fillAdminBox($path, $settingGroup, &$box, &$fields, &$values) {
		if ($c = $this->runSubClass(__FILE__)) {
			return $c->fillAdminBox($path, $settingGroup, $box, $fields, $values);
		}
		
		switch ($path) {
			case 'zenario_admin':
				if ($details = self::getAdminNotifications($box['key']['id'])) {
					$values['notifications_tab/draft_notification'] = $details['draft_notification'];
					$values['notifications_tab/published_notification'] = $details['published_notification'];
					$values['notifications_tab/menu_node_notification'] = $details['menu_node_notification'];
					$values['notifications_tab/content_request_notification'] = $details['content_request_notification'];
				}
				
				break;
		}
	}

	public function formatAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		if ($c = $this->runSubClass(__FILE__)) {
			return $c->formatAdminBox($path, $settingGroup, $box, $fields, $values, $changes);
		}
		
		switch ($path) {
			case 'zenario_admin':
				
				switch ($values['permissions/permissions']) {
					case 'all_permissions':
						$notificationPerms = true;
						break;
					
					case 'specific_actions':
						$notificationPerms = 
							!empty($values['permissions/perm_publish_permissions'])
						 && strpos($values['permissions/perm_publish_permissions'], '_PRIV_APPEAR_ON_CONTENT_REQUEST_RECIPIENT_LIST') !== false;
						break;
					
					default:
						$notificationPerms = false;
				}
				
				$fields['notifications_tab/no_notifications']['hidden'] = $notificationPerms;
				
				$fields['notifications_tab/content_request_notification']['hidden'] =
				$fields['notifications_tab/draft_notification']['hidden'] =
				$fields['notifications_tab/published_notification']['hidden'] =
				$fields['notifications_tab/menu_node_notification']['hidden'] = !$notificationPerms;
				
				//Allow an admin to edit their own notifications, otherwise check the _PRIV_EDIT_ADMIN permission
				$box['tabs']['notifications_tab']['edit_mode']['enabled'] =
					$notificationPerms && ($box['key']['id'] == ze\admin::id() || ze\priv::check('_PRIV_EDIT_ADMIN'));
				
				break;
		}
	}

	public function validateAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes, $saving) {
		if ($c = $this->runSubClass(__FILE__)) {
			return $c->validateAdminBox($path, $settingGroup, $box, $fields, $values, $changes, $saving);
		}
	}

	public function saveAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		if ($c = $this->runSubClass(__FILE__)) {
			return $c->saveAdminBox($path, $settingGroup, $box, $fields, $values, $changes);
		}
		
		switch ($path) {
			case 'zenario_admin':
				
				//Allow an admin to edit their own notifications, otherwise check the _PRIV_EDIT_ADMIN permission
				if (ze\ring::engToBoolean($box['tabs']['notifications_tab']['edit_mode']['on'] ?? false)
				 && ($box['key']['id'] == ze\admin::id() || ze\priv::check('_PRIV_EDIT_ADMIN'))) {
					ze\row::set(
						ZENARIO_CONTENT_NOTIFICATIONS_PREFIX. 'admins_mirror',
						[
							'draft_notification' => $values['notifications_tab/draft_notification'],
							'published_notification' => $values['notifications_tab/published_notification'],
							'menu_node_notification' => $values['notifications_tab/menu_node_notification'],
							'content_request_notification' => $values['notifications_tab/content_request_notification']],
						$box['key']['id']);
				}
				
				break;
		}
	}
	
	
	//Delete requests for any versions that are deleted from the system
	public static function eventContentDeleted($cID, $cType, $cVersion) {
		ze\row::delete(
			ZENARIO_CONTENT_NOTIFICATIONS_PREFIX. 'versions_mirror',
			['content_id' => $cID, 'content_type' => $cType, 'content_version' => $cVersion]);
	}

	
	
	
	
	

	public static function processTemplate($template_key, &$fields) {
		$template = ze::setting($template_key);
		$re = '/\{\{([^}]+)\}\}/';

		$result = preg_replace_callback($re, function ($matches) use (&$fields) {
					$key = $matches[1];
					if (isset($fields[$key])) {
						return $fields[$key];
					}
					return $matches[0];
				}, $template);
		return $result;
	}

	protected static function sendEmailNotification($notification_type, $subject, $body) {
		
		$sql = "
			SELECT DISTINCT a.id, a.email
			FROM ". DB_PREFIX. "admins AS a
			INNER JOIN ". DB_PREFIX. ZENARIO_CONTENT_NOTIFICATIONS_PREFIX. "admins_mirror AS am
			   ON am.admin_id = a.id
			  AND am.`". ze\escape::sql($notification_type). "` = 1
			INNER JOIN " . DB_PREFIX . "action_admin_link as aal 
			   ON aal.admin_id = a.id
			  AND aal.action_name IN ('_ALL', '_PRIV_APPEAR_ON_CONTENT_REQUEST_RECIPIENT_LIST')
			WHERE a.status = 'active'
			  AND id != ". (int) ze\admin::id();

		$result = ze\sql::select($sql);
		while ($row = ze\sql::fetchAssoc($result)) {
			$addressToOverriddenBy = '';
			ze\server::sendEmail($subject, $body, $row['email'], $addressToOverriddenBy, $nameTo = false, $addressFrom = false, $nameFrom = false, $attachments = [], $attachmentFilenameMappings = [], $precedence = 'bulk', $isHTML = false);
		}
	}

	protected static function sendEmailContentNotification($notification_type, $subject, $mergeFields) {

		$mergeFields['published_drafted_trashed'] = $subject;
		$subject_template = self::processTemplate('content_notification_email_subject', $mergeFields);
		$body_template = self::processTemplate('content_notification_email_body', $mergeFields);
		self::sendEmailNotification($notification_type, $subject_template, $body_template);
	}

	protected static function insertFieldsForEmail(&$record) {
		$record['url'] = $url = ze\link::adminDomain() . SUBDIRECTORY;
		$record['admin_name'] = ze\admin::formatName();
		$record['admin_profile_url'] = 
			ze\link::absolute(). 'zenario/admin/organizer.php?#zenario__users/panels/administrators';
		$record['datetime_when'] = ze\date::formatDateTime(ze\date::now());
	}

	protected static function getContentFieldsForEmail($cID, $cType, $cVersion) {
		$record = ze\row::get('content_item_versions', ['tag_id', 'title'], ['id' => $cID, 'type' => $cType, 'version' => $cVersion]);
		self::insertFieldsForEmail($record);
		$url = $record['url'];
		$record['hyperlink'] = ze\link::toItem($cID, $cType, $fullPath = true, $request = '&cVersion=' . $cVersion);
		return $record;
	}

	public static function eventContentPublished($cID, $cType, $cVersion) {
		self::sendEmailContentNotification('published_notification', 'published', self::getContentFieldsForEmail($cID, $cType, $cVersion));
	}

	public static function eventDraftCreated($cIDTo, $cIDFrom, $cTypeTo, $cVersionTo, $cVersionFrom, $cTypeFrom) {
		self::sendEmailContentNotification('draft_notification', 'drafted', self::getContentFieldsForEmail($cIDTo, $cTypeTo, $cVersionTo));
	}

	protected static function sendEmailMenuNodeNotification($notification_type, $subject, $mergeFields) {

		$mergeFields['created_updated'] = $subject;
		$subject_template = self::processTemplate('menu_node_notification_email_subject', $mergeFields);
		$body_template = self::processTemplate('menu_node_notification_email_body', $mergeFields);
		self::sendEmailNotification($notification_type, $subject_template, $body_template);
	}

	protected static function getMenuFieldsForEmail($menuId, $languageId, $newText, $oldText = '') {

		$title = '';
		$tag_id = '';
		$link = '';
		
		if ($equiv = ze\menu::getContentItem($menuId)) {
			
			if (ze\content::langEquivalentItem($equiv['content_id'], $equiv['content_type'], $languageId)) {
				$title = ze\content::title($equiv['content_id'], $equiv['content_type']);
				$tag_id = ze\content::formatTag($equiv['content_id'], $equiv['content_type']);
				$link = ze\link::toItem($equiv['content_id'], $equiv['content_type'], $fullPath = true);
			}
		}
		
		$record = [];
		self::insertFieldsForEmail($record);
		$record['previous_menu_node'] = $oldText;
		$record['new_menu_node'] = $newText;
		$record['title'] = $title;
		$record['tag_id'] = $tag_id;
		$record['hyperlink'] = $link;
		return $record;
	}

	public static function eventMenuNodeTextAdded($menuId, $languageId, $newText) {
		self::sendEmailMenuNodeNotification('menu_node_notification', 'created', self::getMenuFieldsForEmail($menuId, $languageId, $newText));
	}

	public static function eventMenuNodeTextUpdated($menuId, $languageId, $newText, $oldText) {
		self::sendEmailMenuNodeNotification('menu_node_notification', 'updated', self::getMenuFieldsForEmail($menuId, $languageId, $newText, $oldText));
	}

}