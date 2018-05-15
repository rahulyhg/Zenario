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


class zenario_common_features__admin_boxes__document_properties extends ze\moduleBaseClass {
	
	public function fillAdminBox($path, $settingGroup, &$box, &$fields, &$values) {
		if ($documentId = $box['key']['id']) {
			$documentTagsString = '';
			
			$documentDetails = ze\row::get('documents',['file_id', 'thumbnail_id', 'extract', 'extract_wordcount', 'title'],  $documentId);
			$documentName = ze\row::get('documents', 'filename', ['type' => 'file','id' => $documentId]);
			$box['title'] = ze\admin::phrase('Editing metadata for document "[[filename]]"', ["filename"=>$documentName]);
			
			$fields['details/document_title']['value'] = $documentDetails['title'];
			$extension = pathinfo($documentName, PATHINFO_EXTENSION);
			$fields['details/document_extension']['value'] = $extension;
			$fields['details/document_name']['value'] = pathinfo($documentName, PATHINFO_FILENAME);
			$fields['details/document_name']['post_field_html'] = '&nbsp;.' . $extension;
			$fileDatetime=ze\row::get('documents', 'file_datetime', ['type' => 'file','id' => $documentId]);
			$fields['details/date_uploaded']['value'] = date('jS F Y H:i', strtotime($fileDatetime));
			
			if (ze::setting('enable_document_tags')) {
				$documentTags = ze\row::getArray('document_tag_link', 'tag_id', ['document_id' => $documentId]);
				foreach ($documentTags as $tag) {
					$documentTagsString .= $tag . ",";
				}
				$fields['details/tags']['value'] = $documentTagsString;
				$fields['details/link_to_add_tags']['snippet']['html'] = 
						ze\admin::phrase('To add or edit document tags click <a[[link]]>this link</a>.',
							['link' => ' href="'. htmlspecialchars(ze\link::absolute(). 'zenario/admin/organizer.php#zenario__content/panels/document_tags'). '" target="_blank"']);
			} else {
				$fields['details/tags']['hidden'] = true;
			}
			
			$fields['extract/extract_wordcount']['value'] = $documentDetails['extract_wordcount'];
			$fields['extract/extract']['value'] = ($documentDetails['extract'] ? $documentDetails['extract']: 'No plain-text extract');
			
			// Add a preview image for JPEG/PNG/GIF images 
			if (!empty($documentDetails['thumbnail_id'])) {
				$this->getImageHtmlSnippet($documentDetails['thumbnail_id'], $fields['upload_image/thumbnail_image']['snippet']['html']);
			} else {
				$fields['upload_image/delete_thumbnail_image']['hidden'] = true;
				$mimeType = ze\row::get('files', 'mime_type', $documentDetails['file_id']);
				if ($mimeType == 'image/gif' || $mimeType == 'image/png' || $mimeType == 'image/jpeg' || $mimeType == 'image/pjpeg') {
					$this->getImageHtmlSnippet($documentDetails['file_id'], $fields['upload_image/thumbnail_image']['snippet']['html']);
				} else {
					$fields['upload_image/thumbnail_image']['snippet']['html'] = ze\admin::phrase('No thumbnail avaliable');
				}
			}
		}
	}
	
	public function formatAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		if (!empty($fields['upload_image/delete_thumbnail_image']['pressed'])) {
			$fields['upload_image/thumbnail_image']['snippet']['html'] = ze\admin::phrase('No thumbnail available');
			$box['key']['delete_thumbnail'] = true;
		}
	}
	
	public function validateAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes, $saving) {
		$documentId = $box['key']['id'];
		$parentfolderId=ze\row::get('documents', 'folder_id', ['id' => $documentId]);
		$newDocumentName = trim($values['details/document_name']);
		
		if (!$newDocumentName ){
			$box['tabs']['details']['errors'][] = ze\admin::phrase('Please enter a filename.');
		} else {
			// Stop forward slashes being used in filenames
			$slashPos = strpos($newDocumentName, '/');
			if ($slashPos !== false) {
				$box['tabs']['details']['errors'][] = ze\admin::phrase('Your filename cannot contain forward slashes (/).');
			}
		}
		
		$sql =  "
			SELECT id
			FROM ".DB_NAME_PREFIX."documents
			WHERE type = 'file' 
			AND folder_id = ". (int) $parentfolderId. "
			AND filename = '". ze\escape::sql($newDocumentName). "' 
			AND id != ". (int) $documentId;
		
		$documentIdList = [];
		$result = ze\sql::select($sql);
		while($row = ze\sql::fetchAssoc($result)) {
				$documentIdList[] = $row;
		}
		$numberOfIds = count($documentIdList);
		
		if ($numberOfIds > 0){
			$box['tabs']['details']['errors'][] = ze\admin::phrase('The filename “[[folder_name]]” is already taken. Please choose a different name.', ['folder_name' => $newDocumentName]);
		}
	}
	
	public function saveAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		$documentId = $box['key']['id'];
		$documentTitle = $values['details/document_title'];
		
		$document = ze\row::get('documents', ['filename', 'file_id', 'title'], ['id' => $documentId]);
		$file = ze\row::get('files', ['id', 'filename', 'path', 'created_datetime', 'short_checksum'], $document['file_id']);
		
		$oldDocumentName = $document['filename'];
		if ($documentId) {
			$documentName = trim($values['details/document_name']) . '.' . trim($values['details/document_extension']);
		} else {
			$documentName = trim($values['details/document_name']);
		}
		
		//Rename public files directory and update filename if different
		if ($oldDocumentName != $documentName) {
			$symFolder =  CMS_ROOT . 'public/downloads/' . $file['short_checksum'];
			$oldSymPath = $symFolder . '/' . $oldDocumentName;
			$newSymPath = $symFolder . '/' . $documentName;
			if(!ze\server::isWindows() && is_link($oldSymPath)) {
				rename($oldSymPath, $newSymPath);
			}
			
			//Documents with the same file must have the same filename for now or the public link would break.
			ze\row::update('documents', ['filename' => $documentName], ['file_id' => $document['file_id']]);
			
			//Update any htaccess files to redirect to the new path
			ze\document::remakeRedirectHtaccessFiles($documentId);
		}
		
		if ($document['title'] != $documentTitle) {
			ze\row::update('documents', ['title' => $documentTitle], $documentId);
		}
		
		//Save document thumbnail image
		$old_image = ze\row::getArray('documents', 'file_id', $documentId);
		$new_image = $values['zenario_common_feature__upload'];
		
		if ($new_image) {
			if (!in_array($new_image, $old_image)) {
				if ($path = ze\file::getPathOfUploadInCacheDir($new_image)) {
					$fileId = ze\file::addToDocstoreDir('document_thumbnail', $path);
					$fileDetails = [];
					$fileDetails['thumbnail_id'] = $fileId;
					//update thumbnail
					ze\row::set('documents', $fileDetails, $documentId);
				}
			}
		} elseif ($box['key']['delete_thumbnail']) {
			ze\row::update('documents', ['thumbnail_id' => 0], $documentId);
		}
	
		//Save document tags
		ze\row::delete('document_tag_link', ['document_id' => $documentId]);
		$tagIds = ze\ray::explodeAndTrim($values['details/tags']);
		foreach ($tagIds as $tagId) {
			ze\row::set('document_tag_link', 
				['tag_id' => $tagId, 'document_id' => $documentId], 
				['tag_id' => $tagId, 'document_id' => $documentId]);
		}
	}
	
}