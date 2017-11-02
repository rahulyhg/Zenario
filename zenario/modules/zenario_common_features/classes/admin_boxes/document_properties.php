<?php
/*
 * Copyright (c) 2017, Tribal Limited
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


class zenario_common_features__admin_boxes__document_properties extends module_base_class {
	
	public function fillAdminBox($path, $settingGroup, &$box, &$fields, &$values) {
		if ($documentId = $box['key']['id']) {
			$documentTagsString = '';
			
			$documentDetails = getRow('documents',array('file_id', 'thumbnail_id', 'extract', 'extract_wordcount', 'title'),  $documentId);
			$documentName = getRow('documents', 'filename', array('type' => 'file','id' => $documentId));
			$box['title'] = adminPhrase('Editing metadata for document "[[filename]]".', array("filename"=>$documentName));
			
			$fields['details/document_title']['value'] = $documentDetails['title'];
			$fields['details/document_extension']['value'] = pathinfo($documentName, PATHINFO_EXTENSION);
			$fields['details/document_name']['value'] = pathinfo($documentName, PATHINFO_FILENAME);
			$fileDatetime=getRow('documents', 'file_datetime', array('type' => 'file','id' => $documentId));
			$fields['details/date_uploaded']['value'] = date('jS F Y H:i', strtotime($fileDatetime));
			
			if (setting('enable_document_tags')) {
				$documentTags = getRowsArray('document_tag_link', 'tag_id', array('document_id' => $documentId));
				foreach ($documentTags as $tag) {
					$documentTagsString .= $tag . ",";
				}
				$fields['details/tags']['value'] = $documentTagsString;
				$fields['details/link_to_add_tags']['snippet']['html'] = 
						adminPhrase('To add or edit document tags click <a[[link]]>this link</a>.',
							array('link' => ' href="'. htmlspecialchars(absCMSDirURL(). 'zenario/admin/organizer.php#zenario__content/panels/document_tags'). '" target="_blank"'));
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
				$mimeType = getRow('files', 'mime_type', $documentDetails['file_id']);
				if ($mimeType == 'image/gif' || $mimeType == 'image/png' || $mimeType == 'image/jpeg' || $mimeType == 'image/pjpeg') {
					$this->getImageHtmlSnippet($documentDetails['file_id'], $fields['upload_image/thumbnail_image']['snippet']['html']);
				} else {
					$fields['upload_image/thumbnail_image']['snippet']['html'] = adminPhrase('No thumbnail avaliable');
				}
			}
		}
	}
	
	public function formatAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		if (!empty($fields['upload_image/delete_thumbnail_image']['pressed'])) {
			$fields['upload_image/thumbnail_image']['snippet']['html'] = adminPhrase('No thumbnail available');
			$box['key']['delete_thumbnail'] = true;
		}
	}
	
	public function validateAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes, $saving) {
		$documentId = $box['key']['id'];
		$parentfolderId=getRow('documents', 'folder_id', array('id' => $documentId));
		$newDocumentName = trim($values['details/document_name']);
		
		if (!$newDocumentName ){
			$box['tabs']['details']['errors'][] = adminPhrase('Please enter a filename.');
		} else {
			// Stop forward slashes being used in filenames
			$slashPos = strpos($newDocumentName, '/');
			if ($slashPos !== false) {
				$box['tabs']['details']['errors'][] = adminPhrase('Your filename cannot contain forward slashes (/).');
			}
		}
		
		$sql =  "
			SELECT id
			FROM ".DB_NAME_PREFIX."documents
			WHERE type = 'file' 
			AND folder_id = ". (int) $parentfolderId. "
			AND filename = '". sqlEscape($newDocumentName). "' 
			AND id != ". (int) $documentId;
		
		$documentIdList = array();
		$result = sqlSelect($sql);
		while($row = sqlFetchAssoc($result)) {
				$documentIdList[] = $row;
		}
		$numberOfIds = count($documentIdList);
		
		if ($numberOfIds > 0){
			$box['tabs']['details']['errors'][] = adminPhrase('The filename “[[folder_name]]” is already taken. Please choose a different name.', array('folder_name' => $newDocumentName));
		}
	}
	
	public function saveAdminBox($path, $settingGroup, &$box, &$fields, &$values, $changes) {
		$documentId = $box['key']['id'];
		$documentTitle = $values['details/document_title'];
		
		$document = getRow('documents', array('filename', 'file_id', 'title'), array('id' => $documentId));
		$file = getRow('files', array('id', 'filename', 'path', 'created_datetime', 'short_checksum'), $document['file_id']);
		
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
			if(!windowsServer() && is_link($oldSymPath)) {
				rename($oldSymPath, $newSymPath);
			}
			
			//Documents with the same file must have the same filename for now or the public link would break.
			updateRow('documents', array('filename' => $documentName), array('file_id' => $document['file_id']));
			
			//Update any htaccess files to redirect to the new path
			zenario_common_features::remakeDocumentRedirectHtaccessFiles($documentId);
		}
		
		if ($document['title'] != $documentTitle) {
			updateRow('documents', array('title' => $documentTitle), $documentId);
		}
		
		//Save document thumbnail image
		$old_image = getRowsArray('documents', 'file_id', $documentId);
		$new_image = $values['zenario_common_feature__upload'];
		
		if ($new_image) {
			if (!in_array($new_image, $old_image)) {
				if ($path = Ze\File::getPathOfUploadedInCacheDir($new_image)) {
					$fileId = Ze\File::addToDocstoreDir('document_thumbnail', $path);
					$fileDetails = array();
					$fileDetails['thumbnail_id'] = $fileId;
					//update thumbnail
					setRow('documents', $fileDetails, $documentId);
				}
			}
		} elseif ($box['key']['delete_thumbnail']) {
			updateRow('documents', array('thumbnail_id' => 0), $documentId);
		}
	
		//Save document tags
		deleteRow('document_tag_link', array('document_id' => $documentId));
		$tagIds = explodeAndTrim($values['details/tags']);
		foreach ($tagIds as $tagId) {
			setRow('document_tag_link', 
				array('tag_id' => $tagId, 'document_id' => $documentId), 
				array('tag_id' => $tagId, 'document_id' => $documentId));
		}
	}
	
}