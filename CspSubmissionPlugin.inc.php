<?php

/**
 * @file plugins/generic/CspSubmission/CspSubmissionPlugin.inc.php
 *
 * Copyright (c) 2014-2019 LyseonTech
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CspSubmissionPlugin
 * @ingroup plugins_generic_CspSubmission
 *
 * @brief CspSubmission plugin class
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpClient\HttpClient;

import('lib.pkp.classes.plugins.GenericPlugin');
require_once(dirname(__FILE__) . '/vendor/autoload.php');

class CspSubmissionPlugin extends GenericPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success) {
			// Insert new field into author metadata submission form (submission step 3) and metadata form
			HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'metadataFieldEdit'));
			HookRegistry::register('TemplateManager::fetch', array($this, 'TemplateManager_fetch'));
			HookRegistry::register('TemplateManager::display',array(&$this, 'registerJS'));
			HookRegistry::register('FileManager::downloadFile',array($this, 'fileManager_downloadFile'));
			HookRegistry::register('Mail::send', array($this,'mail_send'));
			HookRegistry::register('submissionfilesuploadform::display', array($this,'submissionfilesuploadform_display'));

			HookRegistry::register('APIHandler::endpoints', array($this,'APIHandler_endpoints'));

			// Hook for initData in two forms -- init the new field
			HookRegistry::register('submissionsubmitstep3form::initdata', array($this, 'metadataInitData'));

			// Hook for readUserVars in two forms -- consider the new field entry
			HookRegistry::register('submissionsubmitstep3form::readuservars', array($this, 'metadataReadUserVars'));

			// Hook for execute in two forms -- consider the new field in the article settings
			HookRegistry::register('submissionsubmitstep3form::execute', array($this, 'metadataExecuteStep3'));
			HookRegistry::register('submissionsubmitstep4form::execute', array($this, 'metadataExecuteStep4'));

			// Hook for save in two forms -- add validation for the new field
			HookRegistry::register('submissionsubmitstep3form::Constructor', array($this, 'addCheck'));

			// Consider the new field for ArticleDAO for storage
			HookRegistry::register('articledao::getAdditionalFieldNames', array($this, 'metadataReadUserVars'));

			HookRegistry::register('submissionfilesuploadform::validate', array($this, 'submissionfilesuploadformValidate'));

			HookRegistry::register('SubmissionHandler::saveSubmit', array($this, 'SubmissionHandler_saveSubmit'));
			HookRegistry::register('User::getMany::queryObject', array($this, 'pkp_services_pkpuserservice_getmany'));
			HookRegistry::register('UserDAO::_returnUserFromRowWithData', array($this, 'userDAO__returnUserFromRowWithData'));
			HookRegistry::register('User::getProperties::values', array($this, 'user_getProperties_values'));
			HookRegistry::register('authorform::initdata', array($this, 'authorform_initdata'));

			// This hook is used to register the components this plugin implements to
			// permit administration of custom block plugins.
			HookRegistry::register('LoadComponentHandler', array($this, 'setupGridHandler'));
		}
		return $success;
	}

	/**
	 * Register JavaScript file
	 *
	 * Hooked to the the `display` callback in TemplateManager
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	public function registerJS($hookName, $args) {
		if ($args[1] == "submission/form/index.tpl"){

			$request =& Registry::get('request');
			$templateManager =& $args[0];
	
			// // Load JavaScript file
			$templateManager->addJavaScript(
				'coautor',
				$request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . '/js/build.js',
				array(
					'contexts' => 'backend',
					'priority' => STYLE_SEQUENCE_LAST,
				)
			);
			$templateManager->addStyleSheet(
				'coautor',
				$request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . '/styles/build.css',
				array(
					'contexts' => 'backend',
					'priority' => STYLE_SEQUENCE_LAST,
				)
			);
		}

		return false;
	}

	/**
	 * Permit requests to the custom block grid handler
	 * @param $hookName string The name of the hook being invoked
	 * @param $args array The parameters to the invoked hook
	 */
	function setupGridHandler($hookName, $params) {
		$component =& $params[0];
		if ($component == 'plugins.generic.CspSubmission.controllers.grid.AddAuthorHandler') {
			return true;
		}
		return false;
  }
	
	function mail_send($hookName, $args){
		//$stageId = $this->article->getData('stageId');
		$stageId = $args[0]->submission->_data["stageId"];

		if (!empty($args[0]->emailKey) && $args[0]->emailKey == "REVIEW_REQUEST_ONECLICK"){			
			$body = $args[0]->_data['body'];
			
			preg_match("/href='(?P<url>.*)' class='submissionReview/",$body,$matches);
			$body = str_replace('{$submissionReviewUrlAccept}', $matches['url']."&accept=yes", $body);
			$body = str_replace('{$submissionReviewUrlReject}', $matches['url']."&accept=no", $body);
			$args[0]->_data['body'] = $body;
		}elseif ($stageId == 3 && !empty($args[0]->emailKey) && $args[0]->emailKey == "NOTIFICATION"){
			return true;
		}elseif ($args[0]->emailKey == "EDITOR_DECISION_INITIAL_DECLINE"){
			$request = \Application::get()->getRequest();;
			$subject = $request->_requestVars["subject"];
			$locale = AppLocale::getLocale();
			$userDao = DAORegistry::getDAO('UserDAO');
			$result = $userDao->retrieve(
				<<<QUERY
				SELECT a.email_key, a.body, a.subject

				FROM 
				
				(
					SELECT 	d.email_key, d.body, d.subject	
					FROM 	email_templates_default_data d	
					WHERE 	d.locale = '$locale'
					
					UNION ALL 
					
					SELECT 	t.email_key, o.body, o.subject	
					FROM 	ojs.email_templates t
					
					LEFT JOIN
					(
						SELECT 	a.body, b.subject, a.email_id
						FROM
						(
							SELECT 	setting_value as body, email_id
							FROM 	email_templates_settings 
							WHERE 	setting_name = 'body' AND locale = '$locale'
						)a
						LEFT JOIN
						(
								SELECT 	setting_value as subject, email_id
								FROM 	email_templates_settings
								WHERE 	setting_name = 'subject' AND locale = '$locale'
						)b
						ON a.email_id = b.email_id
					) o	
					ON o.email_id = t.email_id
					WHERE t.enabled = 1
				) a
				WHERE 	a.email_key  = '$subject'

				QUERY
			);

			$args[0]->setData('subject', $result->GetRowAssoc(false)['subject']);
			
		}
	}

	public function APIHandler_endpoints($hookName, $args) {
		if (isset($args[0]['GET'])) {
			foreach($args[0]['GET'] as $key => $endpoint) {
				if ($endpoint['pattern'] == '/{contextPath}/api/{version}/users') {
					if (!in_array(ROLE_ID_AUTHOR, $endpoint['roles'])) {
						$args[0]['GET'][$key]['roles'][] = ROLE_ID_AUTHOR;
					}
				}
			}
		}
	}

	function TemplateManager_fetch($hookName, $args) {
		$args[1];
		$templateMgr =& $args[0];
		$request = \Application::get()->getRequest();
		$stageId = $request->getUserVar('stageId');

		if ($args[1] == 'submission/form/step1.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('step1.tpl'));
			
			return true;
		} elseif ($args[1] == 'submission/form/step3.tpl'){
			$args[4] = $templateMgr->fetch($this->getTemplateResource('step3.tpl'));
			
			return true;
		} elseif ($args[1] == 'controllers/wizard/fileUpload/form/submissionArtworkFileMetadataForm.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('submissionArtworkFileMetadataForm.tpl'));
			
			return true;
		} elseif ($args[1] == 'controllers/grid/users/author/form/authorForm.tpl') {
			$request = \Application::get()->getRequest();
			$operation = $request->getRouter()->getRequestedOp($request);
			switch ($operation) {
				case 'addAuthor':
					if ($request->getUserVar('userId')) {
						$args[4] = $templateMgr->fetch($this->getTemplateResource('authorFormAdd.tpl'));
						return true;
					}
					import('plugins.generic.cspSubmission.controllers.list.autor.CoautorListPanel');

					$coautorListHandler = new CoautorListPanel(
						'CoautorListPanel',
						__('plugins.generic.CspSubmission.searchForAuthor'),
						[
							'apiUrl' => $request->getDispatcher()->url($request, ROUTE_API, $request->getContext()->getPath(), 'users'),
							'getParams' => [
								'roleIds' => [ROLE_ID_AUTHOR],
								'orderBy' => 'givenName',
								'orderDirection' => 'ASC'
							]
						]
					);

					$templateMgr->assign('containerData', ['components' => ['CoautorListPanel' => $coautorListHandler->getConfig()]]);
					$templateMgr->assign('basejs', $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . '/js/build.js');
					$templateMgr->assign('basecss', $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . '/styles/build.css');

					$args[4] = $templateMgr->fetch($this->getTemplateResource('authorForm.tpl'));
					return true;
				case 'updateAuthor':
					$templateMgr->assign('csrfToken', $request->getSession()->getCSRFToken());
					$args[4] = $templateMgr->fetch($this->getTemplateResource('authorFormAdd.tpl'));
					return true;
			}
		} elseif ($args[1] == 'controllers/modals/submissionMetadata/form/issueEntrySubmissionReviewForm.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('issueEntrySubmissionReviewForm.tpl'));

			return true;
		} elseif ($args[1] == 'controllers/grid/users/reviewer/form/advancedSearchReviewerForm.tpl') {
			$request = \Application::get()->getRequest();
			$submissionDAO = Application::getSubmissionDAO();
			$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
			$templateMgr->assign('title',$submission->getTitle(AppLocale::getLocale()));
			$args[4] = $templateMgr->fetch($this->getTemplateResource('advancedSearchReviewerForm.tpl'));

			return true;
		}elseif ($args[1] == 'reviewer/review/step1.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('reviewStep1.tpl'));
			
			return true;
		}elseif ($args[1] == 'reviewer/review/step3.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('reviewStep3.tpl'));
			
			return true;
		}elseif ($args[1] == 'controllers/grid/users/stageParticipant/addParticipantForm.tpl') {
			
			//$request = \Application::get()->getRequest();
			//$submissionId = $request->_requestVars["submissionId"];
			//$template = new SubmissionMailTemplate($submissionId);

			//$templateMgr->assign('message',$template->getBody(),AppLocale::getLocale());

			//$args[4] = $templateMgr->fetch($this->getTemplateResource('addParticipantForm.tpl'));


			//return true;
	//	}elseif ($args[1] == 'controllers/grid/grid.tpl' && $stageId == 3) {
			//$args[4] = $templateMgr->fetch($this->getTemplateResource('grid.tpl'));

			//return true;
		}elseif ($args[1] == 'controllers/modals/editorDecision/form/promoteForm.tpl') {
			if ($stageId == 3){
				$args[4] = $templateMgr->fetch($this->getTemplateResource('promoteFormStage3.tpl'));

				return true;
			}elseif ($stageId == 4){
				$args[4] = $templateMgr->fetch($this->getTemplateResource('promoteFormStage4.tpl'));

				return true;
			}
		}elseif ($args[1] == 'controllers/modals/editorDecision/form/sendReviewsForm.tpl') {

			$decision = $request->_requestVars["decision"];

			if ($decision == 2){ // BOTÃO SOLICITAR MODIFICAÇÕES
				
				$templateMgr->assign('skipEmail',0); // PASSA VARIÁVEL PARA ENVIAR EMAIL PARA O AUTOR
				$templateMgr->assign('decision',3); // PASSA VARIÁVEL PARA SELECIONAR O CAMPO " Solicitar modificações ao autor que estarão sujeitos a avaliação futura."

				$locale = AppLocale::getLocale();
				$userDao = DAORegistry::getDAO('UserDAO');
				$result = $userDao->retrieve(
					<<<QUERY

					SELECT a.email_key, a.body, a.subject

					FROM 
					
					(
						SELECT 	d.email_key, d.body, d.subject	
						FROM 	email_templates_default_data d	
						WHERE 	d.locale = '$locale'
						
						UNION ALL 
						
						SELECT 	t.email_key, o.body, o.subject	
						FROM 	ojs.email_templates t
						
						LEFT JOIN
						(
							SELECT 	a.body, b.subject, a.email_id
							FROM
							(
								SELECT 	setting_value as body, email_id
								FROM 	email_templates_settings 
								WHERE 	setting_name = 'body' AND locale = '$locale'
							)a
							LEFT JOIN
							(
									SELECT 	setting_value as subject, email_id
									FROM 	email_templates_settings
									WHERE 	setting_name = 'subject' AND locale = '$locale'
							)b
							ON a.email_id = b.email_id
						) o	
						ON o.email_id = t.email_id
						WHERE t.enabled = 1
					) a
					WHERE 	a.email_key LIKE 'REQUEST_REVISIONS%'					
					
					QUERY
				);
				$i = 0;
				while (!$result->EOF) {
					$i++;
					$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];
					$templateBody[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['body'];
	
					$result->MoveNext();
				}

				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign(array(
					'templates' => $templateSubject,
					'stageId' => $stageId,
					'message' => json_encode($templateBody),
					'default' => reset($templateBody)
				));						

			}elseif ($decision == 4 or $decision == 9){  // BOTÃO REJEITAR SUBMISSÃO
				$locale = AppLocale::getLocale();
				$userDao = DAORegistry::getDAO('UserDAO');
				$result = $userDao->retrieve(
					<<<QUERY

					SELECT a.email_key, a.body, a.subject

					FROM 
					
					(
						SELECT 	d.email_key, d.body, d.subject	
						FROM 	email_templates_default_data d	
						WHERE 	d.locale = '$locale'
						
						UNION ALL 
						
						SELECT 	t.email_key, o.body, o.subject	
						FROM 	ojs.email_templates t
						
						LEFT JOIN
						(
							SELECT 	a.body, b.subject, a.email_id
							FROM
							(
								SELECT 	setting_value as body, email_id
								FROM 	email_templates_settings 
								WHERE 	setting_name = 'body' AND locale = '$locale'
							)a
							LEFT JOIN
							(
									SELECT 	setting_value as subject, email_id
									FROM 	email_templates_settings
									WHERE 	setting_name = 'subject' AND locale = '$locale'
							)b
							ON a.email_id = b.email_id
						) o	
						ON o.email_id = t.email_id
						WHERE t.enabled = 1
					) a
					WHERE 	a.email_key LIKE 'EDITOR_DECISION_DECLINE%'					
					
					QUERY
				);
				$i = 0;
				while (!$result->EOF) {
					$i++;
					$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];
					$templateBody[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['body'];
	
					$result->MoveNext();
				}

				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign(array(
					'templates' => $templateSubject,
					'stageId' => $stageId,
					'message' => json_encode($templateBody),
					'default' => reset($templateBody)
				));				
			}

			$args[4] = $templateMgr->fetch($this->getTemplateResource('sendReviewsForm.tpl'));

			return true;

		}elseif ($args[1] == 'controllers/grid/queries/form/queryForm.tpl') {

			$locale = AppLocale::getLocale();
			$userDao = DAORegistry::getDAO('UserDAO');
			$result = $userDao->retrieve(
				<<<QUERY
				SELECT t.email_key, o.body, o.subject
				FROM email_templates t
				LEFT JOIN
				(
					SELECT a.body, b.subject, a.email_id
					FROM
					(
						SELECT setting_value as body, email_id
						FROM ojs.email_templates_settings 
						WHERE setting_name = 'body' AND locale = '$locale'
					)a
					LEFT JOIN
					(
							SELECT setting_value as subject, email_id
							FROM ojs.email_templates_settings
							WHERE setting_name = 'subject' AND locale = '$locale'
					)b
					ON a.email_id = b.email_id
				) o	
				ON o.email_id = t.email_id
				WHERE t.enabled = 1 AND t.email_key LIKE 'PRE_AVALIACAO%'
				QUERY
			);
			$i = 0;
			while (!$result->EOF) {
				$i++;
				$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];
				$templateBody[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['body'];

				$result->MoveNext();
			}

			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'templates' => $templateSubject,
				'stageId' => $stageId,
				'submissionId' => $this->_submissionId,
				'itemId' => $this->_itemId,
				'message' => json_encode($templateBody),
				'comment' => reset($templateBody)
			));

			$args[4] = $templateMgr->fetch($this->getTemplateResource('queryForm.tpl'));

			return true;

		}



		return false;
	}

	public function submissionfilesuploadform_display($hookName, $args)
	{
		/** @var Request */
		$request = \Application::get()->getRequest();
		$fileStage = $request->getUserVar('fileStage');
		if ($fileStage != 2) {
			return;
		}
		$submissionDAO = Application::getSubmissionDAO();
		$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
		$submissionProgress = $submission->getData('submissionProgress');
		if ($submissionProgress == 0){
			$templateMgr =& $args[0];

			$templateMgr->setData('revisionOnly',false);
			$templateMgr->setData('isReviewAttachment',true);
			$templateMgr->setData('submissionFileOptions',[]);
		}

	}

	function pkp_services_pkpuserservice_getmany($hookName, $args)
	{
		$refObject   = new ReflectionObject($args[1]);
		$refReviewStageId = $refObject->getProperty('reviewStageId');
		$refReviewStageId->setAccessible( true );
		$reviewStageId = $refReviewStageId->getValue($args[1]);

		if (!$reviewStageId && strpos($_SERVER["HTTP_REFERER"], 'submission/wizard')  ){
			$refObject   = new ReflectionObject($args[1]);
			$refColumns = $refObject->getProperty('columns');
			$refColumns->setAccessible( true );
			$columns = $refColumns->getValue($args[1]);
			$columns[] = Capsule::raw("trim(concat(ui1.setting_value, ' ', COALESCE(ui2.setting_value, ''))) AS instituicao");
			$columns[] = Capsule::raw('\'ojs\' AS type');
			$refColumns->setValue($args[1], $columns);
	
			$cspQuery = Capsule::table(Capsule::raw('csp.Pessoa p'));
			$cspQuery->leftJoin('users as u', function ($join) {
				$join->on('u.email', '=', 'p.email');
			});
			$cspQuery->whereNull('u.email');
			$cspQuery->whereIn('p.permissao', [0,2,3]);
	
			$refSearchPhrase = $refObject->getProperty('searchPhrase');
			$refSearchPhrase->setAccessible( true );
			$words = $refSearchPhrase->getValue($args[1]);
			if ($words) {
				$words = explode(' ', $words);
				if (count($words)) {
					foreach ($words as $word) {
						$cspQuery->where(function($q) use ($word) {
							$q->where(Capsule::raw('lower(p.nome)'), 'LIKE', "%{$word}%")
								->orWhere(Capsule::raw('lower(p.email)'), 'LIKE', "%{$word}%")
								->orWhere(Capsule::raw('lower(p.orcid)'), 'LIKE', "%{$word}%");
						});
					}
				}
			}
	
			$locale = AppLocale::getLocale();
			$args[0]->leftJoin('user_settings as ui1', function ($join) use ($locale) {
				$join->on('ui1.user_id', '=', 'u.user_id')
					->where('ui1.setting_name', '=', 'instituicao1')
					->where('ui1.locale', '=', $locale);
			});
			$args[0]->leftJoin('user_settings as ui2', function ($join) use ($locale) {
				$join->on('ui2.user_id', '=', 'u.user_id')
					->where('ui2.setting_name', '=', 'instituicao2')
					->where('ui2.locale', '=', $locale);
			});

			if (property_exists($args[1], 'countOnly')) {
				$refCountOnly = $refObject->getProperty('countOnly');
				$refCountOnly->setAccessible( true );
				if ($refCountOnly->getValue($args[1])) {
					$cspQuery->select(['p.idPessoa']);
					$args[0]->select(['u.user_id'])
						->groupBy('u.user_id');
				}
			} else {
				$userDao = DAORegistry::getDAO('UserDAO');
				// retrieve all columns of table users
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT `COLUMN_NAME`
					  FROM `INFORMATION_SCHEMA`.`COLUMNS` 
					 WHERE `TABLE_SCHEMA`='ojs' 
					   AND `TABLE_NAME`='users';
					QUERY
				);
				while (!$result->EOF) {
					$columnsNames[$result->GetRowAssoc(0)['column_name']] = 'null';
					$result->MoveNext();
				}
				// assign custom values to columns
				$columnsNames['user_id'] = "CONCAT('CSP|',p.idPessoa)";
				$columnsNames['email'] = 'p.email';
				$columnsNames['user_given'] = "SUBSTRING_INDEX(SUBSTRING_INDEX(p.nome, ' ', 1), ' ', -1)";
				$columnsNames['user_family'] = "TRIM( SUBSTR(p.nome, LOCATE(' ', p.nome)) )";
				$columnsNames['instituicao'] = 'p.instituicao1';
				$columnsNames['type'] = '\'csp\'';
				foreach ($columnsNames as $name => $value) {
					$cspQuery->addSelect(Capsule::raw($value . ' AS ' . $name));
				}
				$args[0]->select($columns)
					->groupBy('u.user_id', 'user_given', 'user_family');
			}
	
			$subOjsQuery = Capsule::table(Capsule::raw(
				<<<QUERY
				(
					{$args[0]->toSql()}
					UNION
					{$cspQuery->toSql()}
				) as u
				QUERY
			));
			$subOjsQuery->mergeBindings($args[0]);
			$subOjsQuery->mergeBindings($cspQuery);
			$refColumns->setValue($args[1], ['*']);
			$args[0] = $subOjsQuery;
		}
	}

	function userDAO__returnUserFromRowWithData($hookName, $args)
	{
		list($user, $row) = $args;
		if (isset($row['type'])) {
			if ($row['type'] == 'csp') {
				$locale = AppLocale::getLocale();
				$user->setData('id', (int)explode('|', $row['user_id'])[1]);
				$user->setData('familyName', [$locale => $row['user_family']]);
				$user->setData('givenName', [$locale => $row['user_given']]);
			}
			$user->setData('type', $row['type']);
			$user->setData('instituicao', $row['instituicao']);
		}
	}

	function user_getProperties_values($hookName, $args)
	{
		list(&$values, $user) = $args;
		$type = $user->getData('type');
		if ($type) {
			$values['type'] = $type;
			$values['instituicao'] = $user->getData('instituicao');
		}
	}

	public function authorform_initdata($hookName, $args)
	{
		$request = \Application::get()->getRequest();
		$type = $request->getUserVar('type');
		if ($type != 'csp') {
			return;
		}

		$form = $args[0];
		$form->setTemplate($this->getTemplateResource('authorFormAdd.tpl'));

		$userDao = DAORegistry::getDAO('UserDAO');
		$userCsp = $userDao->retrieve(
			<<<QUERY
			SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(p.nome, ' ', 1), ' ', -1) as given_name,
					TRIM( SUBSTR(p.nome, LOCATE(' ', p.nome)) ) family_name,
					email, orcid,
					TRIM(CONCAT(p.instituicao1, ' ', p.instituicao2)) AS affiliation
				FROM csp.Pessoa p
				WHERE p.idPessoa = ?
			QUERY,
			[(int) $request->getUserVar('userId')]
		)->GetRowAssoc(0);
		$locale = AppLocale::getLocale();
		$form->setData('givenName', [$locale => $userCsp['given_name']]);
		$form->setData('familyName', [$locale => $userCsp['family_name']]);
		$form->setData('affiliation', [$locale => $userCsp['affiliation']]);
		$form->setData('email', $userCsp['email']);
		$form->setData('orcid', $userCsp['orcid']);

		$args[0] = $form;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.CspSubmission.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.CspSubmission.description');
	}

	/**
	 * Insert Campo1 field into author submission step 3 and metadata edit form
	 */
	function metadataFieldEdit($hookName, $params) {
		$smarty =& $params[1];
		$output =& $params[2];
		$output .= $smarty->fetch($this->getTemplateResource('RemovePrefixoTitulo.tpl'));
		
		if($this->sectionId == 5){
			$output .= $smarty->fetch($this->getTemplateResource('Revisao.tpl'));
		}
		
		if($this->sectionId == 4){					
			$output .= $smarty->fetch($this->getTemplateResource('Tema.tpl'));
			$output .= $smarty->fetch($this->getTemplateResource('CodigoTematico.tpl'));
		}

		$output .= $smarty->fetch($this->getTemplateResource('ConflitoInteresse.tpl'));
		//$output .= $smarty->fetch($this->getTemplateResource('FonteFinanciamento.tpl'));
		$output .= $smarty->fetch($this->getTemplateResource('Agradecimentos.tpl'));
		
		if($this->sectionId == 6){	
			$output .= $smarty->fetch($this->getTemplateResource('CodigoArtigoRelacionado.tpl'));
		}

		$output .= $smarty->fetch($this->getTemplateResource('InclusaoAutores.tpl'));
		
		
		return false;
	}

	/**
	 * Concern Campo1 field in the form
	 */
	function metadataReadUserVars($hookName, $params) {
		$userVars =& $params[1];
		$userVars[] = 'ConflitoInteresse';
		$userVars[] = 'ConflitoInteresseQual';
		//$userVars[] = 'FonteFinanciamento';
		//$userVars[] = 'FonteFinanciamentoQual';		
		$userVars[] = 'Agradecimentos';		
		$userVars[] = 'CodigoTematico';
		$userVars[] = 'Tema';
		$userVars[] = 'CodigoArtigoRelacionado';
		$userVars[] = 'CodigoArtigo';
		$userVars[] = 'DOI';
		
		return false;
	}

	/**
	 * Set article Campo1
	 */
	function metadataExecuteStep3($hookName, $params) {
		$form =& $params[0];
		$article = $form->submission;
		$article->setData('ConflitoInteresse', $form->getData('ConflitoInteresse'));
		$article->setData('ConflitoInteresseQual', $form->getData('ConflitoInteresseQual'));
		//$article->setData('FonteFinanciamento', $form->getData('FonteFinanciamento'));
		//$article->setData('FonteFinanciamentoQual', $form->getData('FonteFinanciamentoQual'));		
		$article->setData('Agradecimentos', $form->getData('Agradecimentos'));	
		$article->setData('CodigoTematico', $form->getData('CodigoTematico'));
		$article->setData('Tema', $form->getData('Tema'));
		$article->setData('CodigoArtigoRelacionado', $form->getData('CodigoArtigoRelacionado'));
		$article->setData('DOI', $form->getData('DOI'));		
		
		return false;
	}

	function metadataExecuteStep4($hookName, $params) {
		$form =& $params[0];
		$article = $form->submission;				
		$userDao = DAORegistry::getDAO('UserDAO');
		$result = $userDao->retrieve(
			<<<QUERY
			SELECT CONCAT(LPAD(count(*)+1, CASE WHEN count(*) > 9999 THEN 5 ELSE 4 END, 0), '/', DATE_FORMAT(now(), '%y')) code
			FROM submissions
			WHERE YEAR(date_submitted) = YEAR(now())
			QUERY
		);
		$article->setData('CodigoArtigo', $result->GetRowAssoc(false)['code']);
		
		
		return false;
	}
	

	/**
	 * Init article Campo1
	 */
	function metadataInitData($hookName, $params) {
		$form =& $params[0];
		$article = $form->submission;
		$this->sectionId = $article->getData('sectionId');
		$form->setData('ConflitoInteresse', $article->getData('ConflitoInteresse'));				
		$form->setData('ConflitoInteresseQual', $article->getData('ConflitoInteresseQual'));	
		//$form->setData('FonteFinanciamento', $article->getData('FonteFinanciamento'));				
		//$form->setData('FonteFinanciamentoQual', $article->getData('FonteFinanciamentoQual'));			
		$form->setData('Agradecimentos', $article->getData('Agradecimentos'));			
		$form->setData('CodigoTematico', $article->getData('CodigoTematico'));	
		$form->setData('Tema', $article->getData('Tema'));	
		$form->setData('CodigoArtigoRelacionado', $article->getData('CodigoArtigoRelacionado'));	
		$form->setData('DOI', $article->getData('DOI'));	
		
		return false;
	}

	/**
	 * Add check/validation for the Campo1 field (= 6 numbers)
	 */
	function addCheck($hookName, $params) {
		$form =& $params[0];
		//$form->addCheck(new FormValidatorRegExp($form, 'ConflitoInteresse', 'optional', 'plugins.generic.CspSubmission.Campo1Valid', '/^\d{6}$/')); // COLOCAR UMA VALIDACAO DE QUANTIDADE MAXIMA DE CARACTERES 	
		if($_POST['ConflitoInteresse'] == "yes"){
			$form->addCheck(new FormValidatorLength($form, 'ConflitoInteresseQual', 'required', 'plugins.generic.CspSubmission.ConflitoInteresseQual.Valid', '>', 0));
			
		}
		//if($_POST['FonteFinanciamento'] == "yes"){
		//	$form->addCheck(new FormValidatorLength($form, 'FonteFinanciamentoQual', 'required', 'plugins.generic.CspSubmission.FonteFinanciamentoQual.Valid', '>', 0));			
		//}		

		if($this->sectionId == 4){		
			$form->addCheck(new FormValidatorLength($form, 'CodigoTematico', 'required', 'plugins.generic.CspSubmission.CodigoTematico.Valid', '>', 0));			
			$form->addCheck(new FormValidatorLength($form, 'Tema', 'required', 'plugins.generic.CspSubmission.Tema.Valid', '>', 0));			
		}

		if($this->sectionId == 6){		
			$form->addCheck(new FormValidatorLength($form, 'CodigoArtigoRelacionado', 'required', 'plugins.generic.CspSubmission.CodigoArtigoRelacionado.Valid', '>', 0));			
		}

		$form->addCheck(new FormValidatorCustom($form, 'DOI', 'optional', 'plugins.generic.CspSubmission.DOI.Valid', function($DOI) {
			if (!filter_var($DOI, FILTER_VALIDATE_URL)) {
				if (strpos($DOI, 'doi.org') === false){
					$DOI = 'http://dx.doi.org/'.$DOI;
				} elseif (strpos($DOI,'http') === false) {
					$DOI = 'http://'.$DOI;
				} else {
					return false;
				}				
			}

			$client = HttpClient::create();
			$response = $client->request('GET', $DOI);
			$statusCode = $response->getStatusCode();			
			return in_array($statusCode,[303,200]);
		}));

		
		return false;
	}

	public function submissionfilesuploadformValidate($hookName, $args) {
		// Retorna o tipo do arquivo enviado
		$genreId = $args[0]->getData('genreId');
		switch($genreId) {
			case 1:	// Corpo do artigo / Tabela (Texto)
				if (($_FILES['uploadedFile']['type'] <> 'application/msword') /*Doc*/
				and ($_FILES['uploadedFile']['type'] <> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') /*docx*/
				and ($_FILES['uploadedFile']['type'] <> 'application/vnd.oasis.opendocument.text')/*odt*/) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.AticleBody')
					);
					break;
				}

				$submissionDAO = Application::getSubmissionDAO();
				$request = \Application::get()->getRequest();
				/** @val Submission */
				$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
				$publication = $submission->getCurrentPublication();
				$sectionId = $publication->getData('sectionId');
				$sectionDAO = DAORegistry::getDAO('SectionDAO');
				$section = $sectionDAO->getById($sectionId);
				$wordCount = $section->getData('wordCount');

				if ($wordCount) {
					$formato = explode('.', $_FILES['uploadedFile']['name']);
					$formato = trim(strtolower(end($formato)));
	
					$readers = array('docx' => 'Word2007', 'odt' => 'ODText', 'rtf' => 'RTF', 'doc' => 'ODText');
					$doc = \PhpOffice\PhpWord\IOFactory::load($_FILES['uploadedFile']['tmp_name'], $readers[$formato]);
					$html = new PhpOffice\PhpWord\Writer\HTML($doc);
					$contagemPalavras = str_word_count(strip_tags($html->getWriterPart('Body')->write()));
					if ($contagemPalavras > $wordCount) {
						$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
							'sectoin' => $section->getTitle($publication->getData('locale')),
							'max'     => $wordCount,
							'count'   => $contagemPalavras
						]);
						$args[0]->addError('genreId', $phrase);
					}
				}
				break;
			case 10: // Fotografia / Imagem satélite (Resolução mínima de 300 dpi)
				if (!in_array($_FILES['uploadedFile']['type'], ['image/bmp', 'image/tiff', 'image/svg+xml'])) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.Image')
					);
				}
				break;		
			case 14: // Fluxograma (Texto ou Desenho Vetorial)
				if (($_FILES['uploadedFile']['type'] <> 'application/msword') /*doc*/
					and ($_FILES['uploadedFile']['type'] <> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') /*docx*/
					and ($_FILES['uploadedFile']['type'] <> 'application/vnd.oasis.opendocument.text')/*odt*/
					and ($_FILES['uploadedFile']['type'] <> 'image/x-eps')/*eps*/
					and ($_FILES['uploadedFile']['type'] <> 'image/svg+xml')/*svg*/
					and ($_FILES['uploadedFile']['type'] <> 'image/wmf')/*wmf*/) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.Flowchart')
					);
				}
				break;	
			case 15: // Gráfico (Planilha ou Desenho Vetorial)
				$_FILES['uploadedFile']['type'];
				if (($_FILES['uploadedFile']['type'] <> 'application/vnd.ms-excel') /*xls*/
					and ($_FILES['uploadedFile']['type'] <> 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') /*xlsx*/
					and ($_FILES['uploadedFile']['type'] <> 'application/vnd.oasis.opendocument.spreadsheet')/*ods*/
					and ($_FILES['uploadedFile']['type'] <> 'image/x-eps')/*eps*/
					and ($_FILES['uploadedFile']['type'] <> 'image/svg+xml')/*svg*/
					and ($_FILES['uploadedFile']['type'] <> 'image/wmf')/*wmf*/) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.Chart')
					);
				}
				break;	
			case 13: // Mapa (Desenho Vetorial)
				$_FILES['uploadedFile']['type'];
				if (($_FILES['uploadedFile']['type'] <> 'image/x-eps')/*eps*/
					and ($_FILES['uploadedFile']['type'] <> 'image/svg+xml')/*svg*/
					and ($_FILES['uploadedFile']['type'] <> 'image/wmf')/*wmf*/) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.Map')
					);
				}
				break;		
				case '': 							
					if (($_FILES['uploadedFile']['type'] <> 'application/pdf')/*PDF*/) {
						if ($args[0]->_errors[0]->getField() == 'genreId') {
							unset($args[0]->_errors[0]);
						}
						$args[0]->addError('typeId',
							__('plugins.generic.CspSubmission.SectionFile.invalidFormat.PDF')
						);
					}else{				
						$args[0]->setData('genreId',8);			
						$args[1] = true;														

						return true;
					}					
					break;																				
		}

		if (!defined('SESSION_DISABLE_INIT')) {
			$request = \Application::get()->getRequest();
			$user = $request->getUser();

			if (!$args[0]->isValid() && $user) {
				import('classes.notification.NotificationManager');
				$notificationManager = new NotificationManager();
				$notificationManager->createTrivialNotification(
					$user->getId(),
					NOTIFICATION_TYPE_FORM_ERROR,
					['contents' => $args[0]->getErrorsArray()]
				);
			}
		}
		if (!$args[0]->isValid()) {
			return true;
		}
		return false;
	}

	public function SubmissionHandler_saveSubmit($hookName, $args)
	{
		$this->article = $args[1];
	}

	function fileManager_downloadFile($hookName, $args)
	{
		list($filePath, $mediaType, $inline, $result, $fileName) = $args;
		if (is_readable($filePath)) {			
			if ($mediaType === null) {
				// If the media type wasn't specified, try to detect.
				$mediaType = PKPString::mime_content_type($filePath);
				if (empty($mediaType)) $mediaType = 'application/octet-stream';
			}
			if ($fileName === null) {
				// If the filename wasn't specified, use the server-side.
				$fileName = basename($filePath);
			}
			preg_match('/\/articles\/(?P<id>\d+)\//',$filePath,$matches);
			if ($matches) {
				$submissionDao = DAORegistry::getDAO('SubmissionFileDAO');
				$result = $submissionDao->retrieve(
					<<<QUERY
					SELECT REPLACE(setting_value,'/','_') AS codigo_artigo
					FROM ojs.submission_settings
					WHERE setting_name = 'CodigoArtigo' AND submission_id = ?
					QUERY, 
					[$matches['id']]
				);
				$a = $result->GetRowAssoc(false);
				$fileName = $a['codigo_artigo'].'_'.$fileName;
			}
			// Stream the file to the end user.
			header("Content-Type: $mediaType");
			header('Content-Length: ' . filesize($filePath));
			header('Accept-Ranges: none');
			header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"$fileName\"");
			header('Cache-Control: private'); // Workarounds for IE weirdness
			header('Pragma: public');
			FileManager::readFileFromPath($filePath, true);
			$returner = true;
		} else {
			$returner = false;
		}
		HookRegistry::call('FileManager::downloadFileFinished', array(&$returner));
		return true;
	}
}
