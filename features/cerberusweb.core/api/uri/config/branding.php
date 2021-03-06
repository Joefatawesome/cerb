<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2018, Webgroup Media LLC
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

class PageSection_SetupBranding extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::services()->template();
		$visit = CerberusApplication::getVisit();
		
		$visit->set(ChConfigurationPage::ID, 'branding');
		
		$tpl->display('devblocks:cerberusweb.core::configuration/section/branding/index.tpl');
	}
	
	function saveJsonAction() {
		try {
			$settings = DevblocksPlatform::services()->pluginSettings();
			$worker = CerberusApplication::getActiveWorker();
			
			if(!$worker || !$worker->is_superuser)
				throw new Exception("You are not a superuser.");
			
			header('Content-Type: application/json; charset=utf-8');
			
			@$title = DevblocksPlatform::importGPC($_POST['title'],'string','');
			@$favicon = DevblocksPlatform::importGPC($_POST['favicon'],'string','');
			@$user_stylesheet = DevblocksPlatform::importGPC($_POST['user_stylesheet'],'string');
	
			if(empty($title))
				$title = CerberusSettingsDefaults::HELPDESK_TITLE;
			
			// Test the favicon
			if(!empty($favicon) && null == parse_url($favicon, PHP_URL_SCHEME))
				throw new Exception("The favicon URL is not valid. Please include a full URL like http://example.com/favicon.ico");
			
			// Is there a user-defined stylesheet?
			if($user_stylesheet) {
				$user_stylesheet_updated_at = time();
			} else {
				$user_stylesheet_updated_at = 0;
			}
			
			$settings->set('cerberusweb.core',CerberusSettings::HELPDESK_TITLE, $title);
			$settings->set('cerberusweb.core',CerberusSettings::HELPDESK_FAVICON_URL, $favicon);
			$settings->set('cerberusweb.core',CerberusSettings::UI_USER_STYLESHEET, $user_stylesheet);
			$settings->set('cerberusweb.core',CerberusSettings::UI_USER_STYLESHEET_UPDATED_AT, $user_stylesheet_updated_at);
			
			echo json_encode(array('status'=>true));
			return;
				
		} catch(Exception $e) {
			echo json_encode(array('status'=>false,'error'=>$e->getMessage()));
			return;
		}
	}
};