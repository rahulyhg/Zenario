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


$forumMessage = getRow(ZENARIO_FORUM_PREFIX. "user_posts", array('message_text', 'poster_id'), array('id' => $postId));

$userId = $forumMessage['poster_id'];


$formFields = array(
	'cms_url' => absCMSDirURL(),
	'forum_link' =>
		linkToItem(
			$this->forum['forum_content_id'], $this->forum['forum_content_type'], true,
			'', false, false, true),
	'forum_title' => getItemTitle($this->forum['forum_content_id'], $this->forum['forum_content_type']),
	'message' => $forumMessage['message_text'],
	'thread_link' =>
		linkToItem(
			$this->forum['thread_content_id'], $this->forum['thread_content_type'], true,
			'&forum_thread='. $this->threadId. '&comm_page='. $this->page, false, false, true),
	'thread_title' => $newThreadTitle? $newThreadTitle : getRow(ZENARIO_FORUM_PREFIX. 'threads', 'title', $this->threadId),
	'poster_screen_name' => '');

if (setting('user_use_screen_name')) {
	$formFields['poster_screen_name'] = userScreenName($userId);
}

if ($this->setting('send_notification_email')
 && $this->setting('notification_email_address')
 && (($newThreadTitle === false && $this->setting('post_notification_email_template'))
  || ($newThreadTitle !== false && $this->setting('new_thread_notification_email_template')))
 && inc('zenario_email_template_manager')) {
	
	zenario_email_template_manager::sendEmailsUsingTemplate(
		$this->setting('notification_email_address'),
		$newThreadTitle === false?
			$this->setting('post_notification_email_template')
		 :	$this->setting('new_thread_notification_email_template'),
		$formFields,
		array(),
		array(),
		array('message' => true));
}





if ($newPost
 && (($newThreadTitle === false && $this->setting('enable_thread_subs') && $this->setting('post_subs_email_template'))
  || ($newThreadTitle !== false && $this->setting('enable_forum_subs') && $this->setting('new_thread_subs_email_template')))
 && inc('zenario_email_template_manager')) {
	
	$sql = "
		SELECT u.id, [u.salutation], [u.first_name], [u.last_name], [u.screen_name], [u.email]
		FROM [ZENARIO_COMMENTS_PREFIX.user_subscriptions AS us]
		INNER JOIN [users AS u]
		   ON u.id = us.user_id
		  AND u.status = 'active'
		INNER JOIN [ZENARIO_COMMENTS_PREFIX.users AS cu]
		   ON cu.user_id = u.id
		  AND IFNULL(cu.latest_activity > us.last_notified, true)
		WHERE us.forum_id = [0]
		  AND us.thread_id = [1]
		  AND us.user_id != [2]";
	
	$result = sqlSelect($sql, [$this->forumId, $newThreadTitle === false? $this->threadId : 0, $userId]);
	while ($row = sqlFetchAssoc($result)) {
		if ($row['email']) {
			
			foreach ($row as $fieldName => &$field) {
				$formFields['subscriber_'. $fieldName] = $field;
			}
			
			if (!setting('user_use_screen_name')) {
				$formFields['subscriber_screen_name'] = '';
			}
			
			zenario_email_template_manager::sendEmailsUsingTemplate(
				$row['email'],
				$newThreadTitle === false?
					$this->setting('post_subs_email_template')
				 :	$this->setting('new_thread_subs_email_template'),
				$formFields,
				array(),
				array(),
				array('message' => true));
			
			updateRow(
				ZENARIO_COMMENTS_PREFIX. 'user_subscriptions',
				array('last_notified' => now()),
				array(
					'user_id' => $row['id'],
					'forum_id' => $this->forumId,
					'thread_id' => $newThreadTitle === false? $this->threadId : 0));
		}
	}
}

?>