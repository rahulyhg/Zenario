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


$backs = [];

if ($this->usesConductor && $this->state) {

	if ($addCurrent) {
		$backs[$this->state] = ['state' => $this->state, 'slide' => $this->slides[$this->slideNum]];
	}


	$backToState = false;
	if (!empty($this->commands['back'])
	 && empty($this->commands['back']->cID)) {
		$backToState = $this->commands['back']->toState;
	}

	while ($backToState
	 && !isset($backs[$backToState])
	 && isset($this->statesToSlides[$backToState])
	) {
		$backs[$backToState] = ['state' => $backToState, 'slide' => $this->slides[$this->statesToSlides[$backToState]]];
	
		$backToState = ze\sql::fetchValue("
			SELECT to_state
			FROM ". DB_PREFIX. "nested_paths
			WHERE instance_id = ". (int) $this->instanceId. "
			  AND from_state = '". ze\escape::sql($backToState). "'
			  AND command IN ('back', 'close')
			ORDER BY to_state
			LIMIT 1
		");
	}

	$backs = array_reverse($backs);
	
	//Define requests for each link
	$lastBack = false;
	foreach ($backs as &$back) {
		$requests = [];
		
		//If we're generating a link to the current state, keep all of the registered get requests
		if ($back['state'] == $this->state) {
			foreach(ze::$importantGetRequests as $reqVar => $defaultValue) {
				if (isset($_GET[$reqVar]) && $_GET[$reqVar] != $defaultValue) {
					$requests[$reqVar] = $_GET[$reqVar];
				}
			}
		}
		
		//Loop through each of the variables needed by the destination
		foreach ($back['slide']['request_vars'] as $reqVar) {
			//Check the settings on the destination to see if it needs that variable.
			//If so then try to add it from the core variables.
			if (empty($requests[$reqVar]) && !empty(ze::$vars[$reqVar])) {
				$requests[$reqVar] = ze::$vars[$reqVar];
			}
		}
		
		$requests['state'] = $back['state'];
		unset($requests['slideId']);
		unset($requests['slideNum']);
		$back['requests'] = $requests;
		
		$slideId = $back['slide']['slide_id'];
		$slideNum = $back['slide']['slide_num'];
		
		//Check to see if there is an egg on this slide that generates smart breadcrumbs
		$sql = "
			SELECT np.id, np.slide_num, np.ord, np.module_id, np.framework, np.css_class, np.cols, np.small_screens
			FROM ". DB_PREFIX. "nested_plugins AS np
			WHERE np.instance_id = ". (int) $this->instanceId. "
			  AND np.is_slide = 0
			  AND np.slide_num = ". (int) $slideNum. "
			  AND np.makes_breadcrumbs > 1
			ORDER BY np.ord
			LIMIT 1";
		
		if ($egg = ze\sql::fetchAssoc($sql)) {
			$slotNameNestId = $this->slotName. '-'. $egg['id'];
			
			//If the plugin isn't already running, run it now
			if (!isset($this->modules[$slideNum])) {
				$this->modules[$slideNum] = [];
			}
			if (!isset(ze::$slotContents[$slotNameNestId])) {
				if ($this->initEgg($slotNameNestId, $egg)) {
					$this->initEggInstance($slotNameNestId, false, $egg['id'], $slideId, false);
				}
			}
			
			//Check to see whether it's running and the init returned true
			if (!empty(ze::$slotContents[$slotNameNestId]['init'])
			 && !empty(ze::$slotContents[$slotNameNestId]['class'])) {
				
				//Call the placeholder method and see if it outputs any breadcrumbs
				$back['smart'] = ze::$slotContents[$slotNameNestId]['class']->generateSmartBreadcrumbs();
				
				if (!is_array($back['smart'])
				 || empty($back['smart'])) {
					unset($back['smart']);
				}
			}
		}
		
		if ($lastBack && !empty($lastBack['smart'])) {
		
			foreach ($lastBack['smart'] as &$vbc) {
				
				if (!empty($vbc['request']) && is_array($vbc['request'])) {
					
					//Check whether the requests for this breadcrumb look like
					//they match the request for the main breadcrumb.
					//If so, highlight that one as the current one
					$isCurrent = true;
					foreach ($vbc['request'] as $key => $value) {
						
						//If we see a key such as "dataPoolId2", and it doesn't match,
						//try again with just "dataPoolId".
						if (!isset($requests[$key])) {
							switch ($key) {
								case 'dataPoolId1':
								case 'dataPoolId2':
								case 'dataPoolId3':
								case 'dataPoolId4':
								case 'dataPoolId5':
									$key = 'dataPoolId';
							}
						}
						
						if (!isset($requests[$key])
						 || $requests[$key] != $value) {
							$isCurrent = false;
						}
					}
				} else {
					$isCurrent = false;
					$vbc['request'] = [];
				}
				
				if (!isset($vbc['current'])) {
					$vbc['current'] = $isCurrent;
				}
				
				$vbc['request']['state'] = $requests['state'];
			}
		}
		
		$lastBack = &$back;
	}
	
	//If there are breadcrumbs on this level, we need to try and work out which slide they will go to
	if ($lastBack && !empty($lastBack['smart'])) {
		
		if ($this->forwardCommand && isset($this->commands[$this->forwardCommand])) {
			foreach ($lastBack['smart'] as &$vbc) {
				$vbc['request']['state'] = $this->commands[$this->forwardCommand]->toState;
				$vbc['current'] = false;
			}
		} else {
			unset($lastBack['smart']);
		}
	}
	
}


return $backs;
