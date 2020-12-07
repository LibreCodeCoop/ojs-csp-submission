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

use APP\Services\QueryBuilders\SubmissionQueryBuilder;
use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpClient\HttpClient;

import('lib.pkp.classes.plugins.GenericPlugin');
require_once(dirname(__FILE__) . '/vendor/autoload.php');

import('plugins.generic.cspSubmission.CspDeclinedSubmissions');

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
			HookRegistry::register('TemplateManager::display',array(&$this, 'templateManager_display'));
			HookRegistry::register('FileManager::downloadFile',array($this, 'fileManager_downloadFile'));
			HookRegistry::register('Mail::send', array($this,'mail_send'));
			HookRegistry::register('submissionfilesuploadform::display', array($this,'submissionfilesuploadform_display'));

			HookRegistry::register('Submission::getMany::queryObject', array($this,'submission_getMany_queryObject'));

			HookRegistry::register('APIHandler::endpoints', array($this,'APIHandler_endpoints'));

			// Hook for initData in two forms -- init the new field
			HookRegistry::register('submissionsubmitstep3form::initdata', array($this, 'metadataInitData'));

			// Hook for readUserVars in two forms -- consider the new field entry
			HookRegistry::register('submissionsubmitstep3form::readuservars', array($this, 'metadataReadUserVars'));			
			HookRegistry::register('authorform::readuservars', array($this, 'authorformReadUserVars'));
			

			// Hook for execute in forms -- consider the new field
			HookRegistry::register('submissionsubmitstep3form::execute', array($this, 'metadataExecuteStep3'));
			HookRegistry::register('submissionsubmitstep4form::execute', array($this, 'metadataExecuteStep4'));
			HookRegistry::register('authorform::execute', array($this, 'authorformExecute'));
				
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

			HookRegistry::register('userstageassignmentdao::_filterusersnotassignedtostageinusergroup', array($this, 'userstageassignmentdao_filterusersnotassignedtostageinusergroup'));

			HookRegistry::register('Template::Workflow::Publication', array($this, 'workflowFieldEdit'));

			HookRegistry::register('addparticipantform::execute', array($this, 'addparticipantformExecute'));

			HookRegistry::register('Publication::edit', array($this, 'publicationEdit'));

			// Hook para adicionar o campo comentário no upload de arquivos
			HookRegistry::register('submissionfilesmetadataform::readuservars', array($this, 'submissionFilesMetadataReadUserVars'));
			HookRegistry::register('submissionfiledaodelegate::getAdditionalFieldNames', array($this, 'submissionfiledaodelegateAdditionalFieldNames'));
			HookRegistry::register('submissionfilesmetadataform::execute', array($this, 'submissionFilesMetadataExecute'));

		}
		return $success;
	}

	public function countStatus($status, $date){

		$userDao = DAORegistry::getDAO('UserDAO');
		$result = $userDao->retrieve(
			<<<QUERY
			SELECT COUNT(*) AS CONTADOR FROM status_csp WHERE status = '$status' and date_status <= '$date'
			QUERY
		);
		$count = $result->GetRowAssoc(false);
		return $count["contador"];

	}

	/**
	 * Hooked to the the `display` callback in TemplateManager
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	public function templateManager_display($hookName, $args) {
		if ($args[1] == "submission/form/index.tpl") {

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
		} elseif ($args[1] == "dashboard/index.tpl") {
			$request = \Application::get()->getRequest();
			$currentUser = $request->getUser();
			$context = $request->getContext();
			$hasAccess = $currentUser->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_ASSISTANT, ROLE_ID_SITE_ADMIN), $context->getId());

			if ($hasAccess) {

				$templateManager =& $args[0];

				$containerData = $templateManager->get_template_vars('containerData');
				$stages[] = $containerData['components']['myQueue']['filters'][1]['filters'][0];
				$stages[] = [
					'param' => 'substage',
					'value' => 'pre_aguardando_secretaria',
					'title' => "--- Aguardando secretaria (" .$this->countStatus('pre_aguardando_secretaria',date('Y-m-d H:i:s')) .")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'pre_pendencia_tecnica',
					'title' => "--- Pendência técnica (" .$this->countStatus('pre_pendencia_tecnica',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'pre_aguardando_editor_chefe',
					'title' => "--- Aguardando editor chefe (" .$this->countStatus('pre_aguardando_editor_chefe',date('Y-m-d H:i:s')).")"
				];
				$stages[] = $containerData['components']['myQueue']['filters'][1]['filters'][1];
				$stages[] = [
					'param' => 'substage',
					'value' => 'ava_com_editor_associado',
					'title' => "--- Com o editor associado (" .$this->countStatus('ava_com_editor_associado',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'ava_aguardando_autor',
					'title' => "--- Aguardando autor (" .$this->countStatus('ava_aguardando_autor',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'ava_aguardando_autor_mais_60_dias',
					'title' => "--- Há mais de 60 dias com o autor (" .$this->countStatus('ava_aguardando_autor',date('Y-m-d H:i:s', strtotime('-2 months'))).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'ava_aguardando_secretaria',
					'title' => "--- Aguardando secretaria (" .$this->countStatus('ava_aguardando_secretaria',date('Y-m-d H:i:s')) .")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'ava_aguardando_editor_chefe',
					'title' => "--- Aguardando editor chefe (" .$this->countStatus('ava_aguardando_editor_chefe',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'ava_consulta_editor_chefe',
					'title' => "--- Consulta ao editor chefe (" .$this->countStatus('ava_consulta_editor_chefe',date('Y-m-d H:i:s')).")"
				];
				$stages[] = $containerData['components']['myQueue']['filters'][1]['filters'][2];
				$stages[] = [
					'param' => 'substage',
					'value' => 'ed_text_em_avaliacao_ilustracao',
					'title' => "--- Em avaliação de ilustração (" .$this->countStatus('ed_text_em_avaliacao_ilustracao',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'ed_text_envio_carta_aprovacao',
					'title' => "--- Envio de Carta de aprovação (" .$this->countStatus('ed_text_envio_carta_aprovacao',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'ed_text_para_revisao_traducao',
					'title' => "--- Para revisão/Tradução (" .$this->countStatus('ed_text_para_revisao_traducao',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'ed_text_em_revisao_traducao',
					'title' => "--- Em revisão/Tradução (" .$this->countStatus('ed_text_em_revisao_traducao',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'ed_texto_traducao_metadados',
					'title' => "--- Tradução de metadados (" .$this->countStatus('ed_texto_traducao_metadados',date('Y-m-d H:i:s')).")"
				];
				$stages[] = $containerData['components']['myQueue']['filters'][1]['filters'][3];
				$stages[] = [
					'param' => 'substage',
					'value' => 'edit_aguardando_padronizador',
					'title' => "--- Aguardando padronizador (" .$this->countStatus('edit_aguardando_padronizador',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'edit_em_formatacao_figura',
					'title' => "--- Em formatação de Figura (" .$this->countStatus('edit_em_formatacao_figura',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'edit_pdf_padronizado',
					'title' => "--- PDF padronizado (" .$this->countStatus('edit_pdf_padronizado',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'edit_em_prova_prelo',
					'title' => "--- Em prova de prelo (" .$this->countStatus('edit_em_prova_prelo',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 'edit_em_diagramacao',
					'title' => "--- Em diagramação (" .$this->countStatus('edit_em_diagramacao',date('Y-m-d H:i:s')).")"
				];
				$stages[] = [
					'param' => 'substage',
					'value' => 19,
					'title' => '--- Aguardando publicação'
				];
				$containerData['components']['myQueue']['filters'][1]['filters'] = $stages;
				$templateManager->assign('containerData', $containerData);
				$args[2] = $templateManager->fetch($this->getTemplateResource('index.tpl'));
				return true;
			}
		}

		return false;
	}

	public function submission_getMany_queryObject($hookName, $args) {
		/**
		 * @var SubmissionQueryBuilder
		 */
		$qb = $args[0];
		$request = \Application::get()->getRequest();
		$substage = $request->getUserVar('substage');

		if ($substage) {
			$substage = $substage[0];
			$queryStatusCsp = Capsule::table('status_csp');
			$queryStatusCsp->select(Capsule::raw('DISTINCT status_csp.submission_id'));
			$queryStatusCsp->where('status_csp.status', '=', $substage);
			if($substage == 'ava_aguardando_autor_mais_60_dias'){
				$queryStatusCsp->where('status_csp.date_status', '<=', date('Y-m-d H:i:s', strtotime('-2 months')));
			}
			$qb->whereIn('s.submission_id',$queryStatusCsp);
			$qb->where('s.status', '=', 1);
		}
	}

	/**
	 * Permit requests to the custom block grid handler
	 * @param $hookName string The name of the hook being invoked
	 * @param $args array The parameters to the invoked hook
	 */
	function setupGridHandler($hookName, $params) {
		$component =& $params[0];
		$request = \Application::get()->getRequest();
		if ($component == 'plugins.generic.CspSubmission.controllers.grid.AddAuthorHandler') {
			return true;
		}
		if ($component == 'grid.users.stageParticipant.stageParticipantGrid.SaveParticipantHandler') {
			if($request->getUserVar('accept')){

				$submissionId = $request->getUserVar('submissionId');
				$userGroupId = $request->getUserVar('userGroupId');
				$userId = $request->getUserVar('userIdSelected');

				$stageId = $request->getUserVar('stageId');
				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
				$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, $userGroupId);
				while ($user = $users->next()) {
					$userAssigned = $user;
				}
				if(!$userAssigned){
					$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
					$stageAssignment = $stageAssignmentDao->newDataObject();
					$stageAssignment->setSubmissionId($submissionId);
					$stageAssignment->setUserGroupId($userGroupId);
					$stageAssignment->setUserId($userId);
					$stageAssignment->setRecommendOnly(1);
					$stageAssignment->setCanChangeMetadata(1);
					$stageAssignmentDao->insertObject($stageAssignment);

					$submissionDAO = Application::getSubmissionDAO();
					$submission = $submissionDAO->getById($submissionId);

					$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
					$assignedUser = $userDao->getById($userId);
					$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
					$userGroup = $userGroupDao->getById($userGroupId);

					import('lib.pkp.classes.log.SubmissionLog');
					SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_ADD_PARTICIPANT, 'submission.event.participantAdded', array('name' => $assignedUser->getFullName(), 'username' => $assignedUser->getUsername(), 'userGroupName' => $userGroup->getLocalizedName()));
				}

				$context = $request->getContext();
				$request->redirect($context->getPath(), 'workflow', null, array('submissionId' => $submissionId, 'stageId' => $stageId));
				return true;
			}
		}

		if ($component == 'modals.editorDecision.EditorDecisionHandler') {
			if($request->getUserVar('decision') == 1){
				if($params[1] == "savePromote" or $params[1] == "savePromoteInReview"){
					$submissionId = $request->getUserVar('submissionId');
					$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */

					import('lib.pkp.classes.file.SubmissionFileManager');

					$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
					$submissionFiles = $submissionFileDao->getBySubmissionId($submissionId);
					foreach ($submissionFiles as $submissionFile) {
						$genreIds[] = $submissionFile->_data["genreId"];
					}

					if (in_array(10, $genreIds)) { // Se houverem figuras, revisores de figura são convidados a avaliar figura e estatus é alterado para "Em avaliação de ilustração"
						$userGroupRevisorFigura = 19;
						$result = $userDao->retrieve(
							<<<QUERY
							SELECT u.email, u.user_id
							FROM ojs.users u
							LEFT JOIN user_user_groups g
							ON u.user_id = g.user_id
							WHERE  g.user_group_id = $userGroupRevisorFigura
							QUERY
						);

						import('lib.pkp.classes.mail.MailTemplate');
						while (!$result->EOF) {
							$mail = new MailTemplate('COPYEDIT_REQUEST_PICTURE');
							$mail->addRecipient($result->GetRowAssoc(0)['email']);
							$mail->params["acceptLink"] = $request->_router->_indexUrl."/".$request->_router->_contextPaths[0]."/$$\$call$$$/grid/users/stage-participant/stage-participant-grid/save-participant/submission?submissionId=$submissionId&userGroupId=$userGroupRevisorFigura&userIdSelected=".$result->GetRowAssoc(0)['user_id']."&stageId=4&accept=1";
							if (!$mail->send()) {
								import('classes.notification.NotificationManager');
								$notificationMgr = new NotificationManager();
								$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
							}
							$result->MoveNext();
						}
						$now = date('Y-m-d H:i:s');
						$userDao->retrieve(
							<<<QUERY
							UPDATE status_csp SET status = 'ed_text_em_avaliacao_ilustracao', date_status = '$now' WHERE submission_id = $submissionId
							QUERY
						);
					}else{ // Se não, assitentes editoriais são notificados e status é alterado para "Envio de carta de aprovação"
						$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
						$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $request->getUserVar('stageId'), 24);
						import('lib.pkp.classes.mail.MailTemplate');
						$mail = new MailTemplate('EDITOR_DECISION_ACCEPT');
						while ($user = $users->next()) {
							$mail->addRecipient($user->getEmail(), $user->getFullName());
						}
						if (!$mail->send()) {
							import('classes.notification.NotificationManager');
							$notificationMgr = new NotificationManager();
							$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
						}

						$now = date('Y-m-d H:i:s');
						$userDao->retrieve(
							<<<QUERY
							UPDATE status_csp SET status = 'ed_text_envio_carta_aprovacao', date_status = '$now' WHERE submission_id = $submissionId
							QUERY
						);
					}
				}
			}
			if($request->getUserVar('decision') == 7){
				// Quando submissão é enviada para editoração, padronizadores recebem email com convite para assumir padronização
				if($params[1] == "savePromote"){
					$userGroupPadronizador = 20;
					$request = \Application::get()->getRequest();
					$submissionId = $request->getUserVar('submissionId');
					$userDao = DAORegistry::getDAO('UserDAO');
					$context = $request->getContext();
					$result = $userDao->retrieve(
						<<<QUERY
						SELECT u.email, u.user_id
						FROM ojs.users u
						LEFT JOIN user_user_groups g
						ON u.user_id = g.user_id
						WHERE  g.user_group_id = $userGroupPadronizador
						QUERY
					);

					import('lib.pkp.classes.mail.MailTemplate');
					while (!$result->EOF) {
						$mail = new MailTemplate('EDITORACAO_PADRONIZACAO');
						$mail->addRecipient($result->GetRowAssoc(0)['email']);
						$mail->params["acceptLink"] = $request->_router->_indexUrl."/".$request->_router->_contextPaths[0]."/$$\$call$$$/grid/users/stage-participant/stage-participant-grid/save-participant/submission?submissionId=$submissionId&userGroupId=$userGroupPadronizador&userIdSelected=".$result->GetRowAssoc(0)['user_id']."&stageId=5&accept=1";
						if (!$mail->send()) {
							import('classes.notification.NotificationManager');
							$notificationMgr = new NotificationManager();
							$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
						}
						$result->MoveNext();
					}
				}
			}
		}
		if ($component == 'api.file.ManageFileApiHandler') {
			$locale = AppLocale::getLocale();
			$submissionId = $request->getUserVar('submissionId');
			$request->_requestVars["name"][$locale] = "csp_".$submissionId."_".date("Y")."_".$request->_requestVars["name"][$locale];
		}
		return false;
	}

	function addparticipantformExecute($hookName, $args){
		$args[0]->_data["userGroupId"] = 1;
		$request = \Application::get()->getRequest();
		if($request->getUserVar("userGroupId") == 5){
			$userDao = DAORegistry::getDAO('UserDAO');
			$now = date('Y-m-d H:i:s');
			$submissionId = $request->getUserVar('submissionId');
			$userDao->retrieve(
				<<<QUERY
				UPDATE status_csp SET status = 'ava_com_editor_associado', date_status = '$now' WHERE submission_id = $submissionId
				QUERY
			);
		}
		if($request->getUserVar("userGroupId") == 7){ // Quando designa revisor/tradutor status é alterado para "Em revisão tradução"
			$userDao = DAORegistry::getDAO('UserDAO');
			$now = date('Y-m-d H:i:s');
			$submissionId = $request->getUserVar('submissionId');
			$userDao->retrieve(
				<<<QUERY
				UPDATE status_csp SET status = 'ed_text_em_revisao_traducao', date_status = '$now' WHERE submission_id = $submissionId
				QUERY
			);
		}
		if($request->getUserVar("userGroupId") == 22){ // Quando designa diagramador status é alterado para "Em diagramação"
			$userDao = DAORegistry::getDAO('UserDAO');
			$now = date('Y-m-d H:i:s');
			$submissionId = $request->getUserVar('submissionId');
			$userDao->retrieve(
				<<<QUERY
				UPDATE status_csp SET status = 'edit_em_diagramacao', date_status = '$now' WHERE submission_id = $submissionId
				QUERY
			);
		}
	}

	function mail_send($hookName, $args){
		//return;
		$request = \Application::get()->getRequest();
		$stageId = $request->getUserVar('stageId');
		$decision = $request->getUserVar('decision');
		$submissionId = $request->getUserVar('submissionId');
		$locale = AppLocale::getLocale();
		$userDao = DAORegistry::getDAO('UserDAO');

		if($stageId == 3){

			if($args[0]->emailKey == "EDITOR_DECISION_DECLINE"){
				(new CspDeclinedSubmissions())->saveDeclinedSubmission($submissionId, $args[0]);

				return true;
			}

			if($args[0]->emailKey == "REVISED_VERSION_NOTIFY"){ // Quando autor submete nova versão, secretaria é notificada

				unset($args[0]->_data["recipients"]);

				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
				$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, 23);

				while ($user = $users->next()) {
					$args[0]->_data["recipients"][] =  array("name" => $user->getFullName(), "email" => $user->getEmail());
				}
			}

			$recipient = $userDao->getUserByEmail($args[0]->_data["recipients"][0]["email"]);
			$context = $request->getContext();
			$isManager = $recipient->hasRole(array(ROLE_ID_MANAGER), $context->getId());
				if($isManager){ // Se o destinatário for editor chefe, status é alterado para "Consulta ao editor chefe"
					$now = date('Y-m-d H:i:s');
					$userDao->retrieve(
						<<<QUERY
						UPDATE status_csp SET status = 'ava_consulta_editor_chefe', date_status = '$now' WHERE submission_id = $submissionId
						QUERY
					);
				}

			if($decision == 2){  // Ao solicitar modificações ao autor, o status é alterado
				$now = date('Y-m-d H:i:s');
				$userDao->retrieve(
					<<<QUERY
					UPDATE status_csp SET status = 'ava_aguardando_autor', date_status = '$now' WHERE submission_id = $submissionId
					QUERY
				);
			}

			if($request->getUserVar('recommendation')){ // Quando editor associado faz recomendação, o status é alterado
				$now = date('Y-m-d H:i:s');
				$userDao->retrieve(
					<<<QUERY
					UPDATE status_csp SET status = 'ava_aguardando_editor_chefe', date_status = '$now' WHERE submission_id = $submissionId
					QUERY
				);
			}
		}

		if($request->_router->_page == "reviewer"){ 
			if($request->_requestVars["step"] == 1){
				return true;
			}
			if($request->_requestVars["step"] == 3){ // Editoras chefe não recebem email de notificação quando é submetida uma nova avaliaçao
				return true;
			}

		}

		if($stageId == 4 && strpos($args[0]->params["notificationContents"], "Artigo aprovado")){  // É enviado email de aprovação
			$now = date('Y-m-d H:i:s');
			$userDao->retrieve(
				<<<QUERY
				UPDATE status_csp SET status = 'ed_text_para_revisao_traducao', date_status = '$now' WHERE submission_id = $submissionId
				QUERY
			);
		}
		if($stageId == 5 && strpos($args[0]->params["notificationContents"], "Prova de prelo")){  // É enviado email de prova de prelo
			$now = date('Y-m-d H:i:s');
			$userDao->retrieve(
				<<<QUERY
				UPDATE status_csp SET status = 'edit_em_prova_prelo', date_status = '$now' WHERE submission_id = $submissionId
				QUERY
			);
		}
		$args[0]->_data["from"]["name"] = "Cadernos de Saúde Pública";
		$args[0]->_data["from"]["email"] = "noreply@fiocruz.br";
		$args[0]->_data["replyTo"][0]["name"] =  "Cadernos de Saúde Pública";
		$args[0]->_data["replyTo"][0]["email"] = "noreply@fiocruz.br";
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
		$submissionId = $request->getUserVar('submissionId');

		if ($args[1] == 'controllers/grid/users/reviewer/form/createReviewerForm.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('createReviewerForm.tpl'));

			return true;
		} elseif ($args[1] == 'submission/form/step3.tpl'){
			$args[4] = $templateMgr->fetch($this->getTemplateResource('step3.tpl'));

			return true;
		} elseif ($args[1] == 'user/identityForm.tpl'){
			$args[4] = $templateMgr->fetch($this->getTemplateResource('identityForm.tpl'));

			return true;
		} elseif ($args[1] == 'controllers/grid/grid.tpl'){
			$args[4] = $templateMgr->fetch($this->getTemplateResource('grid.tpl'));
			
			return true;
		} elseif ($args[1] == 'controllers/grid/gridCell.tpl'){
			if($request->_requestPath == "/ojs/index.php/csp/$$\$call\$$$/grid/files/submission/editor-submission-details-files-grid/fetch-grid" 
			OR $request->_requestPath == "/ojs/index.php/csp/$$\$call\$$$/grid/files/final/final-draft-files-grid/fetch-grid" 
			OR $request->_requestPath == "/ojs/index.php/csp/$$\$call\$$$/grid/files/review/editor-review-files-grid/fetch-grid"
			OR $request->_requestPath == "/ojs/index.php/csp/$$\$call$\$$/grid/files/production-ready/production-ready-files-grid/fetch-grid"){ //Busca comentários somente quando grid for de arquivos
				$row = $templateMgr->getVariable('row');
				if($row->value->_data["submissionFile"]->_data["comentario"]){
					$templateMgr->assign('comentario', $row->value->_data["submissionFile"]->_data["comentario"]);
				}
			}

			$args[4] = $templateMgr->fetch($this->getTemplateResource('gridCell.tpl'));
			return true;
		} elseif ($args[1] == 'controllers/wizard/fileUpload/form/fileUploadConfirmationForm.tpl'){
			$args[4] = $templateMgr->fetch($this->getTemplateResource('fileUploadConfirmationForm.tpl'));

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
			$templateMgr->assign(array(
				'reviewerRecommendationOptions' =>	array(
															'' => 'common.chooseOne',
															SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT => 'reviewer.article.decision.accept',
															SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS => 'reviewer.article.decision.pendingRevisions',
															SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE => 'reviewer.article.decision.decline',
														)
			));

			$args[4] = $templateMgr->fetch($this->getTemplateResource('reviewStep3.tpl'));
			return true;
		}elseif ($args[1] == 'reviewer/review/reviewCompleted.tpl') { // Ao terminar avaliação, avaliador recebe email de agradecimento
			$request = \Application::get()->getRequest();
			$currentUser = $request->getUser();

			import('lib.pkp.classes.mail.MailTemplate');
			$mail = new MailTemplate('REVIEW_THANK');
			$mail->addRecipient($currentUser->getEmail(), $currentUser->getFullName());

			if (!$mail->send()) {
				import('classes.notification.NotificationManager');
				$notificationMgr = new NotificationManager();
				$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
			}

			$args[4] = $templateMgr->fetch($this->getTemplateResource('reviewCompleted.tpl'));

			return true;
		}elseif ($args[1] == 'controllers/grid/users/stageParticipant/addParticipantForm.tpl') {

			if($stageId == 3 OR $stageId == 1){

				$mail = new MailTemplate('EDITOR_ASSIGN');
				$templateSubject['EDITOR_ASSIGN'] = $mail->_data["subject"];
				$templateBody['EDITOR_ASSIGN'] = $mail->_data["body"];

			}
			if($stageId == 4){

				$mail = new MailTemplate('COPYEDIT_REQUEST');
				$templateSubject['COPYEDIT_REQUEST'] = $mail->_data["subject"];
				$templateBody['COPYEDIT_REQUEST'] = $mail->_data["body"];
			}

			if($stageId == 5){
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
					WHERE t.enabled = 1 AND t.email_key LIKE 'LAYOUT%'
					QUERY
				);
				$i = 0;
				while (!$result->EOF) {
					$i++;
					$templateSubject[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['subject'];
					$templateBody[$result->GetRowAssoc(0)['email_key']] = $result->GetRowAssoc(0)['body'];

					$result->MoveNext();
				}
			}

			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'templates' => $templateSubject,
				'message' => json_encode($templateBody),
				'comment' => reset($templateBody)
			));

			$args[4] = $templateMgr->fetch($this->getTemplateResource('addParticipantForm.tpl'));

			return true;

		}elseif ($args[1] == 'controllers/modals/editorDecision/form/promoteForm.tpl') {
			$decision = $request->_requestVars["decision"];
			if ($stageId == 3 or $stageId == 1){
				if($decision == 1){ // Quando submissão é aceita, editores assistentes são designados

						$request = \Application::get()->getRequest();
						$submissionId = $request->getUserVar('submissionId');

						$userDao = DAORegistry::getDAO('UserDAO');
						$result = $userDao->retrieve(
							<<<QUERY
							SELECT s.user_group_id , g.user_id, a.user_id as assigned
							FROM ojs.user_user_groups g
							LEFT JOIN ojs.user_group_settings s
							ON s.user_group_id = g.user_group_id
							LEFT JOIN ojs.stage_assignments a
							ON g.user_id = a.user_id AND a.submission_id = $submissionId
							WHERE s.setting_value = 'Assistente editorial'
							QUERY
						);
						while (!$result->EOF) {

							if($result->GetRowAssoc(0)['assigned'] == NULL){

								$userGroupId = $result->GetRowAssoc(0)['user_group_id'];
								$userId = $result->GetRowAssoc(0)['user_id'];

								$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
								$stageAssignment = $stageAssignmentDao->newDataObject();
								$stageAssignment->setSubmissionId($submissionId);
								$stageAssignment->setUserGroupId($userGroupId);
								$stageAssignment->setUserId($userId);
								$stageAssignment->setRecommendOnly(1);
								$stageAssignment->setCanChangeMetadata(1);
								$stageAssignmentDao->insertObject($stageAssignment);

								$submissionDAO = Application::getSubmissionDAO();
								$submission = $submissionDAO->getById($submissionId);

								$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
								$assignedUser = $userDao->getById($userId);
								$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
								$userGroup = $userGroupDao->getById($userGroupId);

								import('lib.pkp.classes.log.SubmissionLog');
								SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_ADD_PARTICIPANT, 'submission.event.participantAdded', array('name' => $assignedUser->getFullName(), 'username' => $assignedUser->getUsername(), 'userGroupName' => $userGroup->getLocalizedName()));

							}

							$result->MoveNext();
						}
				}

				$args[4] = $templateMgr->fetch($this->getTemplateResource('promoteFormStage1And3.tpl'));

				return true;

			}elseif ($stageId == 4){ // Quando submissão é enviada para editoração, o status é alterado para "Aguardando padronizador"
				$templateMgr->assign('skipEmail',1); // Passa variável para não enviar email para o autor

				$userDao = DAORegistry::getDAO('UserDAO');
				$now = date('Y-m-d H:i:s');
				$userDao->retrieve(
					<<<QUERY
					UPDATE status_csp SET status = 'edit_aguardando_padronizador', date_status = '$now' WHERE submission_id = $submissionId
					QUERY
				);

				$args[4] = $templateMgr->fetch($this->getTemplateResource('promoteFormStage4.tpl'));

				return true;
			}

		}elseif ($args[1] == 'controllers/grid/queries/form/queryForm.tpl' && $stageId == "1") {

			$mail = new MailTemplate('PRE_AVALIACAO');
			$templateSubject['PRE_AVALIACAO'] = $mail->_data["subject"];
			$templateBody['PRE_AVALIACAO'] = $mail->_data["body"];

			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'templates' => $templateSubject,
				'stageId' => $stageId,
				'submissionId' => $submissionId,
				'message' => json_encode($templateBody),
				'comment' => reset($templateBody)
			));

			$args[4] = $templateMgr->fetch($this->getTemplateResource('queryForm.tpl'));

			return true;

		}elseif ($args[1] == 'controllers/grid/queries/form/queryForm.tpl' && $stageId == "4") {

			$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
			$manager = $userGroupDao->getUserGroupIdsByRoleId(ROLE_ID_MANAGER);
			$assistent = $userGroupDao->getUserGroupIdsByRoleId(ROLE_ID_ASSISTANT);
			$stageAssignmentsFactory = $stageAssignmentDao->getBySubmissionAndStageId($request->getUserVar('submissionId'), null, null, $_SESSION["userId"]);

			while ($stageAssignment = $stageAssignmentsFactory->next()) {
				if (in_array($stageAssignment->getUserGroupId(), $manager)) {
					$isManager = true;
				}
				if (in_array($stageAssignment->getUserGroupId(), $assistent)) {
					$isAssistent = true;
				}
			}

			if($isManager){
				$mail = new MailTemplate('EDICAO_TEXTO_APROVD');
				$templateSubject['EDICAO_TEXTO_APROVD'] = $mail->_data["subject"];
				$templateBody['EDICAO_TEXTO_APROVD'] = $mail->_data["body"];
			}elseif($isAssistent){
				$mail1 = new MailTemplate('EDICAO_TEXTO_FIG_APROVD');
				$templateSubject['EDICAO_TEXTO_FIG_APROVD'] = $mail1->_data["subject"];
				$templateBody['EDICAO_TEXTO_FIG_APROVD'] = $mail1->_data["body"];
				$mail2 = new MailTemplate('EDICAO_TEXTO_PENDENC_TEC');
				$templateSubject['EDICAO_TEXTO_PENDENC_TEC'] = $mail2->_data["subject"];
				$templateBody['EDICAO_TEXTO_PENDENC_TEC'] = $mail2->_data["body"];
			}else{
				return;
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
		}elseif ($args[1] == 'controllers/grid/queries/form/queryForm.tpl' && $stageId == "5") {

			$mail = new MailTemplate('EDITORACAO_PROVA_PRELO');
			$templateSubject['EDITORACAO_PROVA_PRELO'] = $mail->_data["subject"];
			$templateBody['EDITORACAO_PROVA_PRELO'] = $mail->_data["body"];

			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'templates' => $templateSubject,
				'stageId' => $stageId,
				'submissionId' => $this->_submissionId,
				'message' => json_encode($templateBody),
				'comment' => reset($templateBody)
			));

			$args[4] = $templateMgr->fetch($this->getTemplateResource('queryForm.tpl'));

			return true;

		}elseif ($args[1] == 'controllers/wizard/fileUpload/form/fileUploadForm.tpl') {

			$args[4] = $templateMgr->fetch($this->getTemplateResource('fileUploadForm.tpl'));

			return true;

		} elseif ($args[1] == 'controllers/wizard/fileUpload/form/submissionFileMetadataForm.tpl'){
			$tplvars = $templateMgr->getFBV();
			$locale = AppLocale::getLocale();

			$genreId = $tplvars->_form->_submissionFile->_data["genreId"];
			if($genreId == 47){ // SEM PRE-DEFINIÇÃO DE GÊNERO

				$tplvars->_form->_submissionFile->_data["name"][$locale] = "csp_".$request->_requestVars["submissionId"]."_".date("Y")."_".$tplvars->_form->_submissionFile->_data["originalFileName"];

			}else{

				$userDao = DAORegistry::getDAO('UserDAO');
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT setting_value
					FROM ojs.genre_settings
					WHERE genre_id = $genreId AND locale = '$locale'
					QUERY
				);
				$genreName = $result->GetRowAssoc(false)['setting_value'];
				$genreName = str_replace(" ","_",$genreName);

				$extensao = pathinfo($tplvars->_form->_submissionFile->_data["originalFileName"], PATHINFO_EXTENSION);

				$tplvars->_form->_submissionFile->_data["name"][$locale] = $genreName.".".$extensao;

			}

			$currentUser = $request->getUser();
			$context = $request->getContext();
			$hasAccess = $currentUser->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_ASSISTANT, ROLE_ID_SITE_ADMIN), $context->getId());

			if($hasAccess){
				$templateMgr->assign('display', true);
			}

			$args[4] = $templateMgr->fetch($this->getTemplateResource('submissionFileMetadataForm.tpl'));

			return true;

		} elseif ($args[1] == 'controllers/grid/users/reviewer/readReview.tpl'){
			$args[4] = $templateMgr->fetch($this->getTemplateResource('readReview.tpl'));

			return true;

		} elseif ($args[1] == 'controllers/modals/editorDecision/form/recommendationForm.tpl'){
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'skipEmail' => true,
				'recommendationOptions' =>	array(
												'' => 'common.chooseOne',
												SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS => 'editor.submission.decision.requestRevisions',
												SUBMISSION_EDITOR_RECOMMEND_ACCEPT => 'editor.submission.decision.accept',
												SUBMISSION_EDITOR_RECOMMEND_DECLINE => 'editor.submission.decision.decline',
											)
			));

			$args[4] = $templateMgr->fetch($this->getTemplateResource('recommendationForm.tpl'));
			return true;

		} elseif ($args[1] == 'controllers/grid/gridRow.tpl' && $request->_requestPath == '/ojs/index.php/csp/$$$call$$$/grid/users/user-select/user-select-grid/fetch-grid'){
			$templateMgr = TemplateManager::getManager($request);
			$columns = $templateMgr->getVariable('columns');
			$cells = $templateMgr->getVariable('cells');
			$row = $templateMgr->getVariable('row');
			$cells->value[] = $row->value->_data->_data["assigns"];
			$columns->value['assigns'] = clone $columns->value["name"];
			$columns->value["assigns"]->_title = "author.users.contributor.assign";

		}
		return false;
	}

	public function submissionfilesuploadform_display($hookName, $args)
	{
		/** @var Request */
		$request = \Application::get()->getRequest();
		$fileStage = $request->getUserVar('fileStage');
		$submissionDAO = Application::getSubmissionDAO();
		$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
		$submissionProgress = $submission->getData('submissionProgress');
		$stageId = $request->getUserVar('stageId');
		$userId = $_SESSION["userId"];
		$locale = AppLocale::getLocale();
		$userDao = DAORegistry::getDAO('UserDAO');

		$templateMgr =& $args[0];

		if ($fileStage == 2 && $submissionProgress == 0){

			$result = $userDao->retrieve(
				<<<QUERY
				SELECT A.genre_id, setting_value
				FROM ojs.genre_settings A
				LEFT JOIN ojs.genres B
				ON B.genre_id = A.genre_id
				WHERE locale = '$locale' AND entry_key = 'SUBMISSAO_PDF'
				QUERY
			);
			while (!$result->EOF) {
				$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

				$result->MoveNext();
			}

			$templateMgr->setData('submissionFileGenres', $genreList);
			$templateMgr->setData('isReviewAttachment', false); // SETA A VARIÁVEL PARA FALSE POIS ELA É VERIFICADA NO TEMPLATE PARA EXIBIR OS COMPONENTES
		}

		if ($fileStage == 4) { // SECRETARIA FAZENDO UPLOAD DE NOVA VERSÃO

			$result = $userDao->retrieve(
				<<<QUERY
				SELECT A.genre_id, setting_value
				FROM ojs.genre_settings A
				LEFT JOIN ojs.genres B
				ON B.genre_id = A.genre_id
				WHERE locale = '$locale' AND entry_key LIKE 'AVAL_SECRETARIA%'
				QUERY
			);
			while (!$result->EOF) {
				$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

				$result->MoveNext();
			}

			$templateMgr->setData('submissionFileGenres', $genreList);
			$templateMgr->setData('isReviewAttachment', false); // SETA A VARIÁVEL PARA FALSE POIS ELA É VERIFICADA NO TEMPLATE PARA EXIBIR OS COMPONENTES

		}

		if ($fileStage == 5) { // AVALIADOR FAZENDO UPLOAD DE PARECER

			$result = $userDao->retrieve(
				<<<QUERY
				SELECT A.genre_id, setting_value
				FROM ojs.genre_settings A
				LEFT JOIN ojs.genres B
				ON B.genre_id = A.genre_id
				WHERE locale = '$locale' AND entry_key LIKE 'AVAL_AVALIADOR%'
				QUERY
			);
			while (!$result->EOF) {
				$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

				$result->MoveNext();
			}

			$templateMgr->setData('submissionFileGenres', $genreList);
			$templateMgr->setData('isReviewAttachment', false); // SETA A VARIÁVEL PARA FALSE POIS ELA É VERIFICADA NO TEMPLATE PARA EXIBIR OS COMPONENTES

		}
		if ($fileStage == 6) {

			$result = $userDao->retrieve( // PEGA O PERFIL
				<<<QUERY
				SELECT g.user_group_id
				FROM ojs.user_user_groups g
				WHERE user_id = $userId
				QUERY
			);

			while (!$result->EOF) {
				if($result->GetRowAssoc(0)['user_group_id'] == 19){ // PERFIL REVISOR DE FIGURA
					$result_genre = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'EDICAO_TEXTO_FIG_ALT%'
						QUERY
					);
				break;
				}

				$result->MoveNext();
			}

			if(isset($result_genre)){

				while (!$result_genre->EOF) {
					$genreList[$result_genre->GetRowAssoc(0)['genre_id']] = $result_genre->GetRowAssoc(0)['setting_value'];

					$result_genre->MoveNext();
				}

				$templateMgr->setData('submissionFileGenres', $genreList);

			}else{
				$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
			}			

		}
		if ($fileStage == 9) { // UPLOAD DE ARQUIVO EM BOX DE ARQUIVOS DE REVISÃO DE TEXTO

			$result = $userDao->retrieve( // VERIFICA SE O PERFIL É DE REVISOR/TRADUTOR
				<<<QUERY
				SELECT g.user_group_id , g.user_id
				FROM ojs.user_user_groups g
				WHERE g.user_group_id = 7 AND user_id = $userId
				QUERY
			);

			if($result->_numOfRows == 0){
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT A.genre_id, setting_value
					FROM ojs.genre_settings A
					LEFT JOIN ojs.genres B
					ON B.genre_id = A.genre_id
					WHERE locale = '$locale' AND entry_key LIKE 'EDICAO_ASSIST_ED%'
					QUERY
				);
			}else{
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT A.genre_id, setting_value
					FROM ojs.genre_settings A
					LEFT JOIN ojs.genres B
					ON B.genre_id = A.genre_id
					WHERE locale = '$locale' AND entry_key LIKE 'EDICAO_TRADUT%'
					QUERY
				);
			}

			while (!$result->EOF) {
				$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

				$result->MoveNext();
			}

			$templateMgr->setData('submissionFileGenres', $genreList);

		}
		if ($fileStage == 10) { // UPLOAD DE PDF PARA PUBLICAÇÃO
			$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
		}
		if ($fileStage == 11) { // UPLOAD DE ARQUIVO EM BOX DE ARQUIVOS PRONTOS PARA LAYOUT

			$result = $userDao->retrieve( // CONSULTA O PERFIL
				<<<QUERY
				SELECT g.user_group_id
				FROM ojs.user_user_groups g
				WHERE user_id = $userId
				QUERY
			);

			while (!$result->EOF) {
				if($result->GetRowAssoc(0)['user_group_id'] == 24){ // ASSISTENTE EDITORIAL
					$result_genre = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND (entry_key LIKE 'EDITORACAO_ASSIS_ED_TEMPLT%' OR entry_key LIKE 'EDITORACAO_FIG_P_FORMATAR%')
						QUERY
					);
				break;
				}elseif($result->GetRowAssoc(0)['user_group_id'] == 21){ // EDITOR DE FIGURA
					$result_genre = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'EDITORACAO_FIG_FORMATA%'
						QUERY
					);
				break;
				}

				$result->MoveNext();
			}

			if(isset($result_genre)){

				while (!$result_genre->EOF) {
					$genreList[$result_genre->GetRowAssoc(0)['genre_id']] = $result_genre->GetRowAssoc(0)['setting_value'];

					$result_genre->MoveNext();
				}

				$templateMgr->setData('submissionFileGenres', $genreList);

			}else{
				$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
			}
		}

		if ($fileStage == 15) { // UPLOAD NOVA VERSÃO

			$result = $userDao->retrieve( // BUSCAR PERFIL DO USUÁRIO
				<<<QUERY
				SELECT g.user_group_id
				FROM ojs.user_user_groups g
				WHERE user_id = $userId
				QUERY
			);

			while (!$result->EOF) {
				if($result->GetRowAssoc(0)['user_group_id'] == 23){ // SECRETARIA
					$result_genre = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'AVAL_SECRETARIA_NOVA_VERSAO%'
						QUERY
					);
				break;
				}elseif($result->GetRowAssoc(0)['user_group_id'] == 14){ // AUTOR
					$result_genre = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'AVAL_AUTOR%'
						QUERY
					);
				break;
				}

				$result->MoveNext();
			}

			if(isset($result_genre)){

				while (!$result_genre->EOF) {
					$genreList[$result_genre->GetRowAssoc(0)['genre_id']] = $result_genre->GetRowAssoc(0)['setting_value'];

					$result_genre->MoveNext();
				}

				$templateMgr->setData('submissionFileGenres', $genreList);
				$templateMgr->setData('alert', 'É obrigatória a submissão de uma carta ao editor associado escolhendo o componete "Alterações realizadas"');

			}else{
				$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
			}

		}

		if ($fileStage == 17) { // ARQUIVOS DEPENDENTES EM PUBLICAÇÃO
			$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES

		}
		if ($fileStage == 18) {  // UPLOADS NO BOX DISCUSSÃO

			if($stageId == 5){

				$autor = $userDao->retrieve( // VERIFICA SE O PERFIL É DE AUTOR PARA EXIBIR SOMENTE OS COMPONENTES DO PERFIL
					<<<QUERY
					SELECT g.user_group_id , g.user_id
					FROM ojs.user_user_groups g
					WHERE g.user_group_id = 14 AND user_id = $userId
					QUERY
				);

				$editor_assistente = $userDao->retrieve( // VERIFICA SE O PERFIL É DE ASSISTENTE EDITORIAL PARA EXIBIR SOMENTE OS COMPONENTES DO PERFIL
					<<<QUERY
					SELECT g.user_group_id , g.user_id
					FROM ojs.user_user_groups g
					WHERE g.user_group_id = 24 AND user_id = $userId
					QUERY
				);

				if($autor->_numOfRows > 0){
					$result = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'EDITORACAO_AUTOR%'
						QUERY
					);

					while (!$result->EOF) {
						$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

						$result->MoveNext();
					}

					$templateMgr->setData('submissionFileGenres', $genreList);
				}elseif($editor_assistente->_numOfRows > 0) {
					$result = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'EDITORACAO_ASSIST_ED%'
						QUERY
					);

					while (!$result->EOF) {
						$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

						$result->MoveNext();
					}

					$templateMgr->setData('submissionFileGenres', $genreList);
				}else{
					$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
				}

			}elseif($stageId == 4){
				// Buscar componentes de arquivos específicos para o autor
				$submissionId = $request->getUserVar('submissionId');
				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
				$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, 14);

				while ($user = $users->next()) {
					if($user->getData('id') == $_SESSION["userId"]){
						$isAuthor = true;
					}
				}

				if($isAuthor){
					$result = $userDao->retrieve(
						<<<QUERY
						SELECT A.genre_id, setting_value
						FROM ojs.genre_settings A
						LEFT JOIN ojs.genres B
						ON B.genre_id = A.genre_id
						WHERE locale = '$locale' AND entry_key LIKE 'PEND_TEC_%'
						QUERY
					);
					while (!$result->EOF) {
						$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];

						$result->MoveNext();
					}

					$templateMgr->setData('submissionFileGenres', $genreList);
				}else{
					$templateMgr->setData('isReviewAttachment', TRUE); // Atribui TRUE para variável utilizada para não exibir os componentes
				}

			}else{
				$templateMgr->setData('isReviewAttachment', TRUE); // Atribui TRUE para variável utilizada para não exibir os componentes
			}

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
		}elseif(isset($row['assigns'])){
			$user->setData('assigns', $row['assigns']);
		}
	}

	function userstageassignmentdao_filterusersnotassignedtostageinusergroup($hookName, $args){
		$args[0] = <<<QUERY
					SELECT q1.*, COALESCE(q2.assigns,0) AS assigns FROM ({$args[0]}) q1
					LEFT JOIN (SELECT COUNT(*) AS assigns, user_id
					FROM ojs.stage_assignments a
					JOIN ojs.submissions s
					ON s.submission_id = a.submission_id AND s.stage_id <= 3
					WHERE a.user_group_id = ?
					GROUP BY a.user_id) q2
					ON q1.user_id = q2.user_id
					QUERY;
		$args[1][] = $args[1][10];


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
		$form = $args[0];
		if ($type == 'csp') {
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
		}elseif($form->_author != null){
			$form->setTemplate($this->getTemplateResource('authorFormAdd.tpl'));
			$author = $form->_author;
			$form->setData('authorContribution', $author->getData('authorContribution'));
			$form->setData('affiliation2', $author->getData('affiliation2'));
		}else{
			return;
		}
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


	function workflowFieldEdit($hookName, $params) {
		$smarty =& $params[1];
		$output =& $params[2];
		$output .= $smarty->fetch($this->getTemplateResource('ExclusaoPrefixo.tpl'));
		return false;
	}

	/**
	 * Insert Campo1 field into author submission step 3 and metadata edit form
	 */
	function metadataFieldEdit($hookName, $params) {

		$submissionDAO = Application::getSubmissionDAO();
		$request = \Application::get()->getRequest();
		/** @val Submission */
		$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
		$publication = $submission->getCurrentPublication();
		$sectionId = $publication->getData('sectionId');

		$smarty =& $params[1];
		$output =& $params[2];

		if($sectionId == 5){
			$output .= $smarty->fetch($this->getTemplateResource('Revisao.tpl'));
		}

		if($sectionId == 4){
			$output .= $smarty->fetch($this->getTemplateResource('tema.tpl'));
			$output .= $smarty->fetch($this->getTemplateResource('codigoTematico.tpl'));
		}

		$output .= $smarty->fetch($this->getTemplateResource('conflitoInteresse.tpl'));
		$output .= $smarty->fetch($this->getTemplateResource('agradecimentos.tpl'));

		if($sectionId == 6){
			$output .= $smarty->fetch($this->getTemplateResource('codigoArtigoRelacionado.tpl'));
		}

		$output .= $smarty->fetch($this->getTemplateResource('InclusaoAutores.tpl'));


		return false;
	}

	function metadataReadUserVars($hookName, $params) {
		$userVars =& $params[1];
		$userVars[] = 'conflitoInteresse';
		$userVars[] = 'agradecimentos';
		$userVars[] = 'codigoTematico';
		$userVars[] = 'tema';
		$userVars[] = 'codigoArtigoRelacionado';
		$userVars[] = 'CodigoArtigo';

		return false;
	}

	function authorformReadUserVars($hookName, $params) {
		$userVars =& $params[1];
		$userVars[] = 'authorContribution';
		$userVars[] = 'affiliation2';

		return false;
	}

	function submissionFilesMetadataReadUserVars($hookName, $params) {
		$userVars =& $params[1];
		$userVars[] = 'comentario';

		return false;
	}

	function submissionfiledaodelegateAdditionalFieldNames($hookName, $params) {
		$additionalFieldNames =& $params[1];
		$additionalFieldNames[] = 'comentario';

		return false;
	}

	function submissionFilesMetadataExecute($hookName, $params) {
		$form =& $params[0];
		$submissionFile = $form->_submissionFile;
		$submissionFile->setData('comentario', $form->getData('comentario'));

		return false;
	}

	function metadataExecuteStep3($hookName, $params) {
		$form =& $params[0];
		$article = $form->submission;
		$article->setData('conflitoInteresse', $form->getData('conflitoInteresse'));
		$article->setData('agradecimentos', $form->getData('agradecimentos'));
		$article->setData('codigoTematico', $form->getData('codigoTematico'));
		$article->setData('tema', $form->getData('tema'));
		$article->setData('codigoArtigoRelacionado', $form->getData('codigoArtigoRelacionado'));

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

		$now = date('Y-m-d H:i:s');
		$submissionId = $article->getData('id');
		$userDao->retrieve(
			<<<QUERY
			INSERT INTO status_csp (submission_id, status, date_status) VALUES ($submissionId,'pre_aguardando_secretaria','$now')
			QUERY
		);


		return false;
	}

	function authorformExecute($hookName, $params) {
		$form =& $params[0];
		$author = $form->_author;
		$author->setData('authorContribution', $form->getData('authorContribution'));
		$author->setData('affiliation2', $form->getData('affiliation2'));

		return false;
	}

	/**
	 * Init article Campo1
	 */
	function metadataInitData($hookName, $params) {
		$form =& $params[0];
		$article = $form->submission;
		$this->sectionId = $article->getData('sectionId');
		$form->setData('agradecimentos', $article->getData('agradecimentos'));
		$form->setData('codigoTematico', $article->getData('codigoTematico'));
		$form->setData('codigoArtigoRelacionado', $article->getData('codigoArtigoRelacionado'));
		$form->setData('conflitoInteresse', $article->getData('conflitoInteresse'));
		$form->setData('tema', $article->getData('tema'));

		return false;
	}

	function publicationEdit($hookName, $params) {
		$params[0]->setData('agradecimentos', $params[3]->_requestVars["agradecimentos"]);
		$params[1]->setData('agradecimentos', $params[3]->_requestVars["agradecimentos"]);
		$params[2]["agradecimentos"] = $params[3]->_requestVars["agradecimentos"];
		$params[0]->setData('codigoTematico', $params[3]->_requestVars["codigoTematico"]);
		$params[1]->setData('codigoTematico', $params[3]->_requestVars["codigoTematico"]);
		$params[2]["codigoTematico"] = $params[3]->_requestVars["codigoTematico"];
		$params[0]->setData('codigoArtigoRelacionado', $params[3]->_requestVars["codigoArtigoRelacionado"]);
		$params[1]->setData('codigoArtigoRelacionado', $params[3]->_requestVars["codigoArtigoRelacionado"]);
		$params[2]["codigoArtigoRelacionado"] = $params[3]->_requestVars["codigoArtigoRelacionado"];
		$params[0]->setData('conflitoInteresse', $params[3]->_requestVars["conflitoInteresse"]);
		$params[1]->setData('conflitoInteresse', $params[3]->_requestVars["conflitoInteresse"]);
		$params[2]["conflitoInteresse"] = $params[3]->_requestVars["conflitoInteresse"];
		$params[0]->setData('tema', $params[3]->_requestVars["tema"]);
		$params[1]->setData('tema', $params[3]->_requestVars["tema"]);
		$params[2]["tema"] = $params[3]->_requestVars["tema"];

		return false;
	}
	/**
	 * Add check/validation for the Campo1 field (= 6 numbers)
	 */
	function addCheck($hookName, $params) {
		$form =& $params[0];

		if($this->sectionId == 4){
			$form->addCheck(new FormValidatorLength($form, 'codigoTematico', 'required', 'plugins.generic.CspSubmission.codigoTematico.Valid', '>', 0));
			$form->addCheck(new FormValidatorLength($form, 'tema', 'required', 'plugins.generic.CspSubmission.Tema.Valid', '>', 0));
		}

		if($this->sectionId == 6){
			$form->addCheck(new FormValidatorLength($form, 'codigoArtigoRelacionado', 'required', 'plugins.generic.CspSubmission.codigoArtigoRelacionado.Valid', '>', 0));
		}

		$form->addCheck(new FormValidatorCustom($form, 'source', 'optional', 'plugins.generic.CspSubmission.doi.Valid', function($doi) {
			if (!filter_var($doi, FILTER_VALIDATE_URL)) {
				if (strpos(reset($doi), 'doi.org') === false){
					$doi = 'http://dx.doi.org/'.reset($doi);
				} elseif (strpos(reset($doi),'http') === false) {
					$doi = 'http://'.reset($doi);
				} else {
					return false;
				}
			}

			$client = HttpClient::create();
			$response = $client->request('GET', $doi);
			$statusCode = $response->getStatusCode();
			return in_array($statusCode,[303,200]);
		}));

		return false;
	}

	public function submissionfilesuploadformValidate($hookName, $args) {
		// Retorna o tipo do arquivo enviado
		$genreId = $args[0]->getData('genreId');
		$args[0]->_data["fileStage"];
		switch($genreId) {
			case 1: // Corpo do artigo
			case 13: // Tabela ou quadro
			case 19: // Nova versão corpo
			case 20: // Nova versão tabela ou quadro
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

				$formato = explode('.', $_FILES['uploadedFile']['name']);
				$formato = trim(strtolower(end($formato)));

				$readers = array('docx' => 'Word2007', 'odt' => 'ODText', 'rtf' => 'RTF', 'doc' => 'ODText');
				$doc = \PhpOffice\PhpWord\IOFactory::load($_FILES['uploadedFile']['tmp_name'], $readers[$formato]);
				$html = new PhpOffice\PhpWord\Writer\HTML($doc);
				$contagemPalavras = str_word_count(strip_tags($html->getWriterPart('Body')->write()));

				switch($sectionId) {
					case 1: // Artigo
					case 4: // Debate
					case 8: //  Questões Metodológicas
					case 10: // Entrevista
						if ($contagemPalavras > 6000) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 6000,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 2: // Editorial
					case 9: // Comunicação breve
						if ($contagemPalavras > 2000) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 2000,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 3: // Perspectivas
						if ($contagemPalavras > 2200) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 2200,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 6: // Revisão
					case 7: // Ensaio
						if ($contagemPalavras > 8000) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 8000,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 5: // Espaço Temático
						if ($contagemPalavras > 4000) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 4000,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 11: // Carta
					case 15: // Comentários
						if ($contagemPalavras > 1300) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 1300,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 12: // Resenhas
						if ($contagemPalavras > 1300) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 1300,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 13: // Obtuário
						if ($contagemPalavras > 1000) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 1000,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
					case 14: // Errata
						if ($contagemPalavras > 700) {
							$phrase = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
								'sectoin' => $section->getTitle($publication->getData('locale')),
								'max'     => 700,
								'count'   => $contagemPalavras
							]);
							$args[0]->addError('genreId', $phrase);
						}
					break;
				}
			break;
			case 10: // Figura
			case 22: // Nova versão Figura
				if (!in_array($_FILES['uploadedFile']['type'], ['image/bmp', 'image/tiff', 'image/png', 'image/jpeg'])) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.Image')
					);
				}
			break;
			case '46': 	// PDF para avaliação
			case '30': 	// Nova versão PDF
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$userDao = DAORegistry::getDAO('UserDAO');

				if (($_FILES['uploadedFile']['type'] <> 'application/pdf')/*PDF*/) {
					$args[0]->addError('typeId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.PDF')
					);
				}else{
					if($genreId == '46'){ // QUANDO SECRETARIA SOBRE UM PDF NO ESTÁGIO DE SUBMISSÃO, A SUBMISSÃO É DESIGNADA PARA TODOS OS EDITORES DA REVISTA

						$result = $userDao->retrieve(
							<<<QUERY
							SELECT s.user_group_id , g.user_id, a.user_id as assigned
							FROM ojs.user_user_groups g
							LEFT JOIN ojs.user_group_settings s
							ON s.user_group_id = g.user_group_id
							LEFT JOIN ojs.stage_assignments a
							ON g.user_id = a.user_id AND a.submission_id = $submissionId
							WHERE s.setting_value = 'Editor da revista'
							QUERY
						);
						while (!$result->EOF) {

							if($result->GetRowAssoc(0)['assigned'] == NULL){

								$userGroupId = $result->GetRowAssoc(0)['user_group_id'];
								$userId = $result->GetRowAssoc(0)['user_id'];

								$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
								$stageAssignment = $stageAssignmentDao->newDataObject();
								$stageAssignment->setSubmissionId($submissionId);
								$stageAssignment->setUserGroupId($userGroupId);
								$stageAssignment->setUserId($userId);
								$stageAssignment->setRecommendOnly(0);
								$stageAssignment->setCanChangeMetadata(1);
								$stageAssignmentDao->insertObject($stageAssignment);

								$submissionDAO = Application::getSubmissionDAO();
								$submission = $submissionDAO->getById($submissionId);

								$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
								$assignedUser = $userDao->getById($userId);
								$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
								$userGroup = $userGroupDao->getById($userGroupId);

								import('lib.pkp.classes.log.SubmissionLog');
								SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_ADD_PARTICIPANT, 'submission.event.participantAdded', array('name' => $assignedUser->getFullName(), 'username' => $assignedUser->getUsername(), 'userGroupName' => $userGroup->getLocalizedName()));

							}

							$result->MoveNext();
						}
						$now = date('Y-m-d H:i:s');
						$userDao->retrieve(
							<<<QUERY
							UPDATE status_csp SET status = 'pre_aguardando_editor_chefe', date_status = '$now' WHERE submission_id = $submissionId
							QUERY
						);
					}
					if($genreId == 30){ // QUANDO SECRETARIA SOBE UM PDF NO ESTÁGIO DE AVALIAÇÃO, O EDITOR ASSOCIADO É NOTIFICADO
						$stageId = $request->getUserVar('stageId');
						$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
						$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, 5);

						import('lib.pkp.classes.mail.MailTemplate');

						while ($user = $users->next()) {

							$mail = new MailTemplate('AVALIACAO_AUTOR_EDITOR_ASSOC');
							$mail->addRecipient($user->getEmail(), $user->getFullName());

							if (!$mail->send()) {
								import('classes.notification.NotificationManager');
								$notificationMgr = new NotificationManager();
								$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
							}
						}
					}
				}
			break;
			// Quando revisor/tradutor faz upload de arquivo no box de arquivo para edição de texto, editores assistentes são notificados
			case '48': // DE rev-trad corpo PT
			case '49': // DE rev-trad corpo  EN
			case '50': // DE rev-trad corpo  ES
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$stageId = $request->getUserVar('stageId');
				$locale = AppLocale::getLocale();

				import('lib.pkp.classes.file.SubmissionFileManager');

				$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
				$submissionFiles = $submissionFileDao->getBySubmissionId($submissionId);
				foreach ($submissionFiles as $submissionFile) {
					$genreIds[] = $submissionFile->_data["genreId"];
				}

				$genreIdsRevTrad = array(48,49,50);

				if(empty(array_intersect($genreIds, $genreIdsRevTrad))){

					import('lib.pkp.classes.mail.MailTemplate');

					$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
					$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, 24);
					while ($user = $users->next()) {

						$mail = new MailTemplate('COPYEDIT_RESPONSE');
						$mail->addRecipient($user->getEmail(), $user->getFullName());

						if (!$mail->send()) {
							import('classes.notification.NotificationManager');
							$notificationMgr = new NotificationManager();
							$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
						}
					}
				}

				$userDao = DAORegistry::getDAO('UserDAO');
				$now = date('Y-m-d H:i:s');
				$userDao->retrieve(
					<<<QUERY
					UPDATE status_csp SET status = 'ed_texto_traducao_metadados', date_status = '$now' WHERE submission_id = $submissionId
					QUERY
				);

			break;
			// Quando revisor de figura faz upload de figura alterada no box arquivos para edição de texto
			case '54': // Figura alterada
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$stageId = $request->getUserVar('stageId');
				$locale = AppLocale::getLocale();

				import('lib.pkp.classes.mail.MailTemplate');

				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
				$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, 24);
				while ($user = $users->next()) {

					$mail = new MailTemplate('EDICAO_TEXTO_FIG_APROVD');
					$mail->addRecipient($user->getEmail(), $user->getFullName());

					if (!$mail->send()) {
						import('classes.notification.NotificationManager');
						$notificationMgr = new NotificationManager();
						$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
					}
				}

				$userDao = DAORegistry::getDAO('UserDAO');
				$now = date('Y-m-d H:i:s');
				$userDao->retrieve(
					<<<QUERY
					UPDATE status_csp SET status = 'ed_text_envio_carta_aprovacao', date_status = '$now' WHERE submission_id = $submissionId
					QUERY
				);
			break;
			// Quando revisor de figura faz upload de figura formatada no box arquivos para edição de texto
			case '64': // Figura formatada
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$stageId = $request->getUserVar('stageId');
				$locale = AppLocale::getLocale();

				import('lib.pkp.classes.mail.MailTemplate');

				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
				$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, 24);
				while ($user = $users->next()) {

					$mail = new MailTemplate('EDITORACAO_FIG_FORMATADA');
					$mail->addRecipient($user->getEmail(), $user->getFullName());

					if (!$mail->send()) {
						import('classes.notification.NotificationManager');
						$notificationMgr = new NotificationManager();
						$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
					}
				}
				$userDao = DAORegistry::getDAO('UserDAO');
				$now = date('Y-m-d H:i:s');
				$userDao->retrieve(
					<<<QUERY
					UPDATE status_csp SET status = 'edit_pdf_padronizado', date_status = '$now' WHERE submission_id = $submissionId
					QUERY
				);
			break;
			case '65': // Figura para formatar
				// Quando é feito upload de figura para formatar, editores de figura recebem email de convite para formatar figura
				$userGroupEditorFigura = 21;
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$userDao = DAORegistry::getDAO('UserDAO');
				$site = $request->getSite();
				$context = $request->getContext();
				$result = $userDao->retrieve(
					<<<QUERY
					SELECT u.email, u.user_id
					FROM ojs.users u
					LEFT JOIN user_user_groups g
					ON u.user_id = g.user_id
					WHERE  g.user_group_id = $userGroupEditorFigura
					QUERY
				);

				import('lib.pkp.classes.mail.MailTemplate');
				while (!$result->EOF) {
					$mail = new MailTemplate('LAYOUT_REQUEST_PICTURE');
					$mail->addRecipient($result->GetRowAssoc(0)['email']);
					$mail->params["acceptLink"] = $request->_router->_indexUrl."/".$request->_router->_contextPaths[0]."/$$\$call$$$/grid/users/stage-participant/stage-participant-grid/save-participant/submission?submissionId=$submissionId&userGroupId=$userGroupEditorFigura&userIdSelected=".$result->GetRowAssoc(0)['user_id']."&stageId=5&accept=1";
					if (!$mail->send()) {
						import('classes.notification.NotificationManager');
						$notificationMgr = new NotificationManager();
						$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
					}
					$result->MoveNext();
				}
				$userDao = DAORegistry::getDAO('UserDAO');
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$now = date('Y-m-d H:i:s');
				$userDao->retrieve(
					<<<QUERY
					UPDATE status_csp SET status = 'edit_em_formatacao_figura', date_status = '$now' WHERE submission_id = $submissionId
					QUERY
				);
			break;
			case '67': // Material suplementar
				if (($_FILES['uploadedFile']['type'] <> 'application/pdf')/*PDF*/) {
					$args[0]->addError('typeId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.PDF')
					);
				}
			break;
			case '':
				$args[0]->setData('genreId',47);
				$args[1] = true;
			break;
			case '68': // Fluxograma
				$file = $_FILES['uploadedFile']['type'];
				if (!in_array($_FILES['uploadedFile']['type'], ['image/svg+xml','image/x-eps', 'image/wmf', 'application/vnd.oasis.opendocument.text', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.Fluxograma')
					);
				}
			break;
			case '69': // Gráfico
				if (!in_array($_FILES['uploadedFile']['type'], ['image/svg+xml','image/x-eps', 'image/wmf', 'application/vnd.oasis.opendocument.text', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.Gráfico')
					);
				}
			break;
			case '70': // Mapa
				if (!in_array($_FILES['uploadedFile']['type'], ['image/svg+xml','image/x-eps', 'image/wmf'])) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.Mapa')
					);
				}
			break;
			case '71': // Fotografia
				if (!in_array($_FILES['uploadedFile']['type'], ['image/bmp', 'image/tiff'])) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.Fotografia')
					);
				}
			break;
		return true;
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
		if($args[0]->getData('reviewRoundId')){
			if($args[0]->getData('fileStage')  == 15){ ///// Quando autor insere nova versão, o status é alterado
				$submissionId = $request->getUserVar('submissionId');
				$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
				$now = date('Y-m-d H:i:s');
				$userDao->retrieve(
					<<<QUERY
					UPDATE status_csp SET status = 'ava_aguardando_secretaria', date_status = '$now' WHERE submission_id = $submissionId
					QUERY
				);
			}
			if($args[0]->getData('fileStage')  == 4){ ///// Quando secretaria insere nova versão de PDF, o status é alterado
				$submissionId = $request->getUserVar('submissionId');
				$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
				$now = date('Y-m-d H:i:s');
				$userDao->retrieve(
					<<<QUERY
					UPDATE status_csp SET status = 'ava_com_editor_associado', date_status = '$now' WHERE submission_id = $submissionId
					QUERY
				);
			}
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
