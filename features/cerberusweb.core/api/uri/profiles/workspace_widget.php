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

class PageSection_ProfilesWorkspaceWidget extends Extension_PageSection {
	function render() {
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // workspace_widget 
		@$context_id = intval(array_shift($stack)); // 123

		$context = CerberusContexts::CONTEXT_WORKSPACE_WIDGET;
		
		Page_Profiles::renderProfile($context, $context_id, $stack);
	}
	
	function getWidgetParamsAction() {
		@$widget_id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$extension_id = DevblocksPlatform::importGPC($_REQUEST['extension'],'string','');
		
		if(false == ($widget_extension = Extension_WorkspaceWidget::get($extension_id)))
			return;
		
		if(false == ($widget = DAO_WorkspaceWidget::get($widget_id))) {
			$widget = new Model_WorkspaceWidget();
			$widget->extension_id = $widget_extension->id;
		}
		
		$widget_extension->renderConfig($widget);
	}
	
	function savePeekJsonAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'], 'string', '');
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		header('Content-Type: application/json; charset=utf-8');
		
		try {
			if(!empty($id) && !empty($do_delete)) { // Delete
				if(!$active_worker->hasPriv(sprintf("contexts.%s.delete", CerberusContexts::CONTEXT_WORKSPACE_WIDGET)))
					throw new Exception_DevblocksAjaxValidationError(DevblocksPlatform::translate('error.core.no_acl.delete'));

				if(false == ($widget = DAO_WorkspaceWidget::get($id)))
					throw new Exception_DevblocksAjaxValidationError("Invalid widget.");
					
				if(!Context_WorkspaceWidget::isWriteableByActor($widget, $active_worker))
					throw new Exception_DevblocksAjaxValidationError(DevblocksPlatform::translate('error.core.no_acl.delete'));
					
				DAO_WorkspaceWidget::delete($id);
				
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'view_id' => $view_id,
				));
				return;
				
			} else {
				@$mode = DevblocksPlatform::importGPC($_REQUEST['mode'], 'string', '');
				
				if($id)
					$mode = 'build';
				
				switch($mode) {
					case 'import':
						@$workspace_tab_id = DevblocksPlatform::importGPC($_REQUEST['workspace_tab_id'], 'integer', 0);
						@$import_json = DevblocksPlatform::importGPC($_REQUEST['import_json'], 'string', '');
						
						@$json = json_decode($import_json, true);
						
						if(
							empty($import_json)
							|| false == (@$widget_json = json_decode($import_json, true))
							)
							throw new Exception_DevblocksAjaxValidationError("Invalid JSON.");
						
						if(empty($workspace_tab_id))
							throw new Exception_DevblocksAjaxValidationError("Invalid workspace tab target");
						
						if(!isset($widget_json['widget']['extension_id']))
							throw new Exception_DevblocksAjaxValidationError("JSON doesn't contain widget extension info");
						
						if(!isset($widget_json['widget']['params']))
							throw new Exception_DevblocksAjaxValidationError("JSON doesn't contain widget params");
						
						@$name = $widget_json['widget']['label'] ?: 'New widget';
						@$extension_id = $widget_json['widget']['extension_id'];
						
						if(empty($extension_id) || null == ($extension = Extension_WorkspaceWidget::get($extension_id)))
							throw new Exception_DevblocksAjaxValidationError("Invalid widget extension");
						
						// Allow prompted configuration of the widget import
						// [TODO] Are there configurable fields in this import file?
						/*
						@$configure_fields = $widget_json['widget']['configure'];
						
						if(is_array($configure_fields) && !empty($configure_fields)) {
							// If the worker has provided the configuration, make the changes to JSON array
							if(!empty($configure)) {
								foreach($configure_fields as $config_field_idx => $config_field) {
									if(!isset($config_field['path']))
										continue;
									
									if(!isset($configure[$config_field_idx]))
										continue;
									
									$ptr =& DevblocksPlatform::jsonGetPointerFromPath($widget_json, $config_field['path']);
									$ptr = $configure[$config_field_idx];
								}
								
							// If the worker hasn't been prompted, do that now
							} else {
								$tpl = DevblocksPlatform::services()->template();
								$tpl->assign('import_json', $import_json);
								$tpl->assign('import_fields', $configure_fields);
								$config_html = $tpl->fetch('devblocks:cerberusweb.core::internal/import/prompted/configure_json_import.tpl');
								
								echo json_encode(array(
									'config_html' => $config_html,
								));
								return;
							}
						}
						*/
						
						$fields = [
							DAO_WorkspaceWidget::LABEL => $name,
							DAO_WorkspaceWidget::EXTENSION_ID => $extension_id,
							DAO_WorkspaceWidget::WORKSPACE_TAB_ID => $workspace_tab_id,
							DAO_WorkspaceWidget::POS => '0000',
							DAO_WorkspaceWidget::PARAMS_JSON => json_encode($widget_json['widget']['params'])
						];
						
						if(!DAO_WorkspaceWidget::validate($fields, $error))
							throw new Exception_DevblocksAjaxValidationError($error);
						
						if(!DAO_WorkspaceWidget::onBeforeUpdateByActor($active_worker, $fields, null, $error))
							throw new Exception_DevblocksAjaxValidationError($error);
						
						$id = DAO_WorkspaceWidget::create($fields);
						DAO_WorkspaceWidget::onUpdateByActor($active_worker, $id, $fields);
						
						if(!empty($view_id) && !empty($id))
							C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_WORKSPACE_WIDGET, $id);
						
						echo json_encode([
							'status' => true,
							'id' => $id,
							'label' => $name,
							'view_id' => $view_id,
						]);
						return;
						break;
					
					case 'build':
						@$name = DevblocksPlatform::importGPC($_REQUEST['name'], 'string', '');
						@$workspace_tab_id = DevblocksPlatform::importGPC($_REQUEST['workspace_tab_id'], 'integer', 0);
						@$extension_id = DevblocksPlatform::importGPC($_REQUEST['extension_id'], 'string', '');
						@$width_units = DevblocksPlatform::importGPC($_REQUEST['width_units'], 'integer', 1);
						
						$width_units = DevblocksPlatform::intClamp($width_units, 1, 4);
						
						if(empty($id)) { // New
							$fields = array(
								DAO_WorkspaceWidget::EXTENSION_ID => $extension_id,
								DAO_WorkspaceWidget::LABEL => $name,
								DAO_WorkspaceWidget::UPDATED_AT => time(),
								DAO_WorkspaceWidget::WIDTH_UNITS => $width_units,
								DAO_WorkspaceWidget::WORKSPACE_TAB_ID => $workspace_tab_id,
							);
							
							if(!DAO_WorkspaceWidget::validate($fields, $error))
								throw new Exception_DevblocksAjaxValidationError($error);
								
							if(!DAO_WorkspaceWidget::onBeforeUpdateByActor($active_worker, $fields, null, $error))
								throw new Exception_DevblocksAjaxValidationError($error);
							
							$id = DAO_WorkspaceWidget::create($fields);
							DAO_WorkspaceWidget::onUpdateByActor($active_worker, $id, $fields);
							
							if(!empty($view_id) && !empty($id))
								C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_WORKSPACE_WIDGET, $id);
							
						} else { // Edit
							$fields = array(
								DAO_WorkspaceWidget::LABEL => $name,
								DAO_WorkspaceWidget::UPDATED_AT => time(),
								DAO_WorkspaceWidget::WIDTH_UNITS => $width_units,
								DAO_WorkspaceWidget::WORKSPACE_TAB_ID => $workspace_tab_id,
							);
							
							if(!DAO_WorkspaceWidget::validate($fields, $error, $id))
								throw new Exception_DevblocksAjaxValidationError($error);
								
							if(!DAO_WorkspaceWidget::onBeforeUpdateByActor($active_worker, $fields, $id, $error))
								throw new Exception_DevblocksAjaxValidationError($error);
							
							DAO_WorkspaceWidget::update($id, $fields);
							DAO_WorkspaceWidget::onUpdateByActor($active_worker, $id, $fields);
						}
						
						// Widget extensions
						if(false == ($widget = DAO_WorkspaceWidget::get($id)))
							throw new Exception_DevblocksAjaxValidationError("Failed to load widget.");
						
						if(null == ($widget_extension = $widget->getExtension()))
							throw new Exception_DevblocksAjaxValidationError("Invalid widget extension.");
							
						if(method_exists($widget_extension, 'saveConfig'))
							$widget_extension->saveConfig($widget);
						
						// Custom field saves
						@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', []);
						if(!DAO_CustomFieldValue::handleFormPost(CerberusContexts::CONTEXT_WORKSPACE_WIDGET, $id, $field_ids, $error))
							throw new Exception_DevblocksAjaxValidationError($error);
						
						echo json_encode([
							'status' => true,
							'id' => $id,
							'label' => $name,
							'view_id' => $view_id,
						]);
						return;
						break;
				}
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
	
	function getWidgetDatasourceConfigAction() {
		@$widget_id = DevblocksPlatform::importGPC($_REQUEST['widget_id'], 'string', '');
		@$params_prefix = DevblocksPlatform::importGPC($_REQUEST['params_prefix'], 'string', null);
		@$ext_id = DevblocksPlatform::importGPC($_REQUEST['ext_id'], 'string', '');

		if(null == ($widget = DAO_WorkspaceWidget::get($widget_id))) {
			$widget = new Model_WorkspaceWidget();
			//$widget->extension_id = $ext_id;
		}
		
		if(null == ($datasource_ext = Extension_WorkspaceWidgetDatasource::get($ext_id)))
			return;
		
		$datasource_ext->renderConfig($widget, $widget->params, $params_prefix);
	}
	
	function renderWidgetAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		@$full = DevblocksPlatform::importGPC($_REQUEST['full'], 'bool', false);
		@$refresh_options = DevblocksPlatform::importGPC($_REQUEST['options'], 'array', []);
		
		if(false == ($widget = DAO_WorkspaceWidget::get($id)))
			return;
		
		if(false == ($extension = $widget->getExtension()))
			return;
		
		// If full, we also want to replace the container
		if($full) {
			$tpl = DevblocksPlatform::services()->template();
			$tpl->assign('widget', $widget);
			
			if(false == ($tab = $widget->getWorkspaceTab()))
				return;
			
			$tpl->assign('extension', $extension);
			
			$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl');
			
		} else {
			$extension->render($widget, $refresh_options);
		}
	}
	
	function reorderWidgetsAction() {
		@$tab_id = DevblocksPlatform::importGPC($_REQUEST['tab_id'], 'integer', 0);
		@$zones = DevblocksPlatform::importGPC($_REQUEST['zones'], 'array', []);
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(false == ($tab = DAO_WorkspaceTab::get($tab_id)))
			return;
		
		// ACL
		if(!Context_WorkspaceTab::isWriteableByActor($tab, $active_worker))
			return;
		
		$widgets = DAO_WorkspaceWidget::getByTab($tab_id);
		
		// Sanitize widget IDs
		foreach($zones as &$zone) {
			$zone = array_values(array_intersect($zone, array_keys($widgets)));
		}
		
		DAO_WorkspaceWidget::reorder($zones);
	}
	
	function getFieldsTabsByContextAction() {
		@$context = DevblocksPlatform::importGPC($_REQUEST['context'],'string','');
		
		$tpl = DevblocksPlatform::services()->template();
		
		if(false == ($context_ext = Extension_DevblocksContext::get($context)))
			return;
		
		if(!($context_ext instanceof IDevblocksContextProfile))
			return;
		
		$tpl->assign('context_ext', $context_ext);
		
		// =================================================================
		// Properties
		
		$properties = $context_ext->profileGetFields();
		
		$tpl->assign('custom_field_values', []);
		
		$properties_cfields = Page_Profiles::getProfilePropertiesCustomFields($context, null);
		
		if(!empty($properties_cfields))
			$properties = array_merge($properties, $properties_cfields);
		
		$tpl->assign('properties', $properties);
		
		$properties_custom_fieldsets = Page_Profiles::getProfilePropertiesCustomFieldsets($context, null, [], true);
		$tpl->assign('properties_custom_fieldsets', $properties_custom_fieldsets);
		
		// =================================================================
		// Search buttons
		
		$search_contexts = Extension_DevblocksContext::getAll(false, ['search']);
		$tpl->assign('search_contexts', $search_contexts);
		
		// =================================================================
		// Template
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/record_fields/fields_config_tabs.tpl');
	}
	
	function testWidgetTemplateAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'int', 0);
		@$params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', []);
		@$template_key = DevblocksPlatform::importGPC($_REQUEST['template_key'], 'string', '');
		
		$tpl_builder = DevblocksPlatform::services()->templateBuilder();
		$tpl = DevblocksPlatform::services()->template();
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		@$template = $params[$template_key];
		
		$dict = DevblocksDictionaryDelegate::instance([
			'current_worker_id' => $active_worker->id,
			'current_worker__context' => CerberusContexts::CONTEXT_WORKER,
			'widget_id' => $id,
			'widget__context' => CerberusContexts::CONTEXT_WORKSPACE_WIDGET,
		]);
		
		$success = false;
		$output = '';
		
		if(!is_string($template) || false === (@$out = $tpl_builder->build($template, $dict))) {
			// If we failed, show the compile errors
			$errors = $tpl_builder->getErrors();
			$success = false;
			$output = @array_shift($errors);
			
		} else {
			$success = true;
			$output = $out;
		}
		
		$tpl->assign('success', $success);
		$tpl->assign('output', $output);
		$tpl->display('devblocks:cerberusweb.core::internal/renderers/test_results.tpl');
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
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=workspace_widget', true),
				);
				$models[] = $model;
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $opp_id => $row) {
				if($opp_id==$explore_from)
					$orig_pos = $pos;
				
				$url = $url_writer->writeNoProxy(sprintf("c=profiles&type=workspace_widget&id=%d-%s", $row[SearchFields_WorkspaceWidget::ID], DevblocksPlatform::strToPermalink($row[SearchFields_WorkspaceWidget::LABEL])), true);
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $row[SearchFields_WorkspaceWidget::ID],
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
