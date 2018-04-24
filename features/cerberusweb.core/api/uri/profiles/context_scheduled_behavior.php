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

class PageSection_ProfilesContextScheduledBehavior extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::services()->template();
		$visit = CerberusApplication::getVisit();
		$translate = DevblocksPlatform::getTranslationService();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // context_scheduled_behavior 
		@$id = intval(array_shift($stack)); // 123

		$context = CerberusContexts::CONTEXT_BEHAVIOR_SCHEDULED;
		
		if(null == ($context_scheduled_behavior = DAO_ContextScheduledBehavior::get($id))) {
			return;
		}
		$tpl->assign('context_scheduled_behavior', $context_scheduled_behavior);

		// Context

		if(false == ($context_ext = Extension_DevblocksContext::get($context, true)))
			return;

		// Dictionary
		
		$labels = $values = [];
		CerberusContexts::getContext($context, $context_scheduled_behavior, $labels, $values, '', true, false);
		$dict = DevblocksDictionaryDelegate::instance($values);
		$tpl->assign('dict', $dict);

		// Tab persistence
		
		$point = 'profiles.context_scheduled_behavior.tab';
		$tpl->assign('point', $point);
		
		if(null == (@$tab_selected = $stack[0])) {
			$tab_selected = $visit->get($point, '');
		}
		$tpl->assign('tab_selected', $tab_selected);
		
		// Properties
		
		$properties = [];
		
		$properties['run_date'] = [
			'label' => mb_ucfirst($translate->_('dao.context_scheduled_behavior.run_date')),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $context_scheduled_behavior->run_date,
		];
		
		$properties['behavior_id'] = [
			'label' => mb_ucfirst($translate->_('common.behavior')),
			'type' => Model_CustomField::TYPE_LINK,
			'value' => $context_scheduled_behavior->behavior_id,
			'params' => [
				'context' => CerberusContexts::CONTEXT_BEHAVIOR,
			]
		];
		
		$properties['context_id'] = [
			'label' => mb_ucfirst($translate->_('common.target')),
			'type' => Model_CustomField::TYPE_LINK,
			'value' => $context_scheduled_behavior->context_id,
			'params' => [
				'context' => $context_scheduled_behavior->context,
			]
		];
		
		// Custom Fields
		
		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds($context, $context_scheduled_behavior->id)) or [];
		$tpl->assign('custom_field_values', $values);
		
		$properties_cfields = Page_Profiles::getProfilePropertiesCustomFields($context, $values);
		
		if(!empty($properties_cfields))
			$properties = array_merge($properties, $properties_cfields);
		
		// Custom Fieldsets
		
		$properties_custom_fieldsets = Page_Profiles::getProfilePropertiesCustomFieldsets($context, $context_scheduled_behavior->id, $values);
		$tpl->assign('properties_custom_fieldsets', $properties_custom_fieldsets);
		
		// Link counts
		
		$properties_links = array(
			$context => array(
				$context_scheduled_behavior->id => 
					DAO_ContextLink::getContextLinkCounts(
						$context,
						$context_scheduled_behavior->id,
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
		
		// Card search buttons
		$search_buttons = $context_ext->getCardSearchButtons($dict, []);
		$tpl->assign('search_buttons', $search_buttons);
		
		// Template
		$tpl->display('devblocks:cerberusweb.core::profiles/context_scheduled_behavior.tpl');
	}
	
	function savePeekJsonAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'], 'string', '');
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		header('Content-Type: application/json; charset=utf-8');
		
		try {
			if(!empty($id) && !empty($do_delete)) { // Delete
				if(!$active_worker->hasPriv(sprintf("contexts.%s.delete", CerberusContexts::CONTEXT_BEHAVIOR_SCHEDULED)))
					throw new Exception_DevblocksAjaxValidationError(DevblocksPlatform::translate('error.core.no_acl.delete'));
				
				DAO_ContextScheduledBehavior::delete($id);
				
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'view_id' => $view_id,
				));
				return;
				
			} else {
				@$behavior_id = DevblocksPlatform::importGPC($_REQUEST['behavior_id'], 'integer', 0);
				@$behavior_params = DevblocksPlatform::importGPC($_REQUEST['behavior_params'], 'array', []);
				@$context = DevblocksPlatform::importGPC($_REQUEST['context'], 'string', '');
				@$context_id = DevblocksPlatform::importGPC($_REQUEST['context_id'], 'integer', 0);
				@$run_date = DevblocksPlatform::importGPC($_REQUEST['run_date'], 'string', '');
				
				if(empty($id)) { // New
					$fields = [
						DAO_ContextScheduledBehavior::BEHAVIOR_ID => $behavior_id,
						DAO_ContextScheduledBehavior::CONTEXT => $context,
						DAO_ContextScheduledBehavior::CONTEXT_ID => $context_id,
						DAO_ContextScheduledBehavior::RUN_DATE => @intval(strtotime($run_date)),
						DAO_ContextScheduledBehavior::VARIABLES_JSON => json_encode($behavior_params),
					];
					
					if(!DAO_ContextScheduledBehavior::validate($fields, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
						
					if(!DAO_ContextScheduledBehavior::onBeforeUpdateByActor($active_worker, $fields, null, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					$id = DAO_ContextScheduledBehavior::create($fields);
					DAO_ContextScheduledBehavior::onUpdateByActor($active_worker, $id, $fields);
					
					if(!empty($view_id) && !empty($id))
						C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_BEHAVIOR_SCHEDULED, $id);
					
				} else { // Edit
					$fields = [
						DAO_ContextScheduledBehavior::BEHAVIOR_ID => $behavior_id,
						DAO_ContextScheduledBehavior::CONTEXT => $context,
						DAO_ContextScheduledBehavior::CONTEXT_ID => $context_id,
						DAO_ContextScheduledBehavior::RUN_DATE => @intval(strtotime($run_date)),
						DAO_ContextScheduledBehavior::VARIABLES_JSON => json_encode($behavior_params),
					];
					
					if(!DAO_ContextScheduledBehavior::validate($fields, $error, $id))
						throw new Exception_DevblocksAjaxValidationError($error);
						
					if(!DAO_ContextScheduledBehavior::onBeforeUpdateByActor($active_worker, $fields, $id, $error))
						throw new Exception_DevblocksAjaxValidationError($error);
					
					DAO_ContextScheduledBehavior::update($id, $fields);
					DAO_ContextScheduledBehavior::onUpdateByActor($active_worker, $id, $fields);
				}
				
				// Custom field saves
				@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', []);
				if(!DAO_CustomFieldValue::handleFormPost(CerberusContexts::CONTEXT_BEHAVIOR_SCHEDULED, $id, $field_ids, $error))
					throw new Exception_DevblocksAjaxValidationError($error);
				
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'label' => '', // [TODO]
					'view_id' => $view_id,
				));
				return;
			}
			
		} catch (Exception_DevblocksAjaxValidationError $e) {
			echo json_encode(array(
				'status' => false,
				'error' => $e->getMessage(),
				'field' => $e->getFieldName(),
			));
			return;
			
		} catch (Exception $e) {
			echo json_encode(array(
				'status' => false,
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
			$models = [];
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
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=scheduled_behavior', true),
					'toolbar_extension_id' => 'cerberusweb.contexts.context.scheduled.behavior.explore.toolbar',
				);
				$models[] = $model;
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $opp_id => $row) {
				if($opp_id==$explore_from)
					$orig_pos = $pos;
				
				$url = $url_writer->writeNoProxy(sprintf("c=profiles&type=scheduled_behavior&id=%d-%s", $row[SearchFields_ContextScheduledBehavior::ID], DevblocksPlatform::strToPermalink($row[SearchFields_ContextScheduledBehavior::NAME])), true);
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $row[SearchFields_ContextScheduledBehavior::ID],
					'url' => $url,
				);
				$models[] = $model;
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}
	
	function renderContextScheduledBehaviorAction() {
		@$context = DevblocksPlatform::importGPC($_REQUEST['context'],'string','');
		@$context_id = DevblocksPlatform::importGPC($_REQUEST['context_id'],'integer',0);
		
		$tpl = DevblocksPlatform::services()->template();
		
		$tpl->assign('context', $context);
		$tpl->assign('context_id', $context_id);
		$tpl->assign('expanded', true);
		
		$tpl->display('devblocks:cerberusweb.core::internal/macros/behavior/scheduled_behavior_profile.tpl');
	}
};