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

/*
	This file contains JavaScript source code.
	The code here is not the code you see in your browser. Before this file is downloaded:
	
		1. Compilation macros are applied (e.g. "foreach" is a macro for "for .. in ... hasOwnProperty").
		2. It is minified (e.g. using Google Closure Compiler).
		3. It may be wrapped togther with other files (this is to reduce the number of http requests on a page).
	
	For more information, see js_minify.shell.php for steps (1) and (2), and organizer.wrapper.js.php for step (3).
*/


zenario.lib(function(
	undefined,
	URLBasePath,
	document, window, windowOpener, windowParent,
	zenario, zenarioA, zenarioT, zenarioAB, zenarioAT, zenarioO,
	encodeURIComponent, defined, engToBoolean, get, htmlspecialchars, jsEscape, phrase,
	extensionOf, methodsOf, has,
	panelTypes
) {
	"use strict";


//Note: extensionOf() and methodsOf() are our shortcut functions for class extension in JavaScript.
	//extensionOf() creates a new class (optionally as an extension of another class).
	//methodsOf() allows you to get to the methods of a class.
var methods = methodsOf(
	panelTypes.form_builder = extensionOf(panelTypes.form_builder_base_class)
);

//Draw the panel, as well as the header at the top and the footer at the bottom
//This is called every time the panel is loaded, refreshed or when something in the header toolbar is changed.
methods.showPanel = function($header, $panel, $footer) {
	$header.html('').hide();
	$footer.html('').show();
	
	thus.sortOutItems();
	
	//Load main panel
	var html = thus.loadPanelHTML();
	$panel.html(html).show();
	
	//On initial load select the first page
	if (_.isEmpty(thus.tuix.items)) {
		thus.addNewPage(true);
	}
	if (!thus.currentPageId) {
		var pages = thus.getOrderedPages();
		if (pages.length > 0) {
			thus.currentPageId = pages[0].id;
		}
	}
	
	thus.editFieldName = false;
	thus.doneEditingName = false;
	thus.pagesReordered = false;
	thus.deletedPages = [];
	thus.deletedFields = [];
	thus.deletedValues = [];
	thus.changeDetected = false;
	thus.maxNewCustomField = 1;
	thus.maxNewCustomFieldValue = 1;
	
	thus.selectedDetailsPage = thus.selectedDetailsPage ? thus.selectedDetailsPage : false;
	thus.selectedFieldId = thus.selectedFieldId ? thus.selectedFieldId : false;
	thus.selectedPageId = thus.selectedPageId ? thus.selectedPageId : false;
	
	//Load right panel
	thus.loadPagesList(thus.currentPageId);
	thus.loadFieldsList(thus.currentPageId);
	
	//Load left panel
	if (thus.selectedFieldId) {
		thus.loadFieldDetailsPanel(thus.selectedFieldId, true);
	} else if (thus.selectedPageId) {
		thus.loadPageDetailsPanel(thus.selectedPageId, true);
	} else {
		thus.loadNewFieldsPanel(true);
	}
	
	//Show Growl message if saved changes
	if (thus.changesSaved) {
		thus.changesSaved = false;
		var toast = {
			message_type: 'success',
			message: 'Your changes have been saved!'
		};
		zenarioA.toast(toast);
	}
};

methods.sortOutItems = function() {
	var pageId, page, fieldId, field;
	foreach (thus.tuix.fields as fieldId => field) {
		//This array is turned into an object when parsed. Turn it back into an array...
		if (field.invalid_responses) {
			field.invalid_responses = _.toArray(field.invalid_responses);
		}
		if (field.visible_condition_checkboxes_field_value) {
			field.visible_condition_checkboxes_field_value = _.toArray(field.visible_condition_checkboxes_field_value);
		}
		if (field.mandatory_condition_checkboxes_field_value) {
			field.mandatory_condition_checkboxes_field_value = _.toArray(field.mandatory_condition_checkboxes_field_value);
		}
	}
	
	foreach (thus.tuix.items as pageId => page) {
		if (page.visible_condition_checkboxes_field_value) {
			page.visible_condition_checkboxes_field_value = _.toArray(page.visible_condition_checkboxes_field_value);
		}
		if (page.mandatory_condition_checkboxes_field_value) {
			page.mandatory_condition_checkboxes_field_value = _.toArray(page.mandatory_condition_checkboxes_field_value);
		}
	}
};

methods.loadPanelHTML = function() {
	var mergeFields = {
		form_title: thus.tuix.form_title
	};
	var html = thus.microTemplate('zenario_organizer_form_builder', mergeFields);
	return html;
};

methods.loadPageDetailsPanel = function(pageId, stopEffect) {
	thus.loadFieldDetailsPanel(pageId, stopEffect, true);
	
	var label, page, message, fields;
	$('#field__name').on('keyup', function() {
		label = thus.getPageLabel($(this).val());
		$('#organizer_form_tab_' + pageId + ' span').text(label);
	});
	
	$('#organizer_remove_form_tab').on('click', function(e) {
		page = thus.tuix.items[pageId];
		if (page) {
			message = '<p>Are you sure you want to delete this page?</p>';
			fields = thus.getOrderedFields(pageId);
			if (fields.length) {
				message += '<p>All fields on this page will be moved onto the previous page.</p>';
			}
			zenarioA.floatingBox(message, 'Delete', 'warning', true, false, function() {
				thus.deletePage(pageId);
			});
		}
	});
};

methods.loadFieldDetailsPanel = function(fieldId, stopEffect, isPage, justAdded) {	
	thus.editFieldName = false;
	thus.doneEditingName = false;
	
	var field;
	if (isPage) {
		field = thus.tuix.items[fieldId];
		field.type = 'page_break';
	} else {
		field = thus.tuix.fields[fieldId];
	}
	
	//Remove "just_added" status if editing after adding
	if (!justAdded && field.just_added) {
		delete(field.just_added);
	}
	
	var typePhrase = thus.getFieldReadableType(field.type);
	if (field.dataset_field_id) {
		typePhrase += ', linked field → ' + field.db_column;
	}
	
	//Load HTML
	var mergeFields = {
		mode: 'field_details',
		name: field.name,
		type: field.type,
		typePhrase: typePhrase,
		just_added: field.just_added,
		hide_tab_bar: thus.tuix.field_details.hide_tab_bar,
		is_tab: isPage
	};
	if (field.dataset_field_id) {
		mergeFields.type += ', dataset field';
		if (field.db_column) {
			mergeFields.type += ' (' + field.db_column + ')';
		}
	}
	
	var html = thus.microTemplate('zenario_organizer_form_builder_left_panel', mergeFields);
	var $div = $('#organizer_form_builder .form_fields_palette .form_fields_palette_outer').html(html);
	
	if (!stopEffect) {
		$div.hide().show('drop', {direction: 'left'}, 200);
	}
	
	//Load TUIX pages HTML
	var tuix = thus.tuix.field_details.tabs;
	var pages = thus.sortAndLoadTUIXPages(tuix, field, thus.selectedDetailsPage);
	var html = thus.getTUIXPagesHTML(pages);
	$('#organizer_field_details_tabs').html(html);
	
	//Add field details page events
	$('#organizer_field_details_tabs .tab').on('click', function() {
		var page = $(this).data('name');
		if (page && page != thus.selectedDetailsPage) {
			var errors = thus.saveCurrentOpenDetails();
			if (!errors) {
				thus.loadFieldDetailsPage(page, fieldId);
			}
		}
	});
	
	//Load TUIX fields html
	thus.loadFieldDetailsPage(thus.selectedDetailsPage, fieldId);
	
	//Add panel events
	
	//Go to new fields panel
	$('#organizer_field_details_inner span.add_new_field').on('click', function() {
		var errors = thus.saveCurrentOpenDetails();
		if (!errors) {
			thus.highlightField(false);
			thus.loadNewFieldsPanel();
		}
	});
	
	//Editing field name
	$('#zenario_field_details_header_content .edit_field_name_button').on('click', function() {
		$('#zenario_field_details_header_content .view_mode').hide();
		$('#zenario_field_details_header_content .edit_mode').show();
		thus.editFieldName = true;
	});
	$('#zenario_field_details_header_content .done_field_name_button').on('click', function() {
		$('#zenario_field_details_header_content .edit_mode').hide();
		$('#zenario_field_details_header_content .view_mode').show();
		
		thus.editFieldName = false;
		thus.doneEditingName = true;
		
		var name = $('#zenario_field_details_header_content .edit_mode input[name="name"]').val();
		field.name = name;
		$('#zenario_field_details_header_content .view_mode h5').text(name);
	});
	$('#field__name').on('keyup', function() {
		thus.changeMadeToPanel();
	});
	
	//Edit form settings
	$('input.form_settings').on('click', function() {
		thus.openFormSettings();
	});
};

methods.openFormSettings = function() {
	zenarioAB.open(
		'zenario_user_form', 
		{id: zenarioO.pi.refiner.id},
		undefined, undefined,
		function(key, values) {
			//Update form title (note if lots of changes might be better to redraw entire form)
			var title = '';
			if (values.details.show_title) {
				title = values.details.title;
			}
			thus.tuix.title = title;
			$('#organizer_form_builder .form_outer .form_header h5').text(title);
		}
	);
};

methods.loadPageDetailsPage = function(page, pageId, errors) {
	thus.loadFieldDetailsPage(page, pageId, errors);
};

methods.loadFieldDetailsPage = function(page, fieldId, errors) {
	var item;
	thus.selectedDetailsPage = page;
	
	if (thus.selectedFieldId && thus.tuix.fields[fieldId]) {
		item = thus.tuix.fields[fieldId]
	} else if (thus.selectedPageId && thus.tuix.items[fieldId]) {
		item = thus.tuix.items[fieldId];
		//Final page must be visible
		var pages = thus.getOrderedPages();
		for (var i = 0; i < pages.length; i++) {
			if (pages[i].id == item.id) {
				if (i == (pages.length - 1) && !thus.tuix.form_enable_summary_page) {
					item.visibility = 'visible';
				}
				break;
			}
		}
	}
	
	var path = 'field_details/' + page;
	var tuix = thus.tuix.field_details.tabs[page].fields;
	var fields = thus.loadFields(path, tuix, item);
	var formattedFields = thus.formatFieldDetails(fields, item, 'field', page);
	var sortedFields = thus.sortFields(formattedFields, item);
	var html = '';
	
	//Add any errors
	if (!_.isEmpty(errors)) {
		errors = thus.sortErrors(errors);
		for (var i = 0; i < errors.length; i++) {
			html += '<p class="error">' + errors[i] + '</p>';
		}
	}
	
	html += thus.getTUIXFieldsHTML(sortedFields);
	$('#organizer_field_details').html(html);
	$('#organizer_field_details_tabs .tab').removeClass('on');
	$('#field_tab__' + page).addClass('on');
	if (!_.isEmpty(errors)) {
		$('#organizer_field_details_form').effect({
			effect: 'bounce',
			duration: 125,
			direction: 'right',
			times: 2,
			distance: 5,
			mode: 'effect'
		});
	}
	
	//Add events
		
	if (page == 'details') {
		$('#field__field_label').on('keyup', function() {
			$('#organizer_form_field_' + fieldId + ' .label').text($(this).val());
			
			if (item.just_added && !thus.editFieldName && !thus.doneEditingName) {
				var fieldName = $(this).val().replace(/:/g, '');
				$('#field__name').val(fieldName);
				$('#field_display__name').text(fieldName);
			}
		});
		
		$('#field__note_to_user').on('keyup', function() {
			var value = $(this).val().trim();
			$('#organizer_form_field_note_below_' + fieldId + ' div.zenario_note_content').text(value);
			$('#organizer_form_field_note_below_' + fieldId).toggle(value !== '');
		});
		
		if (item.type == 'text' || item.type == 'textarea') {
			$('#field__placeholder').on('keyup', function() {
				$('#organizer_form_field_' + fieldId + ' :input').prop('placeholder', $(this).val());
			});
		} else if (item.type == 'section_description') {
			$('#field__description').on('keyup', function() {
				$('#organizer_form_field_' + fieldId + ' .description').text($(this).val());
			});
		} else if (item.type == 'calculated') {
			$('#edit_field_calculation').on('click', function() {
				//Get all numeric text fields
				var numericFields = {};
				foreach (thus.tuix.fields as var tFieldId => var tField) {
					if ((tField.type == 'text' && ['number', 'integer', 'floating_point'].indexOf(tField.field_validation) != -1) 
						|| (tField.type == 'calculated' && fieldId != tFieldId)
					) {
						numericFields[tFieldId] = {label: tField.name, ord: tField.ord};
					}
				}
				
				var key = {
					id: fieldId, 
					title: 'Editing the calculation for the field "' + item.name + '"',
					calculation_code: JSON.stringify(item.calculation_code)
				};
				var values = {
					details: {
						dummy_field: JSON.stringify(numericFields)
					}
				};
				
				zenarioAB.open(
					'zenario_field_calculation', 
					key,
					undefined, values,
					function(key, values) {
						if (values.details.calculation_code) {
							item.calculation_code = JSON.parse(values.details.calculation_code);
						} else {
							item.calculation_code = '';
						}
						thus.changeMadeToPanel();
						thus.saveCurrentOpenDetails();
						thus.loadFieldDetailsPage('details', fieldId);
					}
				);
			});
		}
		
	} else if (page == 'advanced') {
		$('#field__css_classes').on('keyup', function() {
			$('#organizer_form_field_' + fieldId + ' .form_field_classes .css').toggle(!!$(this).val()).prop('title', $(this).val());
		});
		$('#field__div_wrap_class').on('keyup', function() {
			$('#organizer_form_field_' + fieldId + ' .form_field_classes .div').toggle(!!$(this).val()).prop('title', $(this).val());
		});
		
	} else if (page == 'values') {
		if (formattedFields.values && !formattedFields.values._hidden) {
			var values = thus.getOrderedFieldValues(fieldId);
			html = thus.microTemplate('zenario_organizer_admin_box_builder_field_value', values);
			
			$('#field_values_list').html(html).sortable({
				containment: 'parent',
				tolerance: 'pointer',
				axis: 'y',
				start: function(event, ui) {
					thus.startIndex = ui.item.index();
				},
				stop: function(event, ui) {
					if (thus.startIndex != ui.item.index()) {
						thus.saveFieldListOfValues(thus.tuix.fields[fieldId]);
						thus.loadFieldValuesListPreview(fieldId);
						thus.changeMadeToPanel();
					}
				}
			});
			
			$('#field_values_list input').on('keyup', function() {
				var id = $(this).data('id');
				$('#organizer_field_value_' + id + ' label').text($(this).val());
				thus.changeMadeToPanel();
			});
			
			//Add new field value
			$('#organizer_add_a_field_value').on('click', function() {
				thus.addFieldValue(item);
				thus.saveItemTUIXPage('field_details', page, item);
				thus.loadFieldDetailsPage(page, fieldId);
				thus.loadFieldValuesListPreview(fieldId);
				thus.changeMadeToPanel();
			});
			
			//Delete field value
			$('#field_values_list .delete_icon').on('click', function() {
				var id = $(this).data('id');
				if (item.lov[id]) {
					thus.deletedValues.push(id);
					delete(item.lov[id]);
				}
				thus.saveItemTUIXPage('field_details', page, item);
				thus.loadFieldDetailsPage(page, fieldId);
				thus.loadFieldValuesListPreview(fieldId);
				thus.changeMadeToPanel();
			});
		}
	} else if (page == 'translations') {
		var transFieldNamesList = _.toArray(thus.tuix.field_details.tabs[page].translatable_fields);
		var transFieldNames = {};
		for (var i = 0; i < transFieldNamesList.length; i++) {
			transFieldNames[transFieldNamesList[i]] = true;
		}
		
		//Currently translations only support fields on the details page
		page = 'details';
		path = 'field_details/' + page;
		tuix = thus.tuix.field_details.tabs[page].fields;
		fields = thus.loadFields(path, tuix, item);
		formattedFields = thus.formatFieldDetails(fields, item, 'field', page);
		sortedFields = thus.sortFields(formattedFields, item);
		
		var visibleTransFieldNamesList = [];
		foreach (sortedFields as var i => var sField) {
			if (transFieldNames[sField.id] && !sField._hidden) {
				visibleTransFieldNamesList.push(sField.id);
			}
		}
		
		var languages = thus.sortLanguages(thus.tuix.languages);
		var transFields = [];
		foreach (visibleTransFieldNamesList as var i => var transFieldName) {
			if (formattedFields[transFieldName] && !formattedFields[transFieldName]._hidden) {
				var existingTranslations = {};
				if (item._translations && item._translations[transFieldName]) {
					existingTranslations = item._translations[transFieldName];
				}
				var transField = {};
				if (tuix[transFieldName] && tuix[transFieldName].label) {
					transField.label = tuix[transFieldName].label;
				}
				if (item[transFieldName]) {
					transField.value = item[transFieldName];
				} else {
					transField.value = '(No text is defined in the default language)';
					transField.disabled = true;
				}
				
				transField.phrases = [];
				foreach (languages as var j => var language) {
					if (parseInt(language.translate_phrases)) {
						var phrase = (existingTranslations.phrases && existingTranslations.phrases[language.id] && !transField.disabled) ? existingTranslations.phrases[language.id] : '';
						transField.phrases.push({
							field_column: transFieldName,
							language_id: language.id,
							language_name: language.english_name,
							phrase: phrase,
							disabled: transField.disabled
						});
					}
				}
				transFields.push(transField);
			}
		}
		
		var html = thus.microTemplate('zenario_organizer_form_builder_translatable_field', transFields);
		$('#organizer_field_translations').html(html);
		
		$('#organizer_field_translations input').on('keyup', function() {
			thus.changeMadeToPanel();
		});
		
	} else if (page == 'crm') {
		var prefix = '';
		if (page == 'crm') {
			prefix = 'crm';
		}
		var crm_value_name = prefix + '_value';
		
		$('#organizer_crm_button__set_labels').on('click', function() {
			thus.changeMadeToPanel();
			$('#organizer_field_crm_values input.crm_value_input').each(function(i, e) {
				var id = $(this).data('id');
				var value = '';
				if (thus.tuix.fields[fieldId].lov && thus.tuix.fields[fieldId].lov[id]) {
					value = thus.tuix.fields[fieldId].lov[id].label;
				}
				$(this).val(value);
			});
		});
		
		$('#organizer_crm_button__set_values').on('click', function() {
			thus.changeMadeToPanel();
			$('#organizer_field_crm_values input.crm_value_input').each(function(i, e) {
				var id = $(this).data('id');
				$(this).val(id);
			});
		});
		
		var mergeFields = {
			values: []
		};
		
		if (item.type == 'checkbox' || item.type == 'group' || item.type == 'consent') {
			var values = {
				'unchecked': {
					label: 0, 
					ord: 1
				}, 
				'checked': {
					label: 1, 
					ord: 2
				}
			};
			if (item['_' + prefix + '_data'] && item['_' + prefix + '_data'].values) {
				foreach (item['_' + prefix + '_data'].values as var valueId => var value) {
					if (values[valueId] && defined(value[prefix + '_value'])) {
						values[valueId][prefix + '_value'] = value[prefix + '_value'];
					}
				}
			}
			foreach (values as var valueId => var value) {
				value.id = valueId;
				value.crm_value_name = crm_value_name;
				mergeFields.values.push(value);
			}
			
			mergeFields.values.sort(thus.sortByOrd);
		} else {
			var values = thus.getOrderedFieldValues(fieldId);
			foreach (values as var valueId => var value) {
				value.crm_value_name = crm_value_name;
				mergeFields.values.push(value);
			}
		}
		
		var html = thus.microTemplate('zenario_organizer_form_builder_crm_values', mergeFields);
		$('#organizer_field_crm_values').html(html);
		
		$('#organizer_field_crm_values input.crm_value_input').on('keyup', function() {
			thus.changeMadeToPanel();
		});
	}
};

//Calculation admin box methods
methods.calculationAdminBoxAddSomthing = function(type, value) {
	var code = false;
	switch (type) {
		case 'operation_addition':
		case 'operation_subtraction':
		case 'operation_multiplication':
		case 'operation_division':
		case 'parentheses_open':
		case 'parentheses_close':
			code = {type: type};
			break;
		case 'static_value':
			if (value !== '' && !isNaN(+value)) {
				code = {type: type, value: +value};
				$('#static_value').val('');
			}
			break;
		case 'field':
			code = {type: type, value: value};
			$('#numeric_field').val('');
			break;
	}
	if (code) {
		var calculationCode = [];
		if ($('#calculation_code').val()) {
			calculationCode = JSON.parse($('#calculation_code').val());
		}
		calculationCode.push(code);
		$('#calculation_code').val(JSON.stringify(calculationCode));
		
		thus.calculationAdminBoxUpdateDisplay(calculationCode);
	}
};
methods.calculationAdminBoxDelete = function() {
	var calculationCode = [];
	if ($('#calculation_code').val()) {
		calculationCode = JSON.parse($('#calculation_code').val());
		if (calculationCode) {
			calculationCode.pop();
			$('#calculation_code').val(JSON.stringify(calculationCode));
		}
	}
	thus.calculationAdminBoxUpdateDisplay(calculationCode);
};
methods.calculationAdminBoxUpdateDisplay = function(calculationCode) {
	var calculationDisplay = thus.getCalculationCodeDisplay(calculationCode);
	$('#zenario_calculation_display').text(calculationDisplay);
};
methods.getCalculationCodeDisplay = function(calculationCode) {
	var calculationDisplay = '',
		fields, fieldId, field, lastIsParenthesisOpen, i, name;
	if (calculationCode) {
		fields = {};
		foreach (thus.tuix.fields as fieldId => field) {
			fields[fieldId] = field;
		}
		
		lastIsParenthesisOpen = false;
		for (i = 0; i < calculationCode.length; i++) {
			if (!lastIsParenthesisOpen && calculationCode[i].type != 'parentheses_close') {
				calculationDisplay += ' ';
			} else if (lastIsParenthesisOpen) {
				lastIsParenthesisOpen = false;
			}
			
			switch (calculationCode[i].type) {
				case 'operation_addition':
					calculationDisplay += '+';
					break;
				case 'operation_subtraction':
					calculationDisplay += '-';
					break;
				case 'operation_multiplication':
					calculationDisplay += '×';
					break;
				case 'operation_division':
					calculationDisplay += '÷';
					break;
				case 'parentheses_open':
					calculationDisplay += '(';
					lastIsParenthesisOpen = true;
					break;
				case 'parentheses_close':
					calculationDisplay += ')';
					break;
				case 'static_value':
					calculationDisplay += calculationCode[i].value;
					break;
				case 'field':
					name = '';
					if (fields[calculationCode[i].value]) {
						name = fields[calculationCode[i].value].name;
					} else {
						name = 'UNKNOWN FIELD';
					}
					calculationDisplay += '"' + name + '"';
					break;
			}
			calculationDisplay = calculationDisplay.trim();
		}
	}
	return calculationDisplay;
};


methods.formatFieldDetails = function(fields, item, mode, page) {
	if (mode == 'field') {
		if (page == 'details') {
			if (item.dataset_field_id) {
				fields.values_source._disabled = true;
				fields.values_source_filter._readonly = true;
				fields.min_rows._readonly = true;
				fields.max_rows._readonly = true;
			}
			
			var values_source = fields.values_source._value;
			if (values_source && thus.tuix.centralised_lists.values[values_source]) {
				var list = thus.tuix.centralised_lists.values[values_source];
				if (!list.info.can_filter) {
					fields.values_source_filter._hidden = true;
				} else {
					fields.values_source_filter.label = list.info.filter_label;
				}
			}
			
			if (item.visibility == 'visible_on_condition' && item.visible_condition_field_id) {
				var conditionField = thus.getField(item.visible_condition_field_id);
				if (conditionField) {
					
					if (conditionField.type == 'checkboxes') {
						fields.visible_condition_checkboxes_field_value.values = conditionField.lov;
						fields.visible_condition_field_value._hidden = true;
						fields.visible_condition_checkboxes_operator._hidden = false;
					} else if (conditionField.type == 'checkbox' || conditionField.type == 'group' || conditionField.type == 'consent') {
						//Nothing to do
					} else {
						fields.visible_condition_field_value.empty_value = '-- Any value --';
						fields.visible_condition_field_value.values = conditionField.lov;
						fields.visible_condition_field_type.values = _.clone(fields.visible_condition_field_type.values);
						fields.visible_condition_field_type.values.visible_if_one_of = {label: 'Visible if one of...'};
						
						if (item.visible_condition_field_type == 'visible_if_one_of') {
							fields.visible_condition_checkboxes_field_value.values = conditionField.lov;
							fields.visible_condition_field_value._hidden = true;
						}
					}
					
					
					if (conditionField.type != 'checkboxes' && item.visible_condition_field_type != 'visible_if_one_of') {
						fields.visible_condition_checkboxes_field_value._hidden = true;
					}
				}
			}
			if (item.readonly_or_mandatory == 'conditional_mandatory' && item.mandatory_condition_field_id) {
				var conditionField = thus.getField(item.mandatory_condition_field_id);
				if (conditionField.type == 'checkboxes') {
					fields.mandatory_condition_checkboxes_field_value.values = conditionField.lov;
					fields.mandatory_condition_field_value._hidden = true;
					fields.mandatory_condition_checkboxes_operator._hidden = false;
				} else {
					if (conditionField && conditionField.type != 'checkbox' && conditionField.type != 'group' && conditionField.type != 'consent') {
						fields.mandatory_condition_field_value.values = conditionField.lov;
						fields.mandatory_condition_field_value.empty_value = '-- Any value --';
					}
					fields.mandatory_condition_checkboxes_field_value._hidden = true;
				}
			}
			
			if (item.type == 'repeat_start') {
				fields.field_label.label = 'Section heading:';
				fields.field_label.note_below = 'This will be the heading at the start of the repeating section.';
			} else if (item.type == 'calculated') {
				if (item.calculation_code) {
					//Make sure this is an array not an object
					if (!Array.isArray(item.calculation_code)) {
						item.calculation_code = $.map(item.calculation_code, function(i) { return i });
					}
					fields.calculation_code._value = thus.getCalculationCodeDisplay(item.calculation_code);
				}
			} else if (item.type == 'page_break') {
				var pages = thus.getOrderedPages();
				for (var i = 0; i < pages.length; i++) {
					if (pages[i].id == item.id) {
						if (i == 0) {
							fields.previous_button_text._hidden = true;
						} else if (i == (pages.length - 1) && !thus.tuix.form_enable_summary_page) {
							fields.next_button_text._hidden = true;
							fields.visibility._disabled = true;
						}
						break;
					}
				}
			}
			
		} else if (page == 'advanced') {
			if (item.default_value_options == 'value') {
				if (['radios', 'centralised_radios', 'select', 'centralised_select'].indexOf(item.type) != -1) {
					fields.default_value_lov.values = item.lov;
				}
			}
			
			if (item.type == 'select') {
				fields.default_value_options.label = 'Pre-select field:';
			}
		}
	}
	
	//Re-draw page tabs for visibility class
	if (item.type == 'page_break') {
		thus.loadPagesList(thus.currentPageId);
	}
	
	return fields;
};

methods.getField = function(fieldId) {
	if (thus.tuix.fields[fieldId]) {
		return thus.tuix.fields[fieldId];
	}
	return false;
};

methods.getFieldValuesList = function(field) {
	var sortedValues = [];
	var fieldName = field.id;
	var values = field.values;
	var value = field._value;
	var disabled = field._disabled;
	var usesOptGroups = false;
	
	if (typeof(values) == 'object') {
		var i = 0;
		foreach (values as var tValueId => var tValue) {
			var option = {
				label: tValue.label,
				value: tValueId,
				ord: defined(tValue.ord) ? tValue.ord : ++i
			};
			sortedValues.push(option);
		}
	} else if (values == 'centralised_lists') {
		var i = 0;
		foreach (thus.tuix.centralised_lists.values as var func => var details) {
			var option = {
				ord: ++i,
				label: details.label,
				value: func
			};
			sortedValues.push(option);
		}
	} else if (values == 'centralised_list_filter_fields'
		|| values == 'conditional_fields'
		|| values == 'mirror_fields'
	) {
		var tPages = thus.getOrderedPages();
		usesOptGroups = tPages.length > 1;
		var ord = 1;
		for (var i = 0; i < tPages.length; i++) {
			var tFields = thus.getOrderedFields(tPages[i].id);
			var parentIndex = false;
			var tPage = tPages[i];
			for (var j = 0; j < tFields.length; j++) {
				var tField = tFields[j];
				if (thus.canAddFieldToList(values, tField)) {
					if (parentIndex === false && usesOptGroups) {
						parentIndex = sortedValues.push({
							ord: ord++,
							label: tPage.name,
							hasChildren: true,
							options: []
						}) - 1;
					}
					var option = {
						ord: ord++,
						label: tField.name,
						value: tField.id,
						parent: parentIndex
					};
					sortedValues.push(option);
				}
			}
		}
	} else if (values == 'field_lov') {
		if (thus.tuix.fields[thus.selectedFieldId] && thus.tuix.fields[thus.selectedFieldId].lov) {
			foreach (thus.tuix.fields[thus.selectedFieldId].lov as var tValueId => var tValue) {
				var option = {
					ord: tValue.ord,
					label: tValue.label,
					value: tValueId
				};
				sortedValues.push(option);
			}
		}
	}
	
	foreach (sortedValues as var index => var option) {
		if (field.type == 'checkboxes') {
			if (value) {
				if (value.indexOf(option.value) != -1) {
					option.selected = true;
				}
			}
		} else {
			if (option.value == value) {
				option.selected = true;
			}
		}
		if (disabled) {
			option.disabled = true;
		}
		option.name = fieldName;
		option.path = field.path;
		
		if (usesOptGroups && defined(option.parent)) {
			sortedValues[option.parent].options.push(option);
			delete(sortedValues[index]);
		}
	}
	
	sortedValues.sort(thus.sortByOrd);
	return sortedValues;	
};

methods.canAddFieldToList = function(values, field) {
	if (values == 'centralised_list_filter_fields') {
		return (field.id != thus.selectedFieldId) && (field.type == 'centralised_select' || (field.type == 'text' && field.autocomplete && field.values_source));
	} else if (values == 'conditional_fields') {
		return (field.id != thus.selectedFieldId) && (['checkbox', 'group', 'consent', 'radios', 'select', 'centralised_radios', 'centralised_select', 'checkboxes'].indexOf(field.type) != -1);
	} else if (values == 'mirror_fields') {
		return (field.id != thus.selectedFieldId) && (['text', 'calculated', 'select', 'centralised_select'].indexOf(field.type) != -1);
	}
	return false;
};


methods.loadNewFieldsPanel = function(stopEffect) {
	thus.selectedPageId = false;
	thus.selectedFieldId = false;
	
	//Load HTML
	var mergeFields = {
		mode: 'new_fields',
		datasetTabs: thus.getOrderedDatasetPagesAndFields(),
		link_to_dataset: thus.tuix.link_to_dataset
	};
	var html = thus.microTemplate('zenario_organizer_form_builder_left_panel', mergeFields);
	var $div = $('#organizer_form_builder .form_fields_palette .form_fields_palette_outer').html(html);
	
	if (!stopEffect) {
		$div.hide().show('drop', {direction: 'right'}, 200);
	}
	
	//Add events
		
	//Allow fields to be dragged onto list
	$('#organizer_field_type_list div.field_type, #organizer_centralised_field_type_list div.field_type, #organizer_linked_field_type_list div.dataset_field').draggable({
		connectToSortable: '#organizer_form_fields .form_section',
		appendTo: '#organizer_form_builder',
		helper: 'clone'
	});
	
	//Edit form settings
	$('input.form_settings').on('click', function() {
		thus.openFormSettings();
	});
};

methods.loadPagesList = function(pageId) {
	foreach (thus.tuix.items as var tPageId => var page) {
		if (pageId == tPageId) {
			page._selected = true;
		} else {
			delete(page._selected);
		}
	}
	
	//Only show page pages if there is at least 2
	var pages = thus.getOrderedPages();
	if (pages.length <= 1) {
		pages = false;
	}
	
	//Load HTML
	var mergeFields = {pages: pages};
	var html = thus.microTemplate('zenario_organizer_form_builder_tabs', mergeFields);
	$('#organizer_form_tabs').html(html);
	
	//Add events
		//Click on a page
	$('#organizer_form_tabs .tab').on('click', function() {
		var tPageId = $(this).data('id');
		if (thus.tuix.items[tPageId]) {
			thus.clickPage(tPageId);
		}
	});
	//Add a new page
	$('#organizer_add_new_tab').on('click', function() {
		var errors = thus.saveCurrentOpenDetails();
		if (!errors) {
			thus.addNewPage();
			thus.changeMadeToPanel();
		}
	});
	//Page sorting
	$('#organizer_form_tabs').sortable({ 
		containment: 'parent',
		tolerance: 'pointer',
		items: 'div.sort',
		start: function(event, ui) {
			thus.startIndex = ui.item.index();
		},
		stop: function(event, ui) {
			if (thus.startIndex != ui.item.index()) {
				//Update ordinals
				$('#organizer_form_tabs .tab.sort').each(function(i) {
					var tPageId = $(this).data('id');
					thus.tuix.items[tPageId].ord = (i + 1);
				});
				thus.pagesReordered = true;
				thus.changeMadeToPanel();
				
				//If currently viewing a pages details, reload because the content changes depending on position TODO
				if (thus.selectedPageId) {
					thus.saveCurrentOpenDetails();
					thus.loadPageDetailsPanel(thus.selectedPageId, true);
				}
			}
		}
	});
	
	//Moving fields between pages
	$('#organizer_form_tabs .tab').droppable({
		accept: 'div.is_sortable:not(.repeat_end)',
		greedy: true,
		hoverClass: 'ui-state-hover',
		tolerance: 'pointer',
		drop: function(event, ui) {
			var errors = thus.saveCurrentOpenDetails();
			if (!errors) {
				var tPageId = $(this).data('id');
				var fieldId = $(ui.draggable).data('id');
			
				thus.moveFieldToPage(thus.currentPageId, tPageId, fieldId);
			
				if (fieldId == thus.selectedFieldId) {
					thus.loadNewFieldsPanel();
				}
				thus.loadFieldsList(thus.currentPageId);
			}
		}
	});
};

methods.moveFieldToPage = function(fromPageId, toPageId, fieldId, addToStart) {
	if (fromPageId != toPageId 
		&& thus.tuix.items[toPageId] 
		&& thus.tuix.items[fromPageId] 
		&& thus.tuix.items[fromPageId].fields[fieldId]
		&& thus.tuix.fields[fieldId].type != 'repeat_end'
	) {
		thus.tuix.items[toPageId]._pageFieldsReordered = true;
		thus.tuix.items[fromPageId]._pageFieldsReordered = true;
		
		var ord = 1;
		var toFields = thus.getOrderedFields(toPageId);
		if (toFields.length) {
			if (addToStart) {
				ord = toFields[0].ord - 1;
			} else {
				ord = toFields[toFields.length - 1].ord + 1;
			}
		}
		
		//Move matching repeat_end for repeat_start
		if (thus.tuix.fields[fieldId].type == 'repeat_start') {
			var repeatEndId = thus.getMatchingRepeatEnd(fieldId);
			thus.tuix.fields[repeatEndId].ord = ord + 0.001;
			thus.tuix.items[toPageId].fields[repeatEndId] = 1;
			delete(thus.tuix.items[fromPageId].fields[repeatEndId]);
		}
		
		thus.tuix.fields[fieldId].ord = ord;
		thus.tuix.items[toPageId].fields[fieldId] = 1;
		delete(thus.tuix.items[fromPageId].fields[fieldId]);
		
		thus.changeMadeToPanel();
	}
};

methods.addNewPage = function(inBackground) {
	var ord = 1;
	var pages = thus.getOrderedPages();
	if (pages.length) {
		ord = pages[pages.length - 1].ord + 1;
	}
	thus.addNewField('page_break', ord, undefined, undefined, undefined, undefined, inBackground);
};

methods.clickPage = function(pageId, isNewPage) {
	if (thus.selectedPageId != pageId) {
		var detailErrors = thus.saveCurrentOpenDetails();
		var structureErrors = thus.displayPageFieldStructureErrors(thus.currentPageId);
		
		if (!detailErrors && !structureErrors) {
			//By clicking on the current page you can access it's details
			if (thus.currentPageId == pageId) {
				thus.selectedFieldId = false;
				thus.selectedPageId = pageId;
				thus.selectedDetailsPage = false;
				thus.loadPageDetailsPanel(pageId);
				return;
			}
			thus.clickPage2(pageId, isNewPage);
		}
	}
};

methods.clickPage2 = function(pageId, isNewPage) {
	thus.currentPageId = pageId;
	if (isNewPage) {
		thus.selectedPageId = pageId;
		thus.loadPageDetailsPanel(pageId);
	} else {
		var stopEffect = false;
		if (!thus.selectedFieldId && !thus.selectedPageId) {
			stopEffect = true;
		}
		thus.loadNewFieldsPanel(stopEffect);
	}
	thus.selectedFieldId = false;
	thus.loadPagesList(pageId);
	thus.loadFieldsList(pageId);
};


methods.getOrderedPages = function() {
	var orderedPages = [],
		pageId, page;
	foreach (thus.tuix.items as pageId => page) {
		orderedPages.push(page);
	}
	orderedPages.sort(thus.sortByOrd);
	return orderedPages;
};


methods.fieldDetailsChanged = function(path, field) {
	path = path.split('/');
	var mode = path[0],
		page = path[1],
		tuixField, value, valuesSource, actionRequests, thus, item, title;
	
	if (page && mode && thus.tuix[mode] && thus.tuix[mode].tabs[page] && thus.tuix[mode].tabs[page].fields[field]) {
		thus.changeMadeToPanel();
		tuixField = thus.tuix[mode].tabs[page].fields[field];
		if (tuixField.format_onchange) {
			if (mode == 'field_details') {
				//Save
				if (thus.selectedFieldId) {
					item = thus.tuix.fields[thus.selectedFieldId];
				} else {
					item = thus.tuix.items[thus.selectedPageId];
				}
				
				thus.saveItemTUIXPage(mode, thus.selectedDetailsPage, item);
				if (page == 'details' && field == 'field_validation') {
					if (item.field_validation == 'email') {
						item.field_validation_error_message = 'The email address you have entered is not valid, please enter a valid email address.';
					} else if (item.field_validation == 'URL') {
						item.field_validation_error_message = 'Please enter a valid URL.';
					} else if (item.field_validation == 'number') {
						item.field_validation_error_message = 'Please enter a valid number.';
					} else if (item.field_validation == 'integer') {
						item.field_validation_error_message = 'Please enter a valid integer.';
					} else if (item.field_validation == 'floating_point') {
						item.field_validation_error_message = 'Please enter a valid floating point number.';
					}
				}
				
				//Load
				thus.loadFieldDetailsPage(thus.selectedDetailsPage, item.id);
				
				if (page == 'details') {
					if (field == 'visibility') {
						value = $('#field__visibility').val();
						if (value == 'hidden') {
							title = 'Hidden';
						} else if (value == 'visible_on_condition') {
							title = 'Visible on condition';
						}
						$('#organizer_form_field_' + thus.selectedFieldId + ' .form_field_classes .not_visible').toggle(value != 'visible').prop('title', title);
					} else if (field == 'readonly_or_mandatory') {
						value = $('#field__readonly_or_mandatory').val();
						if (value == 'mandatory') {
							title = 'Mandatory';
						} else if (value == 'conditional_mandatory') {
							title = 'Mandatory on condition';
						}
						$('#organizer_form_field_' + thus.selectedFieldId + ' .form_field_classes .mandatory').toggle(value == 'mandatory' || value == 'conditional_mandatory' || value == 'mandatory_if_visible').prop('title', title);
					} else if (field == 'field_validation') {
						value = $('#field__field_validation').val();
						$('#organizer_form_field_' + thus.selectedFieldId + ' .form_field_classes .validation').toggle(value != 'none');
					} else if (field == 'values_source') {
						valuesSource = $('#field__values_source').val();
						if (valuesSource) {
							actionRequests = {
								mode: 'get_centralised_lov',
								method: valuesSource,
								filter: $('#field__values_source_filter').val()
							};
							thus.sendAJAXRequest(actionRequests).after(function(lov) {
								item.lov = lov;
								thus.loadFieldValuesListPreview(thus.selectedFieldId);
							});
						} else {
							item.lov = {};
							thus.loadFieldValuesListPreview(thus.selectedFieldId);
						}
					}
				}
			}
		}
	}
};

methods.loadFieldValuesListPreview = function(fieldId) {
	var pageId = thus.currentPageId;
	if (thus.tuix.fields[fieldId]) {
		var field = thus.tuix.fields[fieldId];
		var values = thus.getOrderedFieldValues(fieldId, true);
		var html = false;
		if (field.type == 'select' || field.type == 'centralised_select') {
			html = thus.microTemplate('zenario_organizer_admin_box_builder_select_values', values);
			$('#organizer_form_field_' + fieldId + ' select').html(html);
		} else if (field.type == 'radios' || field.type == 'centralised_radios') {
			html = thus.microTemplate('zenario_organizer_admin_box_builder_radio_values_preview', values);
			$('#organizer_form_field_values_' + fieldId).html(html);
		} else if (field.type == 'checkboxes') {
			html = thus.microTemplate('zenario_organizer_admin_box_builder_checkbox_values_preview', values);
			$('#organizer_form_field_values_' + fieldId).html(html);
		}
	}
};

methods.loadFieldsList = function(pageId) {
	var orderedFields = thus.getOrderedFields(pageId, true, true);
	
	//Load HTML
	var mergeFields = {
		fields: orderedFields
	};
	var html = thus.microTemplate('zenario_organizer_form_builder_section', mergeFields);
	$('#organizer_form_fields').html(html);
	
	if (thus.selectedFieldId) {
		thus.highlightField(thus.selectedFieldId);
	}
	
	//Add events
		//Select a field
	$('#organizer_form_fields div.form_field').on('click', function() {
		var fieldId = $(this).data('id');
		if (thus.tuix.fields[fieldId]) {
			thus.clickField(fieldId);
		}
	});
	
	var hoverTimeoutId;
	$('#organizer_form_fields div.form_field').hover(
		function() {
			var that2 = thus;
			if (!hoverTimeoutId) {
				hoverTimeoutId = setTimeout(function() {
					hoverTimeoutId = null;
					var fieldId = $(that2).data('id');
					$('#organizer_form_field_' + fieldId + ' .form_field_classes').show();
				}, 200)
			}
		}, function() {
			if (hoverTimeoutId) {
				clearTimeout(hoverTimeoutId);
				hoverTimeoutId = null;
			} else {
				var fieldId = $(this).data('id');
				if (fieldId != thus.selectedFieldId) {
					$('#organizer_form_field_' + fieldId + ' .form_field_classes').hide();
				}
			}
		}
	);
	$('#organizer_form_builder').tooltip();
	
	//Make fields sortable
	$('#organizer_form_fields .form_section').sortable({
		items: 'div.is_sortable',
		tolerance: 'pointer',
		placeholder: 'preview',
		//Add new field to the form
		receive: function(event, ui) {
			$(this).find('div.field_type, div.dataset_field').each(function() {
				var fieldType = $(this).data('type');
				var datasetFieldId = $(this).data('id');
				var datasetPageId = $(this).data('tab_name');
				var ord = 0.1;
				var previousFieldId = $(this).prev().data('id');
				if (previousFieldId && thus.tuix.fields[previousFieldId]) {
					ord = thus.tuix.fields[previousFieldId].ord + 0.1;
				} else {
					var nextFieldId = $(this).next().data('id');
					if (nextFieldId && thus.tuix.fields[nextFieldId]) {
						ord = thus.tuix.fields[nextFieldId].ord - 0.1;
					}
				}
				
				thus.addNewField(fieldType, ord, datasetFieldId, datasetPageId);
				thus.updateFieldOrds();
			});
		},
		start: function(event, ui) {
			thus.startIndex = ui.item.index();
		},
		//Detect reorder/new/deleted fields
		stop: function(event, ui) {
			if (thus.startIndex != ui.item.index()) {
				thus.tuix.items[pageId]._pageFieldsReordered = true;
				//Update ordinals
				thus.updateFieldOrds();
				//Redraw fields for indenting
				thus.loadFieldsList(pageId);
			}
		}
	});
	//Delete a field
	$('#organizer_form_fields .delete_icon').on('click', function(e) {
		e.stopPropagation();
		var fieldId = $(this).data('id');
		if (thus.tuix.fields[fieldId]) {
			//Make sure we can delete this field
			var errors = thus.saveCurrentOpenDetails(true);
			if (!errors) {
				var field = thus.tuix.fields[fieldId];
				var transferFields = {};
				
				//Data can be transfered to fields of the same type
				//Groups can have their data moved into checkbox and consent fields also
				foreach (thus.tuix.fields as var tFieldId => var tField) {
					if ((tField.type == field.type || (field.type == 'group' && (tField.type == 'checkbox' || tField.type == 'consent'))) && tFieldId != fieldId) {
						transferFields[tFieldId] = tField.name;
					}
				}
			
				var keys = {
					id: fieldId,
					field_name: field.name,
					field_type: field.type,
					field_english_type: thus.getFieldReadableType(field.type)
				};
				//Pass the fields this responses can be transfered to a dummy field via values because this may be too large for the key
				//which is passed in the URL
				var values = {details: {dummy_field: JSON.stringify(transferFields)}};
				zenarioAB.open(
					'zenario_delete_form_field',
					keys,
					undefined, values,
					function(key, values) {
						//Migrate data to another field
						var migrateResponsesTo = undefined;
						if (values.details.delete_field_options == 'delete_field_but_migrate_data' 
							&& values.details.migration_field
						) {
							if (thus.tuix.fields[values.details.migration_field]) {
								thus.tuix.fields[values.details.migration_field]._migrate_responses_from = fieldId;
							}
						}
						thus.deleteField(fieldId);
					}
				);
			}
		}
	});
	//Duplicate a field
	$('#organizer_form_fields .duplicate_icon').on('click', function(e) {
		e.stopPropagation();
		var fieldId = $(this).data('id');
		if (thus.tuix.fields[fieldId]) {
			var errors = thus.saveCurrentOpenDetails();
			if (!errors) {
				var field = thus.tuix.fields[fieldId];
				message = '<p>Are you sure you want to duplicate the field "' + field.name + '"?</p>';
				zenarioA.floatingBox(message, 'Duplicate', 'warning', true, false, function() {
					thus.addNewField(field.type, field.ord + 0.1, undefined, undefined, undefined, fieldId);
					thus.updateFieldOrds();
				});
			}
		}
	});
	
	//Update a dataset repeat field
	$('#organizer_form_fields .update_repeat_field_icon').on('click', function(e) {
		e.stopPropagation();
		var repeatFieldId = $(this).data('id');
		if (thus.tuix.fields[repeatFieldId] && thus.tuix.fields[repeatFieldId].dataset_field_id) {
			var errors = thus.saveCurrentOpenDetails();
			if (!errors) {
				message = '<p>Are you sure you want to update this dataset repeat field?</p>';
				zenarioA.floatingBox(message, 'Update', 'warning', true, false, function() {
					
					var datasetRepeatFieldId = thus.tuix.fields[repeatFieldId].dataset_field_id;
					var datasetRepeatField = false;
					//Find dataset repeat start field
					for (var tab in thus.tuix.dataset_fields) {
						if (thus.tuix.dataset_fields[tab].fields && thus.tuix.dataset_fields[tab].fields[datasetRepeatFieldId]) {
							var datasetRepeatField = thus.tuix.dataset_fields[tab].fields[datasetRepeatFieldId];
							break;
						}
					}
					//Add / remove / reorder fields
					if (datasetRepeatField) {
						var formRepeatFields = [];
						var datasetRepeatFields = [];
						//Get form fields inside dataset repeat
						foreach (thus.tuix.fields as var fieldId => var field) {
							if (field.dataset_repeat_grouping == datasetRepeatField.id && field.type != 'repeat_end' && field.type != 'repeat_start') {
								formRepeatFields.push(fieldId);
							}
						}
						//Get dataset fields inside dataset repeat
						foreach (datasetRepeatField.fields as var datasetFieldId => var datasetField) {
							if (datasetField.type != 'repeat_end') {
								datasetRepeatFields.push(datasetField);
							}
						}
						datasetRepeatFields.sort(thus.sortByOrd);
				
						//Add new fields and update ordinals
						var ord = thus.tuix.fields[repeatFieldId].ord;
						for (var i = 0; i < datasetRepeatFields.length; i++) {
					
							var found = false;
							for (var j = 0; j < formRepeatFields.length; j++) {
								if (datasetRepeatFields[i].id == thus.tuix.fields[formRepeatFields[j]].dataset_field_id) {
									thus.tuix.fields[formRepeatFields[j]].ord = ord + (datasetRepeatFields[i].ord / 1000);
									found = true;
									break;
								}
							}
							if (!found) {
								datasetRepeatFields[i].dataset_repeat_grouping = datasetRepeatField.id;
								thus.addNewField(datasetRepeatFields[i].type, ord + (datasetRepeatFields[i].ord / 1000), false, false, datasetRepeatFields[i]);
							}
						}
				
						//Remove fields this no longer exist
						for (var i = 0; i < formRepeatFields.length; i++) {
							var found = false;
							for (var j = 0; j < datasetRepeatFields.length; j++) {
								if (thus.tuix.fields[formRepeatFields[i]].dataset_field_id == datasetRepeatFields[j].id) {
									found = true;
								}
							}
							if (!found) {
								thus.deleteField(formRepeatFields[i]);
							}
						}
						
						//Update display
						thus.tuix.items[thus.currentPageId]._pageFieldsReordered = true;
						thus.loadFieldsList(thus.currentPageId);
						thus.changeMadeToPanel();
					}
				});
			}
		}
	});
};

methods.updateFieldOrds = function() {	
	$('#organizer_form_fields div.form_field').each(function(i) {
		var fieldId = $(this).data('id');
		if (fieldId) {
			thus.tuix.fields[fieldId].ord = (i + 1);
		}
	});
	thus.changeMadeToPanel();
};

methods.deletePage = function(pageId) {
	var pages = thus.getOrderedPages(),
		fields, i, j, nextPageId;
	//Do not allow the final page to be deleted
	if (pages.length >= 2) {
		fields = thus.getOrderedFields(pageId);
		for (i = 0; i < pages.length; i++) {
			if (pages[i].id == pageId) {
				if (pages[i - 1]) {
					nextPageId = pages[i - 1].id;
					for (j = 0; j < fields.length; j++) {
						thus.moveFieldToPage(pageId, nextPageId, fields[j].id);
					}
				} else {
					nextPageId = pages[i + 1].id;
					for (j = fields.length - 1; j >= 0; j--) {
						thus.moveFieldToPage(pageId, nextPageId, fields[j].id, true);
					}
				}
				thus.deletedPages.push(pageId);
				delete(thus.tuix.items[pageId]);
				
				thus.clickPage(nextPageId);
				thus.changeMadeToPanel();
				break;
			}
		}
	}
};

methods.deleteField = function(fieldId) {
	pageId = thus.currentPageId;
	if (thus.tuix.fields[fieldId]) {
		thus.changeMadeToPanel();
		thus.deletedFields.push(fieldId);
		
		var field = thus.tuix.fields[fieldId];
		//Delete a repeat start's matching repeat end
		if (field.type == 'repeat_start') {
			var repeatEndId = thus.getMatchingRepeatEnd(fieldId);
			if (repeatEndId) {
				thus.deletedFields.push(repeatEndId);
				delete(thus.tuix.items[pageId].fields[repeatEndId]);
				delete(thus.tuix.fields[repeatEndId]);
			}
		}
		
		//When deleting a dataset repeat, remove all fields inside as well.
		if (field.dataset_repeat_grouping) {
			var fields = thus.getOrderedFields(pageId);
			for (var i = 0; i < fields.length; i++) {
				if (fields[i].dataset_repeat_grouping && fields[i].dataset_repeat_grouping == field.dataset_repeat_grouping && fields[i].type != 'repeat_start') {
					thus.deletedFields.push(fields[i].id);
					delete(thus.tuix.items[pageId].fields[fields[i].id]);
					delete(thus.tuix.fields[fields[i].id]);
				}
			}
		}
		
		//Select next field if one exists
		var fields = thus.getOrderedFields(pageId);
		var deletedFieldIndex = false;
		var nextFieldId = false;
		
		for (var i = 0; i < fields.length; i++) {
			if (fields[i].id == field.id) {
				deletedFieldIndex = i;
			}
			
			if (deletedFieldIndex !== false) {
				if (deletedFieldIndex > 0) {
					nextFieldId = fields[deletedFieldIndex - 1].id;
					break;
				} else if (fieldId != fields[i].id) {
					nextFieldId = fields[i].id;
					break;
				}
			}
		}
		
		if (nextFieldId && thus.tuix.fields[nextFieldId].type == 'repeat_end') {
			nextFieldId = false;
		}
		
		delete(thus.tuix.items[pageId].fields[fieldId]);
		delete(thus.tuix.fields[fieldId]);
		
		thus.loadFieldsList(pageId);
		if (nextFieldId) {
			thus.clickField(nextFieldId);
		} else {
			thus.loadNewFieldsPanel();
		}
	}
};

methods.addNewField = function(type, ord, datasetFieldId, datasetPageId, datasetField, copyFromFieldId, inBackground) {
	var newFieldId = 't' + thus.maxNewCustomField++;
	var newField = false;
	if (datasetField
		|| (datasetFieldId 
			&& thus.tuix.dataset_fields[datasetPageId] 
			&& thus.tuix.dataset_fields[datasetPageId].fields[datasetFieldId]
		)
	) {
		if (!datasetField) {
			datasetField = thus.tuix.dataset_fields[datasetPageId].fields[datasetFieldId];
		}
		newField = {
			type: datasetField.type,
			name: datasetField.label,
			field_label: datasetField.label,
			dataset_field_id: datasetField.id,
			values_source: datasetField.values_source,
			values_source_filter: datasetField.values_source_filter,
			db_column: datasetField.db_column
		};
		
		if (datasetField.db_column == 'terms_and_conditions_accepted') {
			newField.field_label = 'By submitting your details you are agreeing that we can store your data for legitimate business purposes and contact you to inform you about our products and services.';
			newField.note_to_user = 'Full details can be found in our <a href="privacy" target="_blank">privacy policy</a>.';
		}
		
		if (datasetField.dataset_repeat_grouping) {
			newField.dataset_repeat_grouping = datasetField.dataset_repeat_grouping;
		}
		
		if (datasetField.lov) {
			newField.lov = datasetField.lov;
		}
		if (datasetField.type == 'repeat_start') {
			newField.dataset_repeat_grouping = datasetField.id;
			var repeatDatasetFields = datasetField.fields;
			var orderedFields = [];
			foreach (datasetField.fields as var tFieldId => var tField) {
				orderedFields.push(tField);
			}
			orderedFields.sort(thus.sortByOrd);
			
			for (var i = 0; i < orderedFields.length; i++) {
				var tField = orderedFields[i];
				tField.dataset_repeat_grouping = datasetField.id;
				thus.addNewField(tField.type, ord + (0.001 * (i + 1)), undefined, undefined, tField);
			}
		}
		
	} else if (type) {
		newField = {
			type: type,
			field_label: 'Untitled',
			name: 'Untitled ' + thus.getFieldReadableType(type).toLowerCase()
		};
		
		if (type == 'checkboxes' || type == 'radios' || type == 'select') {
			newField.lov = {};
			for (var i = 1; i <= 3; i++) {
				thus.addFieldValue(newField, 'Option ' + i);
			}
		} else if (type == 'repeat_start') {
			thus.addNewField('repeat_end', ord + 0.001);
		}
	}
	
	//Load default values for fields
	foreach (thus.tuix.field_details.tabs as var pageName => page) {
		if (page.fields) {
			foreach (page.fields as var fieldName => var field) {
				if (defined(field.value) && !defined(newField[fieldName])) {
					newField[fieldName] = field.value;
				}	
			}
		}
	}
	
	if (newField) {
		if (copyFromFieldId) {
			newField = JSON.parse(JSON.stringify(thus.tuix.fields[copyFromFieldId]));
			if (newField.type == 'checkboxes' || newField.type == 'select' || newField.type == 'radios') {
				var lov = newField.lov;
				delete(newField.lov);
				
				newField.lov = {};
				for (var valueId in lov) {
					thus.addFieldValue(newField, lov[valueId].label, lov[valueId].ord);
				}
			}
		}
		
		newField.ord = ord;
		newField._is_new = true;
		newField.just_added = true;
		newField.id = newFieldId;
		if (type == 'page_break') {
			var pages = thus.getOrderedPages();
			newField.name = 'Page ' + (pages.length + 1);
			newField.fields = {};
			thus.tuix.items[newFieldId] = newField;
			if (!inBackground) {
				thus.clickPage(newFieldId, true);
			}
		} else {
			thus.tuix.items[thus.currentPageId].fields[newFieldId] = 1;
			thus.tuix.fields[newFieldId] = newField;
			//Don't bother redrawing if we're adding repeat dataset fields, once is enough.
			if (datasetField && !datasetFieldId) {
				return;
			}
			thus.loadFieldsList(thus.currentPageId);
			if (!inBackground) {
				thus.clickField(newFieldId, newField.just_added);
			}
		}
	}
};

methods.clickField = function(fieldId, justAdded) {
	if (thus.selectedFieldId != fieldId 
		&& thus.tuix.fields[fieldId]
		&& thus.tuix.fields[fieldId].type != 'repeat_end'
	) {
		var errors = thus.saveCurrentOpenDetails();
		if (!errors) {
			thus.highlightField(fieldId);
			thus.selectedFieldId = fieldId;
			thus.selectedPageId = false;
			thus.selectedDetailsPage = false;
			thus.loadFieldDetailsPanel(fieldId, undefined, undefined, justAdded);
		}
	}
};

methods.saveCurrentOpenDetails = function(deletingField) {
	var errors = false,
		item;
	if (thus.selectedFieldId && thus.tuix.fields[thus.selectedFieldId]) {
		//Save custom field
		var $name = $('#field__name');
		if ($name.length) {
			thus.tuix.fields[thus.selectedFieldId].name = $name.val();
		}
		
		if (deletingField) {
			errors = thus.getDeleteFieldErrors(thus.selectedFieldId);
		} else {
			item = thus.tuix.fields[thus.selectedFieldId];
			errors = thus.saveItemTUIXPage('field_details', thus.selectedDetailsPage, item);
		}
		
		if (!_.isEmpty(errors)) {
			thus.loadFieldDetailsPage(thus.selectedDetailsPage, thus.selectedFieldId, errors);
		}
	} else if (thus.selectedPageId && thus.tuix.items[thus.selectedPageId]) {
		//Save custom field
		var $name = $('#field__name');
		if ($name.length) {
			thus.tuix.items[thus.selectedPageId].name = $name.val();
		}
		
		item = thus.tuix.items[thus.selectedPageId];
		errors = thus.saveItemTUIXPage('field_details', thus.selectedDetailsPage, item);
		if (!_.isEmpty(errors)) {
			thus.loadPageDetailsPage(thus.selectedDetailsPage, thus.selectedPageId, errors);
		}
	}
	return !_.isEmpty(errors);
};

methods.displayPageFieldStructureErrors = function(pageId) {
	var errors = [],
		fields = thus.getOrderedFields(pageId),
		inRepeatBlock = false, 
		repeatBlockDepth = 0,
		i, field;
	
	var fieldsAllowedInRepeatBlock = [
		//Valid form field types
		'checkbox', 
		'checkboxes', 
		'date', 
		'radios', 
		'centralised_radios', 
		'select', 
		'centralised_select', 
		'text', 
		'textarea', 
		'url', 
		'attachment', 
		'section_description', 
		'calculated',
		//Valid dataset field types not included above
		'group'
	];
	
	for (i = 0; i < fields.length; i++) {
		field = fields[i];
		
		if (field.type == 'repeat_start' && !inRepeatBlock) {
			inRepeatBlock = true;
		} else if (field.type == 'repeat_end') {
			if (inRepeatBlock) {
				inRepeatBlock = false;
			} else {
				errors.push('Repeat ends must be placed after repeat starts.');
			}
		} else {
			//Validate field types in repeat block
			if (inRepeatBlock && fieldsAllowedInRepeatBlock.indexOf(field.type) == -1) {
				errors.push('Field type "' + thus.getFieldReadableType(field.type).toLowerCase() + '" is not allowed in a repeat block.');
			}
		}
	}
	
	//Display errors
	var foundErrors = errors.length > 0;
	var $errorDiv = $('#organizer_fields_error');
	$errorDiv.html('');
	for (i = 0; i < errors.length; i++) {
		$errorDiv.append('<p class="error">' + errors[i] + '</p>');
	}
	$errorDiv.toggle(foundErrors);
	
	return foundErrors;
};



methods.getDeleteFieldErrors = function(fieldId) {
	var errors = {},
		tFieldId, tField;
	foreach (thus.tuix.fields as tFieldId => var tField) {
		if (fieldId != tFieldId) {
			var usedInCalcField = false;
			if (tField.type == 'calculated' && tField.calculation_code) {
				foreach (tField.calculation_code as var index => var step) {
					if (step.type == 'field' && step.value == fieldId) {
						usedInCalcField = true;
						break;
					}
				}
			}
			
			if ((tField.visibility && tField.visibility == 'visible_on_condition' && tField.visible_condition_field_id == fieldId)
				|| (tField.readonly_or_mandatory && tField.readonly_or_mandatory == 'conditional_mandatory' && tField.mandatory_condition_field_id == fieldId)
				|| usedInCalcField
			) {
				errors[tFieldId] = 'Unable to delete field because the field "' + tField.name + '" depends on it.';
			}
		}
	}
	return errors;
};


methods.validateFieldDetails = function(fields, item, mode, page, errors) {
	var conditionField, tPageId, tPage, fieldId, field, i;
	if (mode == 'field_details') {
		if (!item.name) {
			errors.name = 'Please enter a name for this form field.';
		}
		
		if (page == 'details') {
			if (item.type == 'checkbox' || item.type == 'group' || item.type == 'consent') {
				if (!item.field_label) {
					errors.field_label = 'Please enter a label for this checkbox.';
				}
			} else if (item.type == 'repeat_start') {
				if (!item.min_rows) {
					errors.min_rows = 'Please enter the minimum rows.';
				} else if (+item.min_rows != item.min_rows) {
					errors.min_rows = 'Please a valid number for mininum rows.';
				} else if (item.min_rows < 1 || item.min_rows > 10) {
					errors.min_rows = 'Mininum rows must be between 1 and 10.';
				}
				
				if (!item.max_rows) {
					errors.max_rows = 'Please enter the maximum rows.';
				} else if (+item.max_rows != item.max_rows) {
					errors.max_rows = 'Please a valid number for maximum rows.';
				} else if (item.max_rows < 1 || item.max_rows > 20) {
					errors.max_rows = 'Maximum rows must be between 1 and 20.';
				}
				
				if (!errors.min_rows && !errors.max_rows && (+item.min_rows > +item.max_rows)) {
					errors.min_rows = 'Minimum rows cannot be greater than maximum rows.';
				}
			} else if (item.type == 'restatement') {
				if (!item.restatement_field) {
					errors.restatement_field = 'Please select the field to mirror.';
				}
			} else if (item.type == 'centralised_radios' || item.type == 'centralised_select') {
				if (!item.values_source) {
					errors.values_source = 'Please select a values source.';
				}
			}
			
			if (item.visibility && item.visibility == 'visible_on_condition') {
				if (!item.visible_condition_field_id) {
					errors.visible_condition_field_id = 'Please select a visible on conditional form field.';
				} else if (!item.visible_condition_field_value) {
					conditionField = thus.getField(item.visible_condition_field_id);
					if (conditionField && ['checkbox', 'group', 'consent'].indexOf(conditionField.type) != -1) {
						errors.visible_condition_field_value = 'Please select a visible on condition form field value.';
					}
				}
			}
			
			if (item.readonly_or_mandatory) {
				if ((item.readonly_or_mandatory == 'mandatory' || item.readonly_or_mandatory == 'conditional_mandatory' || item.readonly_or_mandatory == 'mandatory_if_visible') && !item.required_error_message) {
					errors.required_error_message = 'Please enter an error message when this field is incomplete.';
				}
				
				if (item.readonly_or_mandatory == 'mandatory') {
					if (item.visibility == 'hidden') {
						errors.readonly_or_mandatory = 'A field cannot be mandatory while hidden.';
					} else if (item.visibility == 'visible_on_condition') {
						errors.readonly_or_mandatory = "This field is always mandatory but is conditionally visible. You must change this field to be either visible all the time or conditionally mandatory with the same condition as it's visibility.";
					}
				} else if (item.readonly_or_mandatory == 'conditional_mandatory') {
					if (!item.mandatory_condition_field_id) {
						errors.mandatory_condition_field_id = 'Please select a mandatory on condition form field.';
					} else if (!item.mandatory_condition_field_value) {
						conditionField = thus.getField(item.mandatory_condition_field_id);
						if (conditionField && ['checkbox', 'group', 'consent'].indexOf(conditionField.type) != -1) {
							errors.mandatory_condition_field_value = 'Please select a mandatory on condition form field value.';
						}
					}
					
					if (item.visibility == 'hidden') {
						errors.visibility = 'A field cannot be mandatory while hidden.';
					} else if (item.visibility == 'visible_on_condition' && item.readonly_or_mandatory == 'conditional_mandatory'
						&& item.visible_condition_field_id && item.mandatory_condition_field_id
						&& ((item.visible_condition_field_id != item.mandatory_condition_field_id)
							|| item.visible_condition_field_type == 'visible_if' && item.mandatory_condition_field_type == 'mandatory_if_not'
							|| item.visible_condition_field_type == 'visible_if_not' && item.mandatory_condition_field_type == 'mandatory_if'
							|| ((conditionField = thus.getField(item.mandatory_condition_field_id))
								&& (conditionField.type != 'checkboxes'
									&& (item.visible_condition_field_value != item.mandatory_condition_field_value)
								)
								|| (conditionField.type == 'checkboxes'
									&& (item.visible_condition_checkboxes_operator != item.mandatory_condition_checkboxes_operator
										|| !_.isEqual(item.visible_condition_checkboxes_field_value, item.mandatory_condition_checkboxes_field_value)
									)
								)
							)
						)
					) {
						errors.visibility = 'A field cannot be mandatory while hidden. If this field is both mandatory and visible on a condition, both fields and values must be the same.';
					}
				}
			}
			
			if (item.field_validation && item.field_validation != 'none') {
				if (!item.field_validation_error_message) {
					errors.field_validation_error_message = 'Please enter a validation error message for this field.';
				}
			}
			
			if (item.type == 'text' && item.field_validation && ['number', 'integer', 'floating_point'].indexOf(item.field_validation) == -1) {
				foreach (thus.tuix.fields as fieldId => field) {
					if (field.type == 'calculated' && field.calculation_code) {
						for (i = 0; i < field.calculation_code.length; i++) {
							if (field.calculation_code[i].type == 'field' && field.calculation_code[i].value == item.id) {
								errors.field_validation = 'The field "' + field.name + '" requires this field to be numeric. You must first remove this from this field.';
								break;
							}
						}
					}
				}
			}
			
		} else if (page == 'advanced') {
			if (item.default_value_options) {
				if (item.default_value_options == 'value') {
					if (!item.default_value_lov && !item.default_value_text) {
						errors.default_value = 'Please enter a default value.';
					}
				} else if (item.default_value_options == 'method') {
					if (!item.default_value_class_name) {
						errors.default_value_class_name = 'Please enter a class name.';
					}
					if (!item.default_value_method_name) {
						errors.default_value_method_name = 'Please enter the name of a static method.';
					}
				}
			}
			
			if (item.custom_code_name) {
				foreach (thus.tuix.fields as fieldId => field) {
					if (fieldId != item.id && (item.custom_code_name == field.custom_code_name)) {
						errors.custom_code_name = 'Another field already has this code name on this form.';
					}
				}
			}
			
			if (item.autocomplete) {
				if (item.autocomplete_options == 'method') {
					if (!item.autocomplete_class_name) {
						errors.autocomplete_class_name = 'Please enter a class name.';
					}
					if (!item.autocomplete_method_name) {
						errors.autocomplete_method_name = 'Please enter the name of a static method.';
					}
				} else if (item.autocomplete_options == 'centralised_list') {
					if (!item.values_source) {
						errors.values_source = 'Please select a value source for the autocomplete lists.';
					}
				}
			}
			
			if (item.invalid_responses && item.invalid_responses.length) {
				if (!item.invalid_field_value_error_message) {
					errors.invalid_field_value_error_message = 'Please enter an error message when an invalid response is chosen.';
				}
			}
			
			if (item.type == 'textarea') {
				if (item.word_count_max) {
					if (+item.word_count_max != item.word_count_max) {
						errors.word_count_max = 'Please a valid number for the max word count.';
					} else if (item.word_count_max < 1) {
						errors.word_count_max = 'Word count must be greater than 0.';
					}
				}
				if (item.word_count_min) {
					if (+item.word_count_min != item.word_count_min) {
						errors.word_count_min = 'Please a valid number for the min word count.';
					} else if (item.word_count_min < 1) {
						errors.word_count_min = 'Word count must be greater than 0.';
					}
				}
			}
			
		} else if (page == 'crm') {
			if (item.send_to_crm && !item.field_crm_name) {
				errors.field_crm_name = 'Please enter a CRM field name.';
			}
		}
	}
};

methods.highlightField = function(fieldId) {
	$('#organizer_form_fields .form_field .form_field_classes').hide();
	$('#organizer_form_fields .form_field .form_field_inline_buttons').hide();
	$('#organizer_form_fields .form_field').removeClass('selected');
	if (fieldId) {
		$('#organizer_form_field_' + fieldId).addClass('selected');
		$('#organizer_form_field_' + fieldId + ' .form_field_classes').show();
		$('#organizer_form_field_' + fieldId + ' .form_field_inline_buttons').show();
	}
};


methods.getFieldReadableType = function(type) {
	switch (type) {
		case 'checkbox':
			return 'Checkbox';
		case 'checkboxes':
			return 'Checkboxes';
		case 'date':
			return 'Date';
		case 'editor':
			return 'Editor';
		case 'radios':
			return 'Radios';
		case 'centralised_radios':
			return 'Centralised radios';
		case 'select':
			return 'Select';
		case 'centralised_select':
			return 'Centralised select';
		case 'text':
			return 'Text';
		case 'textarea':
			return 'Textarea';
		case 'url':
			return 'URL';
		case 'attachment':
			return 'Attachment';
		case 'page_break':
			return 'Page';
		case 'section_description':
			return 'Subheading';
		case 'calculated':
			return 'Calculated';
		case 'restatement':
			return 'Mirror';
		case 'repeat_start':
			return 'Start of repeating section';
		case 'repeat_end':
			return 'End of repeating section';
		case 'document_upload':
			return 'Multi-upload';
		default:
			return 'Unknown';
		
		//Types unique to dataset fields
		case 'group':
			return 'Group';
		case 'consent':
			return 'Consent';
		case 'file_picker':
			return 'File picker';
	}
};

methods.getOrderedFields = function(pageId, orderValues, groupGroupedFields) {
	var sortedFields = [],
		groupedFields = [],
		groups = {},
		fieldId, field, 
		inRepeat = false;
	if (thus.tuix.items[pageId] && thus.tuix.items[pageId].fields) {
		
		for (fieldId in thus.tuix.items[pageId].fields) {
			field = thus.tuix.fields[fieldId];
			var fieldClone = _.clone(field);
			
			if (orderValues) {
				fieldClone.lov = thus.getOrderedFieldValues(fieldId, true);
			}
			
			if (fieldClone.dataset_field_id) {
				fieldClone._hideDuplicateButton = true;
			}
			
			if (groupGroupedFields && fieldClone.dataset_repeat_grouping) {
				if (!groups[fieldClone.dataset_repeat_grouping]) {
					groups[fieldClone.dataset_repeat_grouping] = {};
					groups[fieldClone.dataset_repeat_grouping]._fields = [];
				}
				if (fieldClone.type != 'repeat_start') {
					fieldClone._hideDragButton = true;
					fieldClone._hideDeleteButton = true;
					fieldClone._hideDuplicateButton = true;
				}
				groupedFields.push(fieldClone);
			} else {
				fieldClone._isSortable = true;
				sortedFields.push(fieldClone);
			}
		}
	}
	
	if (groupedFields.length > 0) {
		for (i = 0; i < groupedFields.length; i++) {
			groups[groupedFields[i].dataset_repeat_grouping]._fields.push(groupedFields[i]);
		}
	}
	
	foreach (groups as groupName => group) {
		group._fields.sort(thus.sortByOrd);
		if (group._fields.length) {
			group.ord = group._fields[0].ord;
			group._isSortable = true;
			sortedFields.push(group);
		}
	}
	
	sortedFields.sort(thus.sortByOrd);
	
	//Remember which fields have a repeat above them for indenting
	for (var i = 0; i < sortedFields.length; i++) {
		if (sortedFields[i].type == 'repeat_start') {
			inRepeat = true;
		} else if (sortedFields[i].type == 'repeat_end') {
			inRepeat = false;
		} else if (inRepeat) {
			sortedFields[i]._inRepeat = true;
		}
	}
	
	return sortedFields;
};

methods.getOrderedFieldValues = function(fieldId, includeEmptyValue) {
	var sortedValues = [],
		valueId, value;
	if (thus.tuix.fields[fieldId] && thus.tuix.fields[fieldId].lov) {
		
		foreach (thus.tuix.fields[fieldId].lov as valueId => value) {
			sortedValues.push(value);
		}
		var type = thus.tuix.fields[fieldId].type;
		if (includeEmptyValue && (type == 'select' || type == 'centralised_select')) {
			sortedValues.push({
				id: 'empty_value',
				label: '-- Select --',
				ord: -1
			});
		}
	}
	sortedValues.sort(thus.sortByOrd);
	return sortedValues;
};

methods.sortErrors = function(errors) {
	var sortedErrors = [];
	foreach (errors as var i => var error) {
		sortedErrors.push(error);
	}
	return sortedErrors;
};

methods.sortLanguages = function(languages) {
	var sortedLanguages = [];
	foreach (languages as var i => var language) {
		sortedLanguages.push(language);
	}
	return sortedLanguages;
};

methods.getOrderedDatasetPagesAndFields = function() {
	var sortedPages = [];
	var usedDatasetFields = {};
	foreach (thus.tuix.fields as var fieldId => var field) {
		if (field.dataset_field_id) {
			usedDatasetFields[field.dataset_field_id] = true;
		}
	}
	
	foreach (thus.tuix.dataset_fields as var datasetPageName => var datasetPage) {
		var datasetPageClone = _.clone(datasetPage);
		datasetPageClone.fields = [];
		
		if (datasetPage.fields) {
			foreach (datasetPage.fields as var datasetFieldId => var datasetField) {
				datasetField.readableType = thus.getFieldReadableType(datasetField.type);
				datasetField.tab_label = datasetPage.label ? datasetPage.label : datasetPage.default_label;
				if (!usedDatasetFields[datasetFieldId]) {
					datasetPageClone.fields.push(datasetField);
				}
			}
			datasetPageClone.fields.sort(thus.sortByOrd);
		}
		
		sortedPages.push(datasetPageClone);
	}
	sortedPages.sort(thus.sortByOrd);
	return sortedPages;
};

methods.changeMadeToPanel = function() {	
	if (!thus.changeDetected) {
		thus.changeDetected = true;
		window.onbeforeunload = function() {
			return 'You are currently editing this form. If you leave now you will lose any unsaved changes.';
		}
		var warningMessage = 'Please either save your changes, or click Reset to discard them, before exiting the form editor.';
		zenarioO.disableInteraction(warningMessage);
		zenarioO.setButtons();
	}
};

methods.saveChanges = function() {
	//Show warning
	if (thus.tuix.not_used_or_on_public_page) {
		var hasNonEmailDatasetFields = false;
		var hasEmailDatasetField = false;
		foreach (thus.tuix.fields as var fieldId => var field) {
			if (field.dataset_field_id) {
				if (field.db_column == 'email') {
					hasEmailDatasetField = true;
				} else {
					hasNonEmailDatasetFields = true;
				}
			}
		}
		if (hasNonEmailDatasetFields && !hasEmailDatasetField && !confirm("Warning: this form contains fields that are linked to the Users Dataset, but doesn\'t contain the Email field from the Users Dataset.\n\nIf this form is used on a public web page by anonymous visitors, form responses will not be stored in the Users & Contacts database table.\n\nTo overcome this, please add the Email field linked to the Users Dataset.\n\nSave anyway?")) {
			return;
		}
	}
	
	
	var actionRequests = {
		mode: 'save',
		pages: JSON.stringify(thus.tuix.items),
		fields: JSON.stringify(thus.tuix.fields),
		pagesReordered: thus.pagesReordered,
		deletedPages: JSON.stringify(thus.deletedPages),
		deletedFields: JSON.stringify(thus.deletedFields),
		deletedValues: JSON.stringify(thus.deletedValues),
		currentPageId: thus.currentPageId
	};
	if (thus.selectedPageId) {
		actionRequests.selectedPageId = thus.selectedPageId;
	}
	if (thus.selectedFieldId) {
		actionRequests.selectedFieldId = thus.selectedFieldId;
	}
	
	zenarioA.nowDoingSomething('saving', true);
	thus.sendAJAXRequest(actionRequests).after(function(info) {
		zenarioA.nowDoingSomething();
		
		if (info) {
			if (info.errors) {
				var message = '';
				for (var i = 0; i < info.errors.length; i++) {
					message += info.errors[i] + '<br>';
				}
				if (message) {
					zenarioA.floatingBox(message);
				}
			}
			thus.currentPageId = info.currentPageId;
			thus.selectedPageId = info.selectedPageId
			thus.selectedFieldId = info.selectedFieldId;
		}
		
		window.onbeforeunload = false;
		zenarioO.enableInteraction();
		
		thus.changeDetected = false;
		thus.changesSaved = true;
		
		zenarioO.reload();
	});
};


}, zenarioO.panelTypes);
