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



class zenario_abstract_fea extends ze\moduleBaseClass {
	
	
	public function fillVisitorTUIX($path, &$tags, &$fields, &$values) {
		$this->checkThingEnabled();
		$tags['enable'] = $this->thingsEnabled;
		
		if ($microtemplate = $this->setting('microtemplate')) {
			$tags['microtemplate'] = $microtemplate;
		}
		
		foreach (array('columns', 'item_buttons', 'collection_buttons') as $tag) {
			if (isset($tags[$tag]) && is_array($tags[$tag])) {
				ze\tuix::addOrdinalsToTUIX($tags[$tag]);
			}
		}
	}
	
	
	protected function feaAJAXRequestIfNeeded($pages = []) {
		if (!$this->isFeaAJAX()) {
			$mode = $this->getMode();
			$path = $this->getPathFromMode($mode);
			$requests = $this->passRequests($mode, $path);
			$this->feaAJAXRequest($this->moduleClassName, $path, $requests, $mode, $pages);
		}
	}
	
	
	protected function getMode() {
		//From version 7.6, if you have a plugin, we'll only allow the plugin to run in the mode chosen in the plugin settings.
		//If you want extra modes then you'll either need to make links in the conductor, or links to other content items.
		if ($this->instanceId && ($mode = $this->setting('mode'))) {
			return $mode;
		
		//Otherwise check the mode in the request
		} elseif (!empty($_REQUEST['mode'])) {
			return $_REQUEST['mode'];
		
		//Otherwise check the path in the request
		} elseif (!empty($_REQUEST['path'])) {
			return $this->getModeFromPath($_REQUEST['path']);
		}
	}
	
	protected function passRequests($mode, $path) {
		$requests = ze::$vars;
		if ($this->idVarName && !isset($requests[$this->idVarName])) {
			$requests[$this->idVarName] = $_REQUEST[$this->idVarName] ?? false;
		}
		
		return $requests;
	}
	
	protected $thingsEnabled;
	protected function checkThingEnabled($thing = '') {
		
		if (!isset($this->thingsEnabled)) {
			$this->thingsEnabled = [];
			foreach ($this->zAPISettings as $settingName => &$value) {
				if (substr($settingName, 0, 7) == 'enable.') {
					$this->thingsEnabled[substr($settingName, 7)] = (bool) $value;
				}
			}
			$this->thingsEnabled[$this->setting('mode')] = true;
		}
		
		return !empty($this->thingsEnabled[$thing]);
	}
	
	protected function isFeaAJAX() {
		switch ($_REQUEST['method_call'] ?? false) {
			case 'fillVisitorTUIX':
			case 'formatVisitorTUIX':
			case 'validateVisitorTUIX':
			case 'saveVisitorTUIX':
				return true;
		}
		
		return false;
	}
	
	protected function getPathFromMode($mode) {
		return 'zenario_' . $mode;
	}
	
	protected function getModeFromPath($path) {
		return str_replace('zenario_', '', $path);
	}
	
	protected function gettingBreadcrumbs($tags = false) {
		return !$this->beingDisplayed || empty($tags);
	}

	public function init() {
		ze::requireJsLib('zenario/js/tuix.wrapper.js.php', null, true);
		
		return true;
	}

	public function showSlot() {
		//$this->twigFramework(array());
	}
	
	
	protected function feaAJAXRequest($libraryName, $path, $requests, $mode = '', $pages = []) {
		
		if (!$this->beingDisplayed) {
			return;
		}
		
		//If this is the initial page load, rather than doing an AJAX request,
		//instead write a script tag to the bottom of the page
		if (empty($_REQUEST['method_call'])) {
			$this->setScriptTag = $this->pluginVisitorTUIXLink(true, $path, $requests);
			$requests = -1;
		}
		
		//Initialise the FEA library
		$this->callScriptBeforeFoot(
			$libraryName, 'init',
			$this->containerId,
			$path, $requests, $mode, $pages);
	}
	
	protected $setScriptTag = '';
	
	public function addToPageFoot() {
		
		if ($this->setScriptTag !== '') {
			echo '
<script type="text/javascript" src="', htmlspecialchars($this->setScriptTag), '"></script>';
		}
	}
	
	
	protected function sqlSelect($sql) {
		return ze\sql::select($sql);
	}
	
	
	protected function populateItemsIdCol($path, &$tags, &$fields, &$values) {
		return 'id';
	}
	protected function populateItemsSelect($path, &$tags, &$fields, &$values) {
		return "SELECT id, name";
	}
	protected function populateItemsSelectCount($path, &$tags, &$fields, &$values) {
		return '
			SELECT COUNT(*)';
	}
	protected function populateItemsFrom($path, &$tags, &$fields, &$values) {
		return "FROM ". DB_NAME_PREFIX. "table";
	}
	protected function populateItemsWhere($path, &$tags, &$fields, &$values) {
		return "WHERE false";
	}
	protected function populateItemsOrderBy($path, &$tags, &$fields, &$values) {
		return "ORDER BY name";
	}
	protected function populateItemsGroupBy($path, &$tags, &$fields, &$values) {
		return '';
	}
	protected function populateItemsPageSize($path, &$tags, &$fields, &$values) {
		return false;
	}
	protected function formatItemRow(&$item, $path, &$tags, &$fields, &$values) {
		//...
	}
	
	//Functions for generating smart breadcrumbs.
	//They default to the functions for the items, unless overridden.
	protected function populateBreadcrumbsSelect() {
		$tags = $fields = $values = [];
		return $this->populateItemsSelect('', $tags, $fields, $values);
	}
	protected function populateBreadcrumbsFrom() {
		$tags = $fields = $values = [];
		return $this->populateItemsFrom('', $tags, $fields, $values);
	}
	protected function populateBreadcrumbsWhere() {
		$tags = $fields = $values = [];
		return $this->populateItemsWhere('', $tags, $fields, $values);
	}
	protected function populateBreadcrumbsOrderBy() {
		$tags = $fields = $values = [];
		return $this->populateItemsOrderBy('', $tags, $fields, $values);
	}
	protected function populateBreadcrumbsGroupBy() {
		$tags = $fields = $values = [];
		return $this->populateItemsGroupBy('', $tags, $fields, $values);
	}
	protected function populateBreadcrumbsPageSize() {
		$tags = $fields = $values = [];
		return $this->populateItemsPageSize('', $tags, $fields, $values);
	}
	protected function formatBreadcrumbRow(&$item) {
		//...
	}
	
	
	public function generateSmartBreadcrumbs() {
		
		$sql = $this->populateBreadcrumbsSelect(). "
			". $this->populateBreadcrumbsFrom(). "
			". $this->populateBreadcrumbsWhere(). "
			". $this->populateBreadcrumbsGroupBy(). "
			". $this->populateBreadcrumbsOrderBy();
		
		if ($pageSize = (int) $this->populateBreadcrumbsPageSize()) {
			$sql .= '
				LIMIT '. $pageSize;
		}
		
		$breadcrumbs = [];
		$result = ze\sql::select($sql);
		while ($row = ze\sql::fetchAssoc($result)) {
			$breadcrumb = $this->formatBreadcrumbRow($row);
			
			if (!empty($breadcrumb)) {
				$breadcrumbs[] = $breadcrumb;
			}
		}
		
		return $breadcrumbs;
	}
	
	
	
	protected function applyTwigSnippets($id, &$item, $path, &$tags, &$fields, &$values) {
		if ($this->checkIfTwigSnippetsAreUsed($tags)) {
			foreach ($tags['columns'] as $key => $col) {
				if (!empty($col['twig_snippet'])) {
					$item[$key] = $this->twigFramework(
						['id' => $id, 'item' => $item, 'column' => $col, 'tuix' => $tags],
						true, $col['twig_snippet']
					);
				}
			}
		}
	}
	
	protected $twigSnippetsUsed;
	protected function checkIfTwigSnippetsAreUsed(&$tags) {
		
		if (!isset($this->twigSnippetsUsed)) {
			$this->twigSnippetsUsed = false;
			
			if (!empty($tags['columns'])
			 && is_array($tags['columns'])) {
				foreach ($tags['columns'] as $col) {
					if (!empty($col['twig_snippet'])) {
						$this->twigSnippetsUsed = true;
						break;
					}
				}
			}
		}
		
		return $this->twigSnippetsUsed;
	}
	
	
	protected function populateItems($path, &$tags, &$fields, &$values) {
		
		$page = 1;
		$limit = '';
		$itemCount = 0;
		$idCol =  $this->populateItemsIdCol($path, $tags, $fields, $values);
		
		if ($pageSize = $this->populateItemsPageSize($path, $tags, $fields, $values)) {
			
			$sql = $this->populateItemsSelectCount($path, $tags, $fields, $values). "
				". $this->populateItemsFrom($path, $tags, $fields, $values). "
				". $this->populateItemsWhere($path, $tags, $fields, $values);
			
			$result = $this->sqlSelect($sql);
			$row = ze\sql::fetchRow($result);
			$itemCount = $row[0];
			
			if (ze\tuix::$feaDebugMode) {
				ze\tuix::$feaSelectCountQuery = $sql;
			}
			unset($sql, $row);
			
			if ((!$page = (int) ($_REQUEST['page'] ?? false))
			 || ($page > ceil($itemCount / $pageSize))) {
				$page = 1;
			}
			
			$limit = ze\sql::limit($page, $pageSize);
			$tags['__page_size__'] = $pageSize;
			$tags['__page__'] = $page;
		}
		
		$sql = $this->populateItemsSelect($path, $tags, $fields, $values). "
				". $this->populateItemsFrom($path, $tags, $fields, $values). "
				". $this->populateItemsWhere($path, $tags, $fields, $values). "
				". $this->populateItemsGroupBy($path, $tags, $fields, $values). "
				". $this->populateItemsOrderBy($path, $tags, $fields, $values). "
				". $limit;
		
		$result = $this->sqlSelect($sql);
		
		if (ze\tuix::$feaDebugMode) {
			ze\tuix::$feaSelectQuery = $sql;
		}
		unset($sql);
		
		$tags['items'] = array();
		$tags['__item_sort_order__'] = array();
		while ($item = ze\sql::fetchAssoc($result)) {
			$id = $item[$idCol];
			$this->formatItemRow($item, $path, $tags, $fields, $values);
			$this->applyTwigSnippets($id, $item, $path, $tags, $fields, $values);
			
			$tags['items'][$id] = $item;
			$tags['__item_sort_order__'][] = $item[$idCol];
			
			if (!$pageSize) {
				++$itemCount;
			}
		}
		$tags['__item_count__'] = $itemCount;
		
		if ($limit
		 && $pageSize < $itemCount) {
		 	
		 	$mrg = [
		 		'count' => $itemCount,
				'start' => ze\sql::pageStart($page, $pageSize) + 1,
				'stop' => min(ze\sql::pageStart($page + 1, $pageSize), $itemCount)
			];
		 	
			if ($this->checkThingEnabled('search_box') && !empty($_REQUEST['search'])) {
				$mrg['search'] = $_REQUEST['search'];
				$tags['__items_phrase__'] = $this->phrase('[[start]] - [[stop]] of [[count]] items found from search "[[search]]"', $mrg);
			} elseif ($itemCount) {
				$tags['__items_phrase__'] = $this->phrase('[[start]] - [[stop]] of [[count]] items', $mrg);
			}
		} else {
			if ($this->checkThingEnabled('search_box') && !empty($_REQUEST['search'])) {
				$mrg = ['search' => $_REQUEST['search']];
				$tags['__items_phrase__'] = $this->nphrase('1 item found from search "[[search]]"', '[[count]] items found from search "[[search]]"', $itemCount, $mrg);
			} elseif ($itemCount) {
				$tags['__items_phrase__'] = $this->nphrase('1 item', '[[count]] items', $itemCount);
			}
		}
	}
	
	protected function mergeCustomTUIX(&$tags) {
		if (($custom = $this->setting('~custom_json~'))
		 && ($custom = json_decode($custom, true))
		 && (!empty($custom))
		 && (is_array($custom))) {
			ze\tuix::merge($tags, $custom);
		}
	}
	
	
	
	protected function applySearchBarSetting(&$tags) {
	
		if (!$this->checkThingEnabled('search_box')) {
			$tags['hide_search_bar'] = true;
		} elseif (empty($_REQUEST['search'])) {
			$numberOfItemsRequired = $this->setting('search_box_items_required');
			if ($numberOfItemsRequired && count($tags['items']) < $numberOfItemsRequired) {
				$tags['hide_search_bar'] = true;
			}
		}
	}
	
	
	protected function setupOverridesForPhrases(&$box, &$fields, &$values) {
		
		$modePath = $this->getPathFromMode($values['first_tab/mode']);
        
        foreach (ze\row::getDistinctArray(
            'tuix_file_contents', 'path', array('type' => 'visitor', 'module_class_name' => $this->moduleClassName)
        ) as $feaPath) {
            if (isset($box['tabs']['phrases.'. $feaPath])) {
                $box['tabs']['phrases.'. $feaPath]['hidden'] = true;
            }
            if ($modePath == $feaPath) {
                if (isset($box['tabs']['phrases.'. $feaPath])) {
                    $box['tabs']['phrases.'. $feaPath]['hidden'] = false;
                } else {
                    $box['tabs']['phrases.'. $feaPath] = array(
                        'edit_mode' => $box['tabs']['first_tab']['edit_mode'],
                        'fields' => array(),
                        'label' => ze\admin::phrase('Phrases ([[mode]])', array('mode' => str_replace('_', ' ', $this->getModeFromPath($feaPath))))
                    );
                    $box['key']['feaPath'] = $feaPath;
                    ze\tuix::setupOverridesForPhrases($box, $box['tabs']['phrases.'. $feaPath]['fields'], $feaPath);
                }
            }
        }
	}
}