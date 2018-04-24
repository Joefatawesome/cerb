<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2014, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.ai/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.ai	    http://webgroup.media
***********************************************************************/

class PageSection_ProfilesBucket extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::services()->template();
		$visit = CerberusApplication::getVisit();
		$translate = DevblocksPlatform::getTranslationService();
		
		$context = CerberusContexts::CONTEXT_BUCKET;
		$active_worker = CerberusApplication::getActiveWorker();
		
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // bucket
		@$id = intval(array_shift($stack)); // 123

		if(null == ($bucket = DAO_Bucket::get($id)))
			return;
		
		$tpl->assign('bucket', $bucket);

		$context = CerberusContexts::CONTEXT_BUCKET;

		// Context

		if(false == ($context_ext = Extension_DevblocksContext::get($context, true)))
			return;

		// Dictionary
		
		$labels = $values = [];
		CerberusContexts::getContext($context, $bucket, $labels, $values, '', true, false);
		$dict = DevblocksDictionaryDelegate::instance($values);
		$tpl->assign('dict', $dict);
		
		// Tab persistence
		
		$point = 'profiles.bucket.tab';
		$tpl->assign('point', $point);
		
		if(null == (@$tab_selected = $stack[0])) {
			$tab_selected = $visit->get($point, '');
		}
		$tpl->assign('tab_selected', $tab_selected);
	
		// Properties
			
		$properties = [];
			
		$properties['group'] = array(
			'label' => mb_ucfirst($translate->_('common.group')),
			'type' => Model_CustomField::TYPE_LINK,
			'params' => array('context' => CerberusContexts::CONTEXT_GROUP),
			'value' => $bucket->group_id,
		);
			
		$properties['updated'] = array(
			'label' => DevblocksPlatform::translateCapitalized('common.updated'),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $bucket->updated_at,
		);
		
		$properties['is_default'] = array(
			'label' => mb_ucfirst($translate->_('common.default')),
			'type' => Model_CustomField::TYPE_CHECKBOX,
			'value' => $bucket->is_default,
		);
	
		// Custom Fields

		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds($context, $bucket->id)) or [];
		$tpl->assign('custom_field_values', $values);
		
		$properties_cfields = Page_Profiles::getProfilePropertiesCustomFields($context, $values);
		
		if(!empty($properties_cfields))
			$properties = array_merge($properties, $properties_cfields);
		
		// Custom Fieldsets

		$properties_custom_fieldsets = Page_Profiles::getProfilePropertiesCustomFieldsets($context, $bucket->id, $values);
		$tpl->assign('properties_custom_fieldsets', $properties_custom_fieldsets);
		
		// Link counts
		
		$properties_links = array(
			$context => array(
				$bucket->id => 
					DAO_ContextLink::getContextLinkCounts(
						$context,
						$bucket->id,
						array(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
					),
			),
			CerberusContexts::CONTEXT_GROUP => array(
				$bucket->group_id => 
					DAO_ContextLink::getContextLinkCounts(
						CerberusContexts::CONTEXT_GROUP,
						$bucket->group_id,
						array(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
					),
			),
		);
		$tpl->assign('properties_links', $properties_links);
		
		// Properties
		$tpl->assign('properties', $properties);
			
		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, $context);
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Interactions
		$interactions = Event_GetInteractionsForWorker::getInteractionsByPointAndWorker('record:' . $context, $dict, $active_worker);
		$interactions_menu = Event_GetInteractionsForWorker::getInteractionMenu($interactions);
		$tpl->assign('interactions_menu', $interactions_menu);

		// Card search buttons
		$search_buttons = $context_ext->getCardSearchButtons($dict, []);
		$tpl->assign('search_buttons', $search_buttons);
		
		// Template
		$tpl->display('devblocks:cerberusweb.core::profiles/bucket.tpl');
	}
	
	function savePeekJsonAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'], 'string', '');
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		header('Content-Type: application/json; charset=utf-8');
		
		try {
			if($id && false == ($bucket = DAO_Bucket::get($id)))
				throw new Exception_DevblocksAjaxValidationError("The specified bucket record doesn't exist.");
			
			// ACL
			if($id && !$active_worker->is_superuser && !$active_worker->isGroupManager($bucket->group_id))
				throw new Exception_DevblocksAjaxValidationError("You do not have permission to delete this bucket.");
			
			if($id && !empty($do_delete)) { // Delete
				@$delete_moveto = DevblocksPlatform::importGPC($_REQUEST['delete_moveto'],'integer',0);
				$buckets = DAO_Bucket::getAll();
				
				// Destination must exist
				if(empty($delete_moveto) || false == ($bucket_moveto = DAO_Bucket::get($delete_moveto)))
					throw new Exception_DevblocksAjaxValidationError("The destination bucket doesn't exist.");
				
				$where = sprintf("%s = %d", DAO_Ticket::BUCKET_ID, $id);
				DAO_Ticket::updateWhere(array(DAO_Ticket::BUCKET_ID => $bucket_moveto->id), $where);
				DAO_Bucket::delete($id);
				
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'view_id' => $view_id,
				));
				return;
				
			} else {
				@$name = DevblocksPlatform::importGPC($_REQUEST['name'],'string','');
				@$enable_mail = DevblocksPlatform::importGPC($_REQUEST['enable_mail'],'integer',0);
				
				$fields = [];
				
				if($enable_mail) {
					@$reply_address_id = DevblocksPlatform::importGPC($_REQUEST['reply_address_id'],'integer',0);
					@$reply_personal = DevblocksPlatform::importGPC($_REQUEST['reply_personal'],'string','');
					@$reply_signature_id = DevblocksPlatform::importGPC($_REQUEST['reply_signature_id'],'integer',0);
					@$reply_html_template_id = DevblocksPlatform::importGPC($_REQUEST['reply_html_template_id'],'integer',0);
				} else {
					$reply_address_id = 0;
					$reply_personal = '';
					$reply_signature_id = 0;
					$reply_html_template_id = 0;
				}
				
				$fields[DAO_Bucket::REPLY_ADDRESS_ID] = $reply_address_id;
				$fields[DAO_Bucket::REPLY_PERSONAL] = $reply_personal;
				$fields[DAO_Bucket::REPLY_SIGNATURE_ID] = $reply_signature_id;
				$fields[DAO_Bucket::REPLY_HTML_TEMPLATE_ID] = $reply_html_template_id;
				
				if(empty($id)) { // New
					@$group_id = DevblocksPlatform::importGPC($_REQUEST['group_id'],'integer',0);
					
					$fields[DAO_Bucket::NAME] = $name;
					$fields[DAO_Bucket::GROUP_ID] = $group_id;
					$fields[DAO_Bucket::UPDATED_AT] = time();
					
					if(!DAO_Bucket::validate($fields, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					if(!DAO_Bucket::onBeforeUpdateByActor($active_worker, $fields, null, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					$id = DAO_Bucket::create($fields);
					DAO_Bucket::onUpdateByActor($active_worker, $fields, $id);
					
					// Default bucket responsibilities
					DAO_Group::setBucketDefaultResponsibilities($id);
					
					if(!empty($view_id) && !empty($id))
						C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_BUCKET, $id);
					
				} else { // Edit
					$fields[DAO_Bucket::NAME] = $name;
					$fields[DAO_Bucket::UPDATED_AT] = time();
					
					if(!DAO_Bucket::validate($fields, $error, $id))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					if(!DAO_Bucket::onBeforeUpdateByActor($active_worker, $fields, $id, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					DAO_Bucket::update($id, $fields);
					DAO_Bucket::onUpdateByActor($active_worker, $fields, $id);
				}
				
				// Custom field saves
				@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', []);
				if(!DAO_CustomFieldValue::handleFormPost(CerberusContexts::CONTEXT_BUCKET, $id, $field_ids, $error))
					throw new Exception_DevblocksAjaxValidationError($error);
			}
			
			echo json_encode(array(
				'status' => true,
				'id' => $id,
				'label' => $name,
				'view_id' => $view_id,
			));
			return;
			
		} catch (Exception_DevblocksAjaxValidationError $e) {
				echo json_encode(array(
					'status' => false,
					'id' => $id,
					'error' => $e->getMessage(),
					'field' => $e->getFieldName(),
				));
				return;
			
		} catch (Exception $e) {
				echo json_encode(array(
					'status' => false,
					'id' => $id,
					'error' => 'An error occurred.',
				));
				return;
			
		}
	}
	
	function viewExploreAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::services()->url();
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time());
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);
		$view->setAutoPersist(false);

		// Page start
		@$explore_from = DevblocksPlatform::importGPC($_REQUEST['explore_from'],'integer',0);
		if(empty($explore_from)) {
			$orig_pos = 1+($view->renderPage * $view->renderLimit);
		} else {
			$orig_pos = 1;
		}

		$view->renderPage = 0;
		$view->renderLimit = 250;
		$pos = 0;
		
		do {
			$models = array();
			list($results, $total) = $view->getData();

			// Summary row
			if(0==$view->renderPage) {
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'title' => $view->name,
					'created' => time(),
//					'worker_id' => $active_worker->id,
					'total' => $total,
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=bucket', true),
					'toolbar_extension_id' => 'cerberusweb.contexts.bucket.explore.toolbar',
				);
				$models[] = $model;
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $opp_id => $row) {
				if($opp_id==$explore_from)
					$orig_pos = $pos;
				
				$url = $url_writer->writeNoProxy(sprintf("c=profiles&type=bucket&id=%d-%s", $row[SearchFields_Bucket::ID], DevblocksPlatform::strToPermalink($row[SearchFields_Bucket::NAME])), true);
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $row[SearchFields_Bucket::ID],
					'url' => $url,
				);
				$models[] = $model;
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}
};
