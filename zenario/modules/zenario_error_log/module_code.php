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

class zenario_error_log extends module_base_class {
	
	//Called when a 404 error is triggered to log it
	public static function log404Error($pageAlias, $httpReferer = '') {
		$logged = date('Y-m-d H:i:s');
		if (strlen($pageAlias) > 255) {
			$pageAlias = mb_substr($pageAlias, 0, 252, 'UTF-8').'...';
		}
		if (strlen($httpReferer) > 65535) {
			$httpReferer = mb_substr($httpReferer, 0, 65532, 'UTF-8').'...';
		}
		insertRow(ZENARIO_ERROR_LOG_PREFIX.'error_log', array('logged' => $logged, 'page_alias' => $pageAlias, 'referrer_url' => $httpReferer));
		
		//Delete old log entries according to site setting
		if ($days = setting('period_to_delete_error_log')) {
			$date = date('Y-m-d', strtotime('-' . $days . ' day', strtotime($logged)));
			$sql = '
				DELETE FROM ' . DB_NAME_PREFIX . ZENARIO_ERROR_LOG_PREFIX . 'error_log
				WHERE logged <= "' . sqlEscape($date) . '"';
			sqlQuery($sql);
		}
	}
	
	public function fillOrganizerPanel($path, &$panel, $refinerName, $refinerId, $mode) {
		if ($c = $this->runSubClass(__FILE__)) {
			return $c->fillOrganizerPanel($path, $panel, $refinerName, $refinerId, $mode);
		}
	}
	
	public function saveAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		if ($c = $this->runSubClass(__FILE__)) {
			return $c->saveAdminBox($path, $settingGroup, $box, $fields, $values, $changes);
		}
	}

	
	//Scheduled task to report yesterdays errors
	public static function jobReportErrors() {
		$yesterday = new DateTime();
		$yesterday->sub(new DateInterval('P1D'));
		
		//Get all errors from yesterday
		$sql = '
			SELECT logged, page_alias, referrer_url
			FROM ' . DB_NAME_PREFIX . ZENARIO_ERROR_LOG_PREFIX . 'error_log
			WHERE logged BETWEEN "' . sqlEscape($yesterday->format('Y-m-d 00:00:00')) . '" AND "' . sqlEscape($yesterday->format('Y-m-d 23:59:59')) . '"
			ORDER BY logged DESC';
		$errors = sqlSelect($sql);
		//Send report
		if (sqlNumRows($errors) > 0) {
			echo sqlNumRows($errors) . " 404 Error(s):\n";
			while ($error = sqlFetchAssoc($errors)) {
				echo "\n---------------\n";
				echo adminPhrase('Logged:') . ' ' . $error['logged'] . "\n";
				echo adminPhrase('Requested page alias:') . ' ' . $error['page_alias'] . "\n";
				echo adminPhrase('Referrer URL:') . ' ' . $error['referrer_url'] . "\n";
			}
			return true;
		//If last action is over 1 week old and no errors send a message
		} else {
			$moduleId = getModuleId('zenario_error_log');
			$job = getRow(
				'jobs', 
				array('last_action'), 
				array(
					'manager_class_name' => 'zenario_scheduled_task_manager',
					'job_name' => 'jobReportErrors',
					'module_id' => $moduleId
				)
			);
			if (!$job['last_action']) {
				echo 'No errors to report';
				return true;
			}
			
			$now = new DateTime();
			$lastAction = new DateTime($job['last_action']);
			$lastWeek = $now->sub(new DateInterval('P1W'));
			$interval = $lastAction->diff($lastWeek);
			if (!$interval->invert) {
				echo 'No errors to report';
				return true;
			}
			
		}
		return false;
	}
}