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


/*
fillAllAdminSlotControls(
	&$controls,
	$cID, $cType, $cVersion,
	$slotName, $containerId,
	$level, $moduleId, $instanceId, $isVersionControlled
)
*/


if (cms_core::$cVersion == cms_core::$adminVersion) {
	$couldEdit = checkPriv('_PRIV_EDIT_DRAFT', $cID, $cType);
	//$canEdit = checkPriv('_PRIV_EDIT_DRAFT', $cID, $cType, $cVersion);
} else {
	$couldEdit = false;
	//$canEdit = false;
}

$pageMode = array();
$isNest = !empty(cms_core::$slotContents[$slotName]['is_nest']);


//Check to see if there are entries on the item and layout layer
$overriddenPlugin = false;
if ($level == 1) {
	$overriddenPlugin = getRow(
		'plugin_layout_link',
		array('module_id', 'instance_id'),
		array('slot_name' => $slotName, 'layout_id' => cms_core::$layoutId));

	//Treat the case of hidden (item layer) and empty (layout layer) as just empty
	if (!$overriddenPlugin && !$moduleId) {
		$level = 0;
	}
}

$mrg = ['slotName' => $slotName];
$controls['info']['slot_lite_details']['label'] = adminPhrase('Slot: <span>[[slotName]]</span>', $mrg);
$controls['info']['slot_name']['label'] = adminPhrase('Slot class name: <span>[[slotName]]</span>', $mrg);

switch ($level) {
	case 1:
		$pageMode = ['item' => true];
		$controls['info']['in_this_slot']['label'] = adminPhrase('In this slot on this content item:');
		
		if (cms_core::$cVersion == cms_core::$adminVersion) {
			$couldChange = checkPriv('_PRIV_MANAGE_ITEM_SLOT', $cID, $cType);
			$canChange = checkPriv('_PRIV_MANAGE_ITEM_SLOT', $cID, $cType, $cVersion);
		} else {
			$couldChange = false;
			$canChange = false;
		}
		
		unset($controls['actions']['insert_reusable_on_layout_layer']);
		unset($controls['actions']['remove_from_layout_layer']);
		
		break;
	
	case 2:
		$pageMode = ['layout' => true];
		$couldChange = $canChange = checkPriv('_PRIV_MANAGE_TEMPLATE_SLOT');
		$controls['info']['in_this_slot']['label'] = adminPhrase('In this slot on this layout:');
		
		break;
	
	default:
		$couldChange = $canChange = false;
		break;
}

if ($isVersionControlled) {
	$settingsPageMode = ['edit' => true];
	$cssFrameworkPageMode = ['edit' => true];
} else {
	$settingsPageMode = $pageMode;
	$cssFrameworkPageMode = $pageMode;
}



//Format options if the slot is empty
if (!$moduleId) {
	$pageMode = array('edit' => true, 'layout' => true);
	
	if (!$level) {
		//Empty slots
		unset($controls['info']['opaque']);
		unset($controls['actions']['replace_reusable_on_item_layer']);
	
		//On the Layout Layer, add an option to insert a Wireframe version of each Plugin
		//that is flagged as uses wireframe.
		if (checkPriv('_PRIV_MANAGE_TEMPLATE_SLOT')) {
			$i = 0;
			foreach (getRowsArray(
				'modules',
				array('id', 'display_name'),
				array('status' => array('module_running', 'module_is_abstract'), 'is_pluggable' => 1, 'can_be_version_controlled' => 1),
				'display_name'
			) as $module) {
				$controls['actions'][] = array(
					'ord' => ++$i,
					'label' => adminPhrase('Insert a [[display_name]]', $module),
					'page_modes' => array('layout' => true),
					'onclick' => "zenarioA.addNewWireframePlugin(this, '". jsEscape($slotName). "', ". (int) $module['id']. ");"
				);
			}
		}
	} else {
		//Opaque slots
		unset($controls['info']['empty']);
		unset($controls['actions']['insert_reusable_on_item_layer']);
		unset($controls['actions']['insert_reusable_on_layout_layer']);
	}
	

} else {
	
	//Set up the embed buttons
	$embedLink = linkToItem(
		$cID, $cType, $fullPath = true, $request = '&zembedded=1&method_call=showSingleSlot&slotName='. $slotName,
		cms_core::$alias, $autoAddImportantRequests = false, $useAliasInAdminMode = true);

	$controls['info']['embed']['label'] .= '
		<a onclick="zenarioA.copyEmbedHTML(\''. jsEscape($embedLink). '\', \''. jsEscape($slotName). '\');">'.
			adminPhrase('Copy iframe HTML').
		'</a>';
	
	
	//Get information from the plugin itself
	
	//Show the wrapping html, id and css class names for the slot
	if (isset(cms_core::$slotContents[$slotName]['class']) && !empty(cms_core::$slotContents[$slotName]['class'])) {
		$a = array();
		cms_core::$slotContents[$slotName]['class']->zAPIGetCachableVars($a);
		$framework = $a[0];
		$cssClass = $a[4];
		
		$controls['info']['slot_css_class']['label'] = adminPhrase('CSS classes: <input class="zenario_class_name_preview" readonly="readonly" value="[[cssClass]]">', array('cssClass' => htmlspecialchars($cssClass)));
	} else {
		unset($controls['info']['slot_css_class']);
	}
	
	fillAdminSlotControlPluginInfo($moduleId, $instanceId, $isVersionControlled, $cID, $cType, $level, $isNest, $controls['info'], $controls['actions']);

	
	
	$controls['actions']['settings']['page_modes'] = $settingsPageMode;
	$controls['actions']['framework_and_css']['page_modes'] = $cssFrameworkPageMode;
	
	if ($isVersionControlled && cms_core::$cVersion == cms_core::$adminVersion) {
		if (cms_core::$locked) {
			$controls['actions']['settings']['label'] = $controls['actions']['settings']['label']['locked'];
		
		} elseif (!isDraft(cms_core::$status)) {
			if (!checkPriv('_PRIV_CREATE_REVISION_DRAFT')) {
				$controls['actions']['settings']['label'] = $controls['actions']['settings']['label']['cant_make_draft'];
			
			} elseif (cms_core::$status == 'trashed') {
				$controls['actions']['settings']['label'] = $controls['actions']['settings']['label']['trashed'];
			
			} elseif (cms_core::$status == 'hidden') {
				$controls['actions']['settings']['label'] = $controls['actions']['settings']['label']['hidden'];
			} else {
				$controls['actions']['settings']['label'] = $controls['actions']['settings']['label']['normal'];
			}
		} else {
			$controls['actions']['settings']['label'] = $controls['actions']['settings']['label']['normal'];
		}
	
	} elseif (!$isVersionControlled && checkPriv('_PRIV_MANAGE_REUSABLE_PLUGIN')) {
		$controls['actions']['settings']['label'] = $controls['actions']['settings']['label']['settings'];
	
	} elseif ($isVersionControlled || (!$isVersionControlled && checkPriv('_PRIV_VIEW_REUSABLE_PLUGIN'))) {
		$controls['actions']['settings']['label'] = $controls['actions']['settings']['label']['view_settings'];
	
	} else {
		unset($controls['actions']['settings']);
		unset($controls['actions']['framework_and_css']);
	}
	
	//Show options to convert the old nest plugins
	if ($isNest
	 && (($isVersionControlled && $canChange && $level == 1)
	  || (!$isVersionControlled && checkPriv('_PRIV_MANAGE_REUSABLE_PLUGIN')))
	 && checkRowExists('nested_plugins', array('is_slide' => 0, 'instance_id' => $instanceId))) {
		$controls['actions']['convert_nest']['page_modes'] = $pageMode;
	} else {
		unset($controls['actions']['convert_nest']);
	}
	
	
	if (!$couldChange || $level == 2) {
		unset($controls['actions']['move_on_item_layer']);
		unset($controls['actions']['remove_from_item_layer']);
	}
	if (!$couldChange || $level == 1) {
		unset($controls['actions']['move_on_layout_layer']);
		unset($controls['actions']['remove_from_layout_layer']);
	}
	if (!$couldChange || $level == 1 || $isVersionControlled) {
		unset($controls['actions']['insert_reusable_on_item_layer']);
	}
	if (!$couldChange/* || $isVersionControlled*/) {
		unset($controls['actions']['replace_reusable_on_item_layer']);
	}
	if (!$couldChange || ($level == 1 && !$overriddenPlugin) || $isVersionControlled) {
		unset($controls['actions']['hide_plugin']);
	}

	
	//Set the right CSS class around the slot and control box
	$controls['css_class'] .= ' zenario_level'. $level;
	
	if (isset(cms_core::$slotContents[$slotName]['class']) && !empty(cms_core::$slotContents[$slotName]['class'])) {
		
		$status = false;
		if (isset(cms_core::$slotContents[$slotName]['init'])) {
			$status = cms_core::$slotContents[$slotName]['init'];
		}
		
		if ($status === ZENARIO_401_NOT_LOGGED_IN || $status === ZENARIO_403_NO_PERMISSION) {
			$controls['css_class'] .= ' zenario_slotWithNoPermission';
		
		} elseif (!$status) {
			$controls['css_class'] .= ' zenario_slotNotShownInVisitorMode';
		}
		
		if (cms_core::$slotContents[$slotName]['class']->shownInMenuMode()) {
			$controls['css_class'] .= ' zenario_showSlotInMenuMode';
		} else {
			$controls['css_class'] .= ' zenario_hideSlotInMenuMode';
		}
		if ($isVersionControlled) {
			$controls['css_class'] .= ' zenario_wireframe';
		} else {
			$controls['css_class'] .= ' zenario_reusable';
		}
	}
	
	//Don't allow wireframe plugins to be replaced
	if ($isVersionControlled) {
		/*unset($controls['actions']['replace_reusable_on_item_layer']);*/
		unset($controls['actions']['insert_reusable_on_item_layer']);
		unset($controls['actions']['insert_reusable_on_layout_layer']);
	}
}

if (!$couldEdit) {
	unset($controls['actions']['replace_reusable_on_item_layer']);
	unset($controls['actions']['insert_reusable_on_item_layer']);
}


//If there is a hidden plugin at the layout layer, display info and some actions for that too
if ($overriddenPlugin) {
	$overriddenPluginIsNest = checkRowExists('nested_plugins', array('instance_id' => $overriddenPlugin['instance_id']));
	$overriddenIsVersionControlled = !$overriddenPlugin['instance_id'];
	fillAdminSlotControlPluginInfo($overriddenPlugin['module_id'], $overriddenPlugin['instance_id'], $overriddenIsVersionControlled, $cID, $cType, 2, $overriddenPluginIsNest, $controls['overridden_info'], $controls['overridden_actions']);
	
	if (!$couldChange) {
		unset($controls['overridden_actions']['show_plugin']);
	}
	
	if (!checkPriv('_PRIV_MANAGE_TEMPLATE_SLOT')) {
		unset($controls['overridden_actions']['remove_from_layout_layer']);
	}
	
	
	//Don't allow wireframe plugins to be replaced
	if ($overriddenIsVersionControlled) {
		unset($controls['actions']['insert_reusable_on_layout_layer']);
	}
	
	
} else {
	unset($controls['overridden_info']);
	unset($controls['overridden_actions']);
}

if (isset($controls['actions']['replace_reusable_on_item_layer'])) {
	if ($level == 2) {
		$replace = 'true';
	} else {
		$replace = 'false';
	}
	$controls['actions']['replace_reusable_on_item_layer']['onclick'] =
		str_replace('[[overides_layout]]', $replace, $controls['actions']['replace_reusable_on_item_layer']['onclick']);
}