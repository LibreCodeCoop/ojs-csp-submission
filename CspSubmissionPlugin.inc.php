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

import('lib.pkp.classes.plugins.GenericPlugin');
require_once(dirname(__FILE__) . '/vendor/autoload.php');

class CspSubmissionPlugin extends GenericPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success) {
			HookRegistry::register('userdao::_getbyusername', array($this, 'userdao__getbyusername'));

			HookRegistry::register('advancedsearchreviewerform::validate', array($this, 'advancedsearchreviewerform_validate'));

			// Insert new field into author metadata submission form (submission step 3) and metadata form
			HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'metadataFieldEdit'));
			HookRegistry::register('TemplateManager::fetch', array($this, 'TemplateManager_fetch'));
			HookRegistry::register('TemplateManager::display',array(&$this, 'TemplateManagerCsp_display'));
			HookRegistry::register('FileManager::downloadFile',array($this, 'fileManager_downloadFile'));
			HookRegistry::register('Mail::send', array($this,'MailCsp_send'));
			HookRegistry::register('submissionfilesuploadform::display', array($this,'submissionfilesuploadform_display'));

			HookRegistry::register('Submission::getMany::queryObject', array($this,'submission_getMany_queryObject'));

			HookRegistry::register('Submission::getMany::queryBuilder', array($this,'submission_getMany_queryBuilder'));

			HookRegistry::register('APIHandler::endpoints', array($this,'APIHandler_endpoints'));

			HookRegistry::register('authorform::readuservars', array($this, 'authorformReadUserVars'));

			HookRegistry::register('submissionsubmitstep4form::execute', array($this, 'metadataExecuteStep4'));
			HookRegistry::register('authorform::execute', array($this, 'authorformExecute'));

			HookRegistry::register('submissionsubmitstep3form::Constructor', array($this, 'SubmissionSubmitStep3FormCsp_constructor'));
			HookRegistry::register('submissionsubmitstep3form::initdata', array($this, 'SubmissionSubmitStep3FormCsp_initData'));
			HookRegistry::register('submissionsubmitstep3form::readuservars', array($this, 'SubmissionSubmitStep3FormCsp_readUserVars'));
			HookRegistry::register('submissionsubmitstep3form::execute', array($this, 'SubmissionSubmitStep3FormCsp_execute'));

			HookRegistry::register('submissionsubmitstep2form::Constructor', array($this, 'SubmissionSubmitStep2FormCsp_constructor'));

			// Consider the new field for ArticleDAO for storage
			HookRegistry::register('articledao::getAdditionalFieldNames', array($this, 'metadataReadUserVars'));

			HookRegistry::register('submissionfilesuploadform::validate', array($this, 'submissionfilesuploadformValidate'));

			HookRegistry::register('User::getMany::queryObject', array($this, 'pkp_services_pkpuserservice_getmany'));
			HookRegistry::register('UserDAO::_returnUserFromRowWithData', array($this, 'userDAO__returnUserFromRowWithData'));
			HookRegistry::register('User::getProperties::values', array($this, 'user_getProperties_values'));
			HookRegistry::register('authorform::initdata', array($this, 'authorform_initdata'));

			// This hook is used to register the components this plugin implements to
			// permit administration of custom block plugins.
			HookRegistry::register('LoadComponentHandler', array($this, 'LoadComponentHandler'));

			HookRegistry::register('userstageassignmentdao::_filterusersnotassignedtostageinusergroup', array($this, 'userstageassignmentdao_filterusersnotassignedtostageinusergroup'));

			HookRegistry::register('addparticipantform::execute', array($this, 'addparticipantformExecute'));

			HookRegistry::register('Publication::edit', array($this, 'publicationEdit'));

			HookRegistry::register('reviewergridhandler::initfeatures', array($this, 'reviewergridhandler_initfeatures'));

			// Hook para adicionar o campo comentário no upload de arquivos
			HookRegistry::register('submissionfilesmetadataform::readuservars', array($this, 'submissionFilesMetadataReadUserVars'));
			HookRegistry::register('submissionfiledaodelegate::getAdditionalFieldNames', array($this, 'submissionfiledaodelegateAdditionalFieldNames'));
			HookRegistry::register('submissionfilesmetadataform::execute', array($this, 'submissionFilesMetadataExecute'));

			// Updates status table when the submission is deleted
			HookRegistry::register('Submission::delete', array($this, 'submissionDelete'));
			// Updates status table when the submission is published
			HookRegistry::register('Publication::publish', array($this, 'publicationPublish'));
			// Displays extra fields in the workflow metadata area
			HookRegistry::register('Form::config::after', array($this, 'formConfigAfter'));

			HookRegistry::register('newreviewroundform::validate', array($this, 'newreviewroundform_validate'));

			HookRegistry::register('registrationform::display', array($this, 'RegistrationFormCsp_display'));
			HookRegistry::register('registrationform::readuservars', array($this, 'RegistrationFormCsp_readuservars'));
			HookRegistry::register('registrationform::execute', array($this, 'RegistrationFormCsp_execute'));
			HookRegistry::register('registrationform::Constructor', array($this, 'RegistrationFormCsp_constructor'));

			HookRegistry::register('userdao::getAdditionalFieldNames', array($this, 'UserdaoCsp_getAdditionalFieldNames'));

			HookRegistry::register('contactform::display', array($this, 'ContactFormCsp_display'));
			HookRegistry::register('contactform::readuservars', array($this, 'ContactFormCsp_readuservars'));
			HookRegistry::register('contactform::execute', array($this, 'ContactFormCsp_execute'));

			HookRegistry::register('identityform::display', array($this, 'IdentityFormCsp_display'));
			HookRegistry::register('identityform::readuservars', array($this, 'IdentityFormCsp_readuservars'));
			HookRegistry::register('identityform::execute', array($this, 'IdentityFormCsp_execute'));

			HookRegistry::register('EditorAction::recordDecision', array($this, 'EditorActionCsp_recordDecision'));
		}
		return $success;
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

	public function __call($name, $arguments) {
		if (!preg_match('/^(?<class>[a-zA-Z0-9]+)_(?<method>[_a-zA-Z0-9]+)/', $name, $matches)) {
			throw new \LogicException('Class or method not found');
		}
		import('plugins.generic.cspSubmission.class.' . $matches['class']);
		$class = new $matches['class']($this);
		call_user_func(array($class, $matches["method"]),$arguments[1]);
	}

	public function newreviewroundform_validate($hookName, $args) {
		$stageId = $args[0]->_submission->_data["stageId"];
		$submissionId = $args[0]->_submission->_data["id"];
		$userGroupId = 5; // Editor associado
		$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO');
		$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, $userGroupId);
		import('lib.pkp.classes.mail.MailTemplate');
		while ($user = $users->next()) {
			$mail = new MailTemplate('AVALIACAO_SEC_EDITOR_ASSOC');
			$mail->addRecipient($user->getEmail(), $user->getFullName());
			if (!$mail->send()) {
				import('classes.notification.NotificationManager');
				$notificationMgr = new NotificationManager();
				$notificationMgr->createTrivialNotification($_SESSION["userId"], NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
			}
		}
		$userDao = DAORegistry::getDAO('UserDAO');
		$now = date('Y-m-d H:i:s');
		$userDao->retrieve(
			'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
			array((string)'ava_com_editor_associado', (string)$now, (int)$submissionId)
		);
	}
	public function formConfigAfter($hookName, $args) {
		$templateManager =& $args[0];
		if ($templateManager["id"] == "titleAbstract"){
			array_splice($templateManager["fields"],0,1);
		}
		if ($templateManager["id"] == "metadata"){

			$pathAction = explode('/', $templateManager["action"]);
			$submissionId = end($pathAction);
			$submissionDAO = Application::getSubmissionDAO();
			$submission = $submissionDAO->getById($submissionId);
			$publication = $submission->getCurrentPublication();
			array_push(
						$templateManager["fields"],
						["name" => "agradecimentos",
						"component" => "field-text",
						"label" => __('plugins.generic.CspSubmission.agradecimentos'),
						"groupId" => "default",
						"isRequired" => false,
						"isMultilingual" => true,
						"value" => $submission->getData('agradecimentos'),
						"inputType" => "text",
						"size" => "large"
						],
						["name" => "conflitoInteresse",
						"component" => "field-text",
						"label" => __('plugins.generic.CspSubmission.conflitoInteresse'),
						"groupId" => "default",
						"isRequired" => false,
						"isMultilingual" => true,
						"value" => $submission->getData('conflitoInteresse'),
						"inputType" => "text",
						"size" => "large"
						],
						["name" => "consideracoesEticas",
						"component" => "field-radio-input",
						"label" => __('plugins.generic.CspSubmission.submission.consideracoesEticas'),
						"groupId" => "default",
						"isRequired" => false,
						"isMultilingual" => false,
						"value" => $submission->getData('consideracoesEticas'),
						"options" => 	[
											["value" => 1, "label" => __('plugins.generic.CspSubmission.submission.consideracoesEticas.checkbox.sim')],
											["value" => 0, "label" => __('plugins.generic.CspSubmission.submission.consideracoesEticas.checkbox.nao')]
										],
						],
						["name" => "ensaiosClinicos",
						"component" => "field-radio-input",
						"label" => __('plugins.generic.CspSubmission.submission.ensaiosClinicos'),
						"groupId" => "default",
						"isRequired" => false,
						"isMultilingual" => false,
						"value" => $submission->getData('ensaiosClinicos'),
						"options" => 	[
											["value" => 1, "label" => __('plugins.generic.CspSubmission.submission.ensaiosClinicos.checkbox.sim')],
											["value" => 0, "label" => __('plugins.generic.CspSubmission.submission.ensaiosClinicos.checkbox.nao')]
										],
						],
						["name" => "numRegistro",
						"component" => "field-text",
						"label" => __('plugins.generic.CspSubmission.submission.ensaiosClinicos.numRegistro'),
						"groupId" => "default",
						"isRequired" => false,
						"isMultilingual" => true,
						"value" => $submission->getData('numRegistro'),
						"inputType" => "text",
						"size" => "medium"
						],
						["name" => "orgao",
						"component" => "field-text",
						"label" => __('plugins.generic.CspSubmission.submission.ensaiosClinicos.orgao'),
						"groupId" => "default",
						"isRequired" => false,
						"isMultilingual" => true,
						"value" => $submission->getData('orgao'),
						"inputType" => "text",
						"size" => "medium"
						],
					);
			if($publication->getData('sectionId') == 5){
				array_push(
					$templateManager["fields"],
					["name" => "codigoTematico",
					"component" => "field-text",
					"label" => __('plugins.generic.CspSubmission.codigoTematico'),
					"groupId" => "default",
					"isRequired" => false,
					"isMultilingual" => true,
					"value" => $submission->getData('codigoTematico'),
					"inputType" => "text",
					"size" => "large"
					],
					["name" => "tema",
					"component" => "field-text",
					"label" => __('plugins.generic.CspSubmission.Tema'),
					"groupId" => "default",
					"isRequired" => false,
					"isMultilingual" => true,
					"value" => $submission->getData('tema'),
					"inputType" => "text",
					"size" => "large"
					],
				);
			}
			if($publication->getData('sectionId') == 15){
				array_push(
					$templateManager["fields"],
					["name" => "codigoArtigoRelacionado",
					"component" => "field-text",
					"label" => __('plugins.generic.CspSubmission.codigoArtigoRelacionado'),
					"groupId" => "default",
					"isRequired" => false,
					"isMultilingual" => true,
					"value" => $submission->getData('codigoArtigoRelacionado'),
					"inputType" => "text",
					"size" => "large"
					]
				);
			}
		}
	}

	public function userdao__getbyusername($hookName, $args) {
		$request = \Application::get()->getRequest();
		if (!strpos($request->getRequestPath(), 'login/signIn')) {
			return false;
		}

		if (empty($args[1][0])) {
			return false;
		}
		$userDao = DAORegistry::getDAO('UserDAO');
		$result = $userDao->retrieve(
			<<<QUERY
			SELECT login AS username,
			       p.email,
			       p.telefone AS phone,
			       p.pais AS country,
			       p.orcid AS orcid,
			       p.nome AS givenName,
			       CASE WHEN p.idioma = 'pt' THEN 'pt_BR'
			            WHEN p.idioma = 'in' THEN 'en_US'
			            WHEN p.idioma = 'es' THEN 'es_ES'
			        END AS locales,
			        CASE WHEN 0 THEN 14 -- Autor => Autor
			             WHEN 1 THEN 16 -- Consultor => Avaliador
			             WHEN 2 THEN 5 -- Editor Associado => Editor de seção 
			             WHEN 3 THEN 3 -- Editor Chefe => Editor da revista
			             WHEN 4 THEN 7 -- Editor Assistente => Editor de texto
			             WHEN 5 THEN 7 -- Assistente Editorial => Editor de texto
			             WHEN 6 THEN 1 -- Administrador
			             WHEN 7 THEN 4 -- Diagramador => Editor de leiaute
			             WHEN 8 THEN 15 -- Tradutor/Revisor (SAGAAS) => Tradutor
			             WHEN 9 THEN 1 -- Administrador SAGAAS => Administrador
			             WHEN 10 THEN 16 -- Consultor => Avaliador
			             WHEN 11 THEN 2 -- Secretaria Editorial e Diagramador => Gerente de revista
			         END AS `group`,
			       p.lattes,
			       p.sexo,
			       p.observacao,
			       p.instituicao1,
			       p.instituicao2,
			       p.endereco,
			       p.cidade,
			       p.estado,
			       p.cep
			  FROM csp.Login l
			  LEFT JOIN ojs.users ou ON ou.username = l.login
			  JOIN csp.Pessoa p ON l.idPessoaFK = p.idPessoa
			 WHERE (l.login = ? OR p.email = ?)
			   AND l.senha = ?
			   AND ou.user_id IS NULL
			QUERY,
			[
				$args[1][0],
				$args[1][0],
				sha1($request->getUserVar('password'))
			]
		);
		if (!$result->RecordCount()) {
			$args[0].= ' OR email = ?';
			$args[1] = [$args[1][0], $args[1][0]];
			return false;
		}
		$row = $result->GetRowAssoc(false);
		$user = $userDao->newDataObject(); /** @var User */
		$user->setAllData($row);
		$user->setGivenName($row['givenname'], $row['locales']);
		$user->setLocales([$row['locales']]);
		$user->setPassword(\Validation::encryptCredentials(
			$row['username'],
			$request->getUserVar('password')
		));
		$userDao->insertObject($user);

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroupDao->assignUserToGroup($user->getId(), $row['group']);
		$args[2] = $userDao->retrieve($args[0], [$row['username']]);
		return true;
	}

	/**
	 * Hook to intercept the reviewer grid
	 * @param $hookName string
	 * @param $args array
	 * @return void
	 */
	public function reviewergridhandler_initfeatures($hookName, $args)
	{
		$returner = &$args[3];
		import('plugins.generic.cspSubmission.controllers.grid.feature.AddReviewerSagasFeature');
		$returner[] = new AddReviewerSagasFeature();
	}

	public function advancedsearchreviewerform_validate($hookName, $args)
	{
		$reviewerForm = $args[0];
		$reviewerIds = json_decode($reviewerForm->getData('reviewerId'), true);
		$reviewRoundId = (int)$reviewerForm->getData('reviewRoundId');
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$assigned = (int)$reviewAssignmentDao->retrieve(
			<<<SQL
			SELECT count(*) AS total
			  FROM review_assignments
			 WHERE declined = 0
			   AND cancelled = 0
			   AND date_completed IS NULL
			   AND review_round_id = ?
			SQL,
			[$reviewRoundId]
		)->GetRowAssoc(false)['total'];

		$inQueue = $this->getReviewersInQueue($reviewRoundId);
		$totalToAssingNow = 3 - $assigned - count($reviewerIds);
		foreach ($inQueue as $reviewer) {
			$this->removeFromQueue($reviewer['user_id'], $reviewer['review_round_id']);
			$reviewerIds[] = $reviewer['user_id'];
			$totalToAssingNow--;
			if (!$totalToAssingNow) {
				break;
			}
		}

		$totalToAddInQueue = count($reviewerIds) - (3 - $assigned);
		for ($i = 0; $i < $totalToAddInQueue; $i++) {
			$this->addToQueue(array_shift($reviewerIds), $reviewRoundId);
			$assigned++;
		}
		$reviewerForm->setData('reviewerId', json_encode($reviewerIds));
	}
	private function getReviewersInQueue(int $reviewRoundId) {
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		return $reviewAssignmentDao->retrieve(
			<<<SQL
			SELECT *
			  FROM csp_reviewer_queue
			 WHERE review_round_id = ?
			 ORDER BY created_at
			SQL,
			[$reviewRoundId]
		)->GetAssoc();
	}

	private function addToQueue(int $reviewerId, int $reviewRoundId) {
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		try {
			$reviewAssignmentDao->update(
				'INSERT INTO csp_reviewer_queue (user_id, review_round_id, created_at) VALUES (?, ?, ?)',
				[
					'user_id' => $reviewerId,
					'review_round_id' => $reviewRoundId,
					'date' => date('Y-m-d H:i:s')
				]
			);
		} catch (\Throwable $th) {
		}
	}

	public function removeFromQueue($userId, $reviewRoundId) {
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignmentDao->update(
			'DELETE FROM csp_reviewer_queue WHERE user_id = ? AND review_round_id = ?',
			[
				'user_id' => $userId,
				'review_round_id' => $reviewRoundId
			]
		);
	}

	public function submission_getMany_queryBuilder($hookName, $args) {
		$request = \Application::get()->getRequest();
		$args[1]["substage"] = $request->_requestVars["substage"];
	}

	public function submission_getMany_queryObject($hookName, $args) {
		/**
		 * @var SubmissionQueryBuilder
		 */
		$qb = $args[0];
		$request = \Application::get()->getRequest();
		$substage = $request->_requestVars["substage"];
		$status = $request->_requestVars["status"];

		$sessionManager = SessionManager::getManager();
		$session = $sessionManager->getUserSession();
		$role = $session->getSessionVar('role');

		if ($substage) {
			$substages = explode(',',str_replace("'", "", $substage));
			$queryStatusCsp = Capsule::table('status_csp');
			$queryStatusCsp->select(Capsule::raw('DISTINCT status_csp.submission_id'));
			$queryStatusCsp->whereIn('status_csp.status', $substages);

			if($substage == 'ava_aguardando_autor_mais_60_dias'){
				$queryStatusCsp->where('status_csp.date_status', '<=', date('Y-m-d H:i:s', strtotime('-2 months')));
			}elseif($substage == "'em_progresso'"){
				$qb->where('s.date_submitted','=' ,null);
			}

			$qb->wheres[1]["values"][0] = $status;
			$qb->bindings["where"][1] = $status;

			if($role == "Gerente"){
				unset($qb->joins[0]->wheres[1]);
			}
			if($role == "Autor"){
				$qb->where('sa.user_group_id','=' ,14);
			}
			if($role == "Avaliador"){
				$qb->where('ra.date_completed','=' ,null);
			}
		}
		$qb->orders[0]["column"] = 's.date_last_activity';
		$qb->orders[0]["direction"] = 'asc';
	}

	/**
	 * Permit requests to the custom block grid handler
	 * @param $hookName string The name of the hook being invoked
	 * @param $args array The parameters to the invoked hook
	 */
	function loadComponentHandler($hookName, $params) {
		$component =& $params[0];
		$action =& $params[1];
		$request = \Application::get()->getRequest();
		if ($component == 'plugins.generic.CspSubmission.controllers.grid.AddAuthorHandler') {
			return true;
		}
		if ($component == 'plugins.generic.cspSubmission.controllers.grid.users.reviewer.ReviewerGridHandler') {
			return true;
		}
		if ($component == 'grid.users.reviewer.ReviewerGridHandler' && $action == 'updateReviewer') {
			$component = 'plugins.generic.cspSubmission.controllers.grid.users.reviewer.ReviewerGridHandler';
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
				switch ($userGroupId) {
					case '12': // Diagramador
						$userDao = DAORegistry::getDAO('UserDAO');
						$now = date('Y-m-d H:i:s');
						$submissionId = $request->getUserVar('submissionId');
						$userDao->retrieve(
							'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
							array((string)'edit_em_diagramacao', (string)$now, (int)$submissionId)
						);
					break;
				return true;
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

					if (in_array(10, $genreIds)) { // Se houverem figuras, revisores de figura são designados e conversa é iniciada com autor

						$context = $request->getContext();
						$submissionId = $request->getUserVar('submissionId');
						$stageId = 4;
						$userGroupId = 10; /// Revisor de figura
						$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
						$users = $userGroupDao->getUsersById($userGroupId, $context->getId());
						$messageParticipants = array();
						while ($user = $users->next()) {
							$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
							$userId = $user->getData('id');
							$messageParticipants[] = $userId;
							$assigned = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submissionId, $userId, $stageId);
							if ($assigned->wasEmpty()){
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
						}

						$submissionDAO = Application::getSubmissionDAO();
						$submission = $submissionDAO->getById($submissionId);
						$publication = $submission->getCurrentPublication();
						$userDao = DAORegistry::getDAO('UserDAO');
						$authorDao = DAORegistry::getDAO('AuthorDAO');
						$author = $authorDao->getById($publication->getData('primaryContactId'));
						$author = $userDao->getUserByEmail($author->getData('email'));
						$messageParticipants[] = $author->getData('id');

						import('lib.pkp.controllers.grid.queries.form.QueryForm');

						$assocType = ASSOC_TYPE_SUBMISSION;
						$assocId = $submissionId;
						$stageId = 4;

						$queryForm = new QueryForm(
							$request,
							$assocType,
							$assocId,
							$stageId
						);
						$queryForm->initData();

						import('lib.pkp.classes.mail.MailTemplate');
						$mail = new MailTemplate('EDICAO_TEXTO_PENDENC_TEC');
						$request->_requestVars["subject"] = $mail->getData('subject');
						$request->_requestVars["comment"] = $mail->getData('body');
						$request->_requestVars["users"] = $messageParticipants;

						$queryForm = new QueryForm(
							$request,
							$assocType,
							$assocId,
							$stageId,
							$queryForm->_query->_data["id"]
						);
						$queryForm->readInputData();

						if ($queryForm->validate()) {
							$queryForm->execute();

								// Update submission notifications
								$notificationMgr = new NotificationManager();
								$notificationMgr->updateNotification(
									$request,
									array(
										NOTIFICATION_TYPE_ASSIGN_COPYEDITOR,
										NOTIFICATION_TYPE_AWAITING_COPYEDITS,
										NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER,
										NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS,
									),
									null,
									ASSOC_TYPE_SUBMISSION,
									$assocId
								);
						}

						$now = date('Y-m-d H:i:s');
						$userDao->retrieve(
							'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',array((string)'ed_text_em_avaliacao_ilustracao', (string)$now, (int)$submissionId)
						);
					}else{ // Se não, assitentes editoriais são notificados e status é alterado para "Envio de carta de aprovação"
						$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
						$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $request->getUserVar('stageId'), 4);
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
							'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',array((string)'ed_text_envio_carta_aprovacao', (string)$now, (int)$submissionId)
						);
					}
				}
			}
			if($request->getUserVar('decision') == 7){ // Quando submissão é enviada para editoração, status é alterado para Aguardando padronizador
				if($params[1] == "savePromote"){
					$request = \Application::get()->getRequest();
					$submissionId = $request->getUserVar('submissionId');
					$userDao = DAORegistry::getDAO('UserDAO');
					$now = date('Y-m-d H:i:s');
					$userDao->retrieve(
						'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
						array((string)'edit_aguardando_padronizador', (string)$now, (int)$submissionId)
					);
				}
			}
			if($request->getUserVar('recommendation')){ // Quando editor associado faz recomendação, o status é alterado
				$submissionId = $request->getUserVar('submissionId');
				$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
				$now = date('Y-m-d H:i:s');
				$userDao->retrieve(
					'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
					array((string)'ava_aguardando_editor_chefe', (string)$now, (int)$submissionId)
				);
			}
		}
		if ($component == 'api.file.ManageFileApiHandler') {
			$locale = AppLocale::getLocale();
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
			$reviewRound = $reviewRoundDao->getById($request->_requestVars["reviewRoundId"]);
			$version = $reviewRound->_data["round"] == "" ? '1' : $reviewRound->_data["round"];

			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
			$submissionFiles = $submissionFileDao->getBySubmissionId($request->_requestVars["submissionId"]);
			$fileVersion = $request->_requestVars["fileId"].'-1';
			$originalFileName = $submissionFiles[$fileVersion]->_data["originalFileName"];
			$originalFileNameArray = explode('.',$originalFileName);

			if($request->_requestVars["name"][$locale]){
				$fileNameArray = explode('.',$request->_requestVars["name"][$locale]);
				$request->_requestVars["name"][$locale] = $fileNameArray[0].'_v'.$version.'.'.$originalFileNameArray[1];
			}else{
				$fileNameArray = explode('.',$request->_requestVars["name"]);
				$request->_requestVars["name"] = $fileNameArray[0].'_v'.$version.'.'.$originalFileNameArray[1];
			}
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
				'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
				array((string)'ava_com_editor_associado', (string)$now, (int)$submissionId)
			);
		}
		if($request->getUserVar("userGroupId") == 7){ // Quando designa revisor/tradutor status é alterado para "Em revisão tradução"
			$userDao = DAORegistry::getDAO('UserDAO');
			$now = date('Y-m-d H:i:s');
			$submissionId = $request->getUserVar('submissionId');
			$userDao->retrieve(
				'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
				array((string)'ed_text_em_revisao_traducao', (string)$now, (int)$submissionId)
			);
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
		$submissionId = $request->getUserVar('submissionId');
		import('lib.pkp.classes.mail.MailTemplate');

		if ($args[1] == "submission/form/complete.tpl") {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('complete.tpl'));
			return true;
		}
		if ($args[1] == "controllers/grid/users/userSelect/searchUserFilter.tpl") {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('searchUserFilter.tpl'));
			return true;
		}
		if ($args[1] == 'controllers/grid/gridBodyPart.tpl') {
			if (!strpos($request->_requestPath, 'reviewer-grid/fetch-grid')) {
				return false;
			}
			$rows = $templateMgr->getVariable('rows');

			$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
			$result = $reviewAssignmentDao->retrieve(
				<<<SQL
				SELECT *
				  FROM csp_reviewer_queue
				 WHERE review_round_id = ?
				 ORDER BY created_at
				SQL,
				[$request->getUserVar('reviewRoundId')]
			);
			if (!$result->RecordCount()) {
				return;
			}
			$userDao = DAORegistry::getDAO('UserDAO');
			import('plugins.generic.cspSubmission.controllers.grid.users.reviewer.ReviewerQueueGridRow');
			$columns = $templateMgr->getVariable('columns');
			while (!$result->EOF) {
				$data = $result->GetRowAssoc(0);
				$user = $userDao->getById($data['user_id']);
				$data['user'] = $user;

				$row = new ReviewerQueueGridRow();
				$row->setData($data);
				$row->initialize($request);
				$renderedCells = [];
				foreach ($columns->value as $column) {
					$renderedCells[] = $row->renderCell($request, $row, $column);
				}
				$templateMgrRow = TemplateManager::getManager($request);
				$templateMgrRow->assign('row', $row);
				$templateMgrRow->assign('cells', $renderedCells);
				$rows->value[] = $templateMgrRow->fetch($row->getTemplate());

				$result->MoveNext();
			}
			return false;
		} elseif ($args[1] == 'controllers/modals/editorDecision/form/sendReviewsForm.tpl') {
			// Retrieve peer reviews.
			if($request->_requestVars["decision"] == 9 or $request->_requestVars["decision"] == 4){ // Recusa imediata e Recusa após avaliação
				$submissionDAO = Application::getSubmissionDAO();
				$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
				$submissionIdCSP = $submission->getData('codigoArtigo');
				$templateMgr->assign('submissionIdCSP', $submissionIdCSP);
			}else{
				$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
				$submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /* @var $submissionCommentDao SubmissionCommentDAO */
				$reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO'); /* @var $reviewFormResponseDao ReviewFormResponseDAO */
				$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /* @var $reviewFormElementDao ReviewFormElementDAO */
				$reviewAssignments = $reviewAssignmentDao->getBySubmissionId($submissionId, $request->getUserVar('reviewRoundId'));
				$reviewIndexes = $reviewAssignmentDao->getReviewIndexesForRound($submissionId, $request->getUserVar('reviewRoundId'));
				AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);
	
				$body = '';
				$textSeparator = '------------------------------------------------------';
				foreach ($reviewAssignments as $reviewAssignment) {
					// If the reviewer has completed the assignment, then import the review.
					if ($reviewAssignment->getDateCompleted() != null && $reviewAssignment->getUnconsidered() != 1) {
						// Get the comments associated with this review assignment
						$submissionComments = $submissionCommentDao->getSubmissionComments($submissionId, COMMENT_TYPE_PEER_REVIEW, $reviewAssignment->getId());
	
						$body .= "<br><br>$textSeparator<br>";
						// If it is an open review, show reviewer's name.
						if ($reviewAssignment->getReviewMethod() == SUBMISSION_REVIEW_METHOD_OPEN) {
							$body .= $reviewAssignment->getReviewerFullName() . "<br>\n";
						} else {
							$body .= __('submission.comments.importPeerReviews.reviewerLetter', array('reviewerLetter' => PKPString::enumerateAlphabetically($reviewIndexes[$reviewAssignment->getId()]))) . "<br>\n";
						}
	
						while ($comment = $submissionComments->next()) {
							// If the comment is viewable by the author, then add the comment.
							if ($comment->getViewable()) {
								$body .= PKPString::stripUnsafeHtml($comment->getComments());
							}
						}
	
						// Add reviewer recommendation
						$recommendation = $reviewAssignment->getLocalizedRecommendation();
						$body .= __('submission.recommendation', array('recommendation' => $recommendation)) . "<br>\n";
	
						$body .= "<br>$textSeparator<br><br>";
	
						if ($reviewFormId = $reviewAssignment->getReviewFormId()) {
							$reviewId = $reviewAssignment->getId();
	
	
							$reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewFormId);
							if(!$submissionComments) {
								$body .= "$textSeparator<br>";
	
								$body .= __('submission.comments.importPeerReviews.reviewerLetter', array('reviewerLetter' => PKPString::enumerateAlphabetically($reviewIndexes[$reviewAssignment->getId()]))) . '<br><br>';
							}
							while ($reviewFormElement = $reviewFormElements->next()) {
								if (!$reviewFormElement->getIncluded()) continue;
	
								$body .= PKPString::stripUnsafeHtml($reviewFormElement->getLocalizedQuestion());
								$reviewFormResponse = $reviewFormResponseDao->getReviewFormResponse($reviewId, $reviewFormElement->getId());
								if ($reviewFormResponse) {
									$possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
									// See issue #2437.
									if (in_array($reviewFormElement->getElementType(), array(REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES, REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS))) {
										ksort($possibleResponses);
										$possibleResponses = array_values($possibleResponses);
									}
									if (in_array($reviewFormElement->getElementType(), $reviewFormElement->getMultipleResponsesElementTypes())) {
										if ($reviewFormElement->getElementType() == REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES) {
											$body .= '<ul>';
											foreach ($reviewFormResponse->getValue() as $value) {
												$body .= '<li>' . PKPString::stripUnsafeHtml($possibleResponses[$value]) . '</li>';
											}
											$body .= '</ul>';
										} else {
											$body .= '<blockquote>' . PKPString::stripUnsafeHtml($possibleResponses[$reviewFormResponse->getValue()]) . '</blockquote>';
										}
										$body .= '<br>';
									} else {
										$body .= '<blockquote>' . nl2br(htmlspecialchars($reviewFormResponse->getValue())) . '</blockquote>';
									}
								}
							}
							$body .= "$textSeparator<br><br>";
						}
					}
				}
				import('lib.pkp.classes.mail.MailTemplate');
				$mail = new MailTemplate('EDITOR_DECISION_RESUBMIT');
				$mail->getData('body');
				$templateMgr->assign('personalMessage', $mail->getData('body').$body);
			}
			$args[4] = $templateMgr->fetch($this->getTemplateResource('sendReviewsForm.tpl'));
			return true;
		} elseif ($args[1] == 'controllers/grid/users/reviewer/form/createReviewerForm.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('createReviewerForm.tpl'));

			return true;
		} elseif ($args[1] == 'controllers/grid/gridRow.tpl') {
			if (strpos($request->_requestPath, 'reviewer-grid/fetch-grid')) {
				$columns = $templateMgr->getVariable('columns');
				if (isset($columns->value['method'])) {
					unset($columns->value['method']);
				}
			}
			if (strpos($request->_requestPath, 'user-select/user-select-grid/fetch-grid')){
				$templateMgr = TemplateManager::getManager($request);
				$columns = $templateMgr->getVariable('columns');
				$cells = $templateMgr->getVariable('cells');
				$row = $templateMgr->getVariable('row');
				$cells->value[] = $row->value->_data->_data["assigns"];
				$columns->value['assigns'] = clone $columns->value["name"];
				$columns->value["assigns"]->_title = "author.users.contributor.assign";
			}
			if(strpos($request->_requestPath, 'editor-submission-details-files-grid/fetch-grid')
			OR strpos($request->_requestPath, 'final-draft-files-grid/fetch-grid')
			OR strpos($request->_requestPath, 'editor-review-files-grid/fetch-grid')
			OR strpos($request->_requestPath, 'production-ready-files-grid/fetch-grid')){ //Busca comentários somente quando grid for de arquivos
				$templateMgr = TemplateManager::getManager($request);
				$columns = $templateMgr->getVariable('columns');
				$cells = $templateMgr->getVariable('cells');
				$row = $templateMgr->getVariable('row');
				if($row->value->_data["submissionFile"]->_data["comentario"] <> ''){
					array_splice($cells->value, -1, 1, $row->value->_data["submissionFile"]->_data["comentario"]);
				}
			}
			if (strpos($request->_requestPath, 'manage-final-draft-files-grid/fetch-grid')) {
				$columns = $templateMgr->getVariable('columns');
				$cells = $templateMgr->getVariable('cells');
				$row = $templateMgr->getVariable('row');
				$columns->value["date"] = clone $columns->value["name"];
				if($row->value->_data["submissionFile"]->_data["dateUploaded"]){
					$dateTimeFormatLong = \Config::getVar('general', 'datetime_format_long');
					$timestamp = strtotime($row->value->_data["submissionFile"]->_data["dateUploaded"]);
					array_splice( $cells->value, 3, 0, strftime($dateTimeFormatLong, $timestamp));
				}
			}

		} elseif ($args[1] == 'submission/form/step3.tpl'){
			$submissionDAO = Application::getSubmissionDAO();
			$submission = $submissionDAO->getById($submissionId);
			$publication = $submission->getCurrentPublication();
			$sectionId = $publication->getData('sectionId');
			$templateMgr->assign('abstractDisplay', true);
			$templateMgr->assign('keywordsEnabled', true);
			$templateMgr->assign('keywordsRequired', true);
			if(in_array($sectionId, array(2, 3, 10, 11, 12, 13, 14, 15))){ // Editorial, Perspectivas, Entrevista, Carta, Resenhas, Obituário, Errata, Comentários
				$templateMgr->assign('keywordsEnabled', false);
				$templateMgr->assign('keywordsRequired', false);
				$templateMgr->assign('abstractDisplay', false);
			}
			if($sectionId == 5){ // Espaço Temático
				$templateMgr->assign('keywordsRequired', false);
			}
			if($sectionId == 6){ // Revisão
				$templateMgr->assign('notification', 'plugins.generic.CspSubmission.Revisao.Notificacao');
			}
			$args[4] = $templateMgr->fetch($this->getTemplateResource('step3.tpl'));

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

			$locale = AppLocale::getLocale();
			$submissionDAO = Application::getSubmissionDAO();

			$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
			$templateMgr->assign('title',$submission->getTitle(AppLocale::getLocale()));
			import('lib.pkp.classes.linkAction.request.OpenWindowAction');
			$templateMgr->tpl_vars['reviewerActions']->value[] = 
				new LinkAction(
					'consultPubMed',
					new OpenWindowAction(
						'https://pubmed.ncbi.nlm.nih.gov/?term='.
						$submission->getTitle(AppLocale::getLocale())
					),
					__('editor.submission.consultPubMed')
				);

			$templateMgr->tpl_vars['selectReviewerListData']->value['components']['selectReviewer']['selectorName'] = 'reviewerId';
			$templateMgr->tpl_vars['selectReviewerListData']->value['components']['selectReviewer']['selectorType'] = 'checkbox';
			$templateMgr->tpl_vars['selectReviewerListData']->value['components']['selectReviewer']['selected'] = [];
			$templateMgr->tpl_vars['selectReviewerListData']->value['components']['selectReviewer']['canSelect'] = 'true';
			$templateMgr->tpl_vars['selectReviewerListData']->value['components']['selectReviewer']['canSelectAll'] = 'true';

			$inQueue = $this->getReviewersInQueue(
				$request->getUserVar('reviewRoundId')
			);
			$templateMgr->tpl_vars['selectReviewerListData']->value['components']['selectReviewer']['currentlyAssigned'] = array_merge(
				$templateMgr->tpl_vars['selectReviewerListData']->value['components']['selectReviewer']['currentlyAssigned'],
				array_keys($inQueue)
			);

			$submissionIdCSP = $submission->getData('codigoArtigo');
			$mail = new MailTemplate('REVIEW_REQUEST_ONECLICK');
			$templateSubject['REVIEW_REQUEST_ONECLICK'] = $mail->_data["subject"];
			$templateBody['REVIEW_REQUEST_ONECLICK'] = $mail->_data["body"];
			$publication = $submission->getCurrentPublication();
			$submissionTitle = $publication->getLocalizedTitle($locale);
			$submissionAbstract = $submission->getLocalizedAbstract($locale);
			$context = $request->getContext();
			$contextName = $context->getLocalizedName();

			$templateBody = str_replace(
				[
					'{$submissionIdCSP}',
					'{$submissionTitle}',
					'{$contextName}',
					'{$submissionAbstract}',
					'{$editorialContactSignature}'
				],
				[
					$submissionIdCSP,
					$submissionTitle,
					$contextName,
					$submissionAbstract,
					$context->getData('contactName')
				],
				$templateBody
			);

			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign(array(
				'templates' => $templateSubject,
				'personalMessage' => reset($templateBody)
			));

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
			$mail = new MailTemplate('REVIEW_ACK');
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
				$templateBody = ['EDITOR_ASSIGN' => $mail->_data["body"]];

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
							FROM email_templates_settings
							WHERE setting_name = 'body' AND locale = '$locale'
						)a
						LEFT JOIN
						(
								SELECT setting_value as subject, email_id
								FROM email_templates_settings
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

					$context = $request->getContext();
					$submissionId = $request->getUserVar('submissionId');
					$stageId = $request->getUserVar('stageId');
					$userGroupId = 4; /// Editor assistente
					$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
					$users = $userGroupDao->getUsersById($userGroupId, $context->getId());

					while ($user = $users->next()) {
						$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
						$assigned = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submissionId, $user->getData('id'), $stageId);
						$userId = $user->getData('id');
						if ($assigned->wasEmpty()){
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
					}
				}

				$args[4] = $templateMgr->fetch($this->getTemplateResource('promoteFormStage1And3.tpl'));

				return true;

			}elseif ($stageId == 4){
				$templateMgr->assign('skipEmail',1); // Passa variável para não enviar email para o autor
				$args[4] = $templateMgr->fetch($this->getTemplateResource('promoteFormStage4.tpl'));
				return true;
			}

		}elseif ($args[1] == 'controllers/grid/queries/form/queryForm.tpl') {

			/// Se o usuário for o autor, o destinatário será a secretaria, caso contrário, o destinatário será o autor
			$submissionDAO = Application::getSubmissionDAO();
			$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
			$publication = $submission->getCurrentPublication();
			$userDao = DAORegistry::getDAO('UserDAO');
			$authorDao = DAORegistry::getDAO('AuthorDAO');
			$author = $authorDao->getById($publication->getData('primaryContactId'));
			$author = $userDao->getUserByEmail($author->getData('email'));

			if ($author->getData('id') == $_SESSION["userId"]) {

				$mail = new MailTemplate('MSG_AUTOR_SECRETARIA');
				$templateSubject['MSG_AUTOR_SECRETARIA'] = $mail->_data["subject"];
				$templateBody['MSG_AUTOR_SECRETARIA'] = $mail->_data["body"];

				$templateMgr->assign('author', true);
				$templateMgr->assign('to', 14); // BUSCAR ID DA SECRETARIA
				$templateMgr->assign('toName', 'Secretaria');
				$templateMgr->assign('from', $_SESSION["userId"]);

			}else{
				if($stageId == "1"){ // Se o estágio for Pré-avaliação, template específico é exibido
					$mail = new MailTemplate('PRE_AVALIACAO');
					$templateSubject['PRE_AVALIACAO'] = $mail->_data["subject"];
					$templateBody['PRE_AVALIACAO'] = $mail->_data["body"];
				}
				if($stageId == "4"){ // Se o estágio for Edição de texto, templates específicos são exibidos para cada perfil
					$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
					$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
					$manager = $userGroupDao->getUserGroupIdsByRoleId(ROLE_ID_MANAGER);
					$assistent = $userGroupDao->getUserGroupIdsByRoleId(ROLE_ID_ASSISTANT);
					$stageAssignmentsFactory = $stageAssignmentDao->getBySubmissionAndStageId($request->getUserVar('submissionId'), null, null, $_SESSION["userId"]);

					$isManager = false;
					$isAssistent = false;
					while ($stageAssignment = $stageAssignmentsFactory->next()) {
						if ($isManager || $isAssistent){
							break;
						}
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
						$mail = new MailTemplate('EDICAO_TEXTO_PENDENC_TEC');
						$templateSubject['EDICAO_TEXTO_PENDENC_TEC'] = $mail->_data["subject"];
						$templateBody['EDICAO_TEXTO_PENDENC_TEC'] = $mail->_data["body"];
					}
				}
				if($stageId == "5"){// Se o estágio for Editoração, template específico é exibido
					$context = $request->getContext();
					$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
					$userInGroup = $userGroupDao->userInGroup($_SESSION["userId"], 13); // Padronizador
					if($userInGroup){
						$mail = new MailTemplate('EDITORACAO_PROVA_PRELO');
						$templateSubject['EDITORACAO_PROVA_PRELO'] = $mail->_data["subject"];
						$templateBody['EDITORACAO_PROVA_PRELO'] = $mail->_data["body"];
					}
				}
			}

			if($mail or $author->getData('id') == $_SESSION["userId"]){
				$authorName = $author->getLocalizedGivenName();
				$submissionTitle = $publication->getLocalizedTitle();
				$context = $request->getContext();
				$contextName = $context->getLocalizedName();
				$submissionDAO = Application::getSubmissionDAO();
				$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
				$submissionIdCSP = $submission->getData('codigoArtigo');

				$comment = str_replace(
					[
						'{$authorName}',
						'{$submissionTitle}',
						'{$submissionIdCSP}',
						'{$contextName}',
						'{$editorialContactSignature}'
					],
					[
						$authorName,
						$submissionTitle,
						$submissionIdCSP,
						$contextName,
						$context->getData('emailSignature')
					],
					$templateBody
				);

				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign(array(
					'templates' => $templateSubject,
					'message' => json_encode($templateBody),
					'comment' => reset($comment)
				));
				$args[4] = $templateMgr->fetch($this->getTemplateResource('queryForm.tpl'));

				return true;

			}

		}elseif ($args[1] == 'controllers/wizard/fileUpload/form/fileUploadForm.tpl') {

			$args[4] = $templateMgr->fetch($this->getTemplateResource('fileUploadForm.tpl'));
			return true;

		} elseif ($args[1] == 'controllers/wizard/fileUpload/form/submissionFileMetadataForm.tpl'){
			$tplvars = $templateMgr->getFBV();
			$locale = AppLocale::getLocale();
			$genreId = $tplvars->_form->_submissionFile->_data["genreId"];

			// Id do tipo de arquivo "Outros"
			$genreDao = \DAORegistry::getDAO('GenreDAO');
			$request = \Application::get()->getRequest();
			$context = $request->getContext();
			$contextId = $context->getData('id');
			$genreOther = $genreDao->getByKey('OTHER', $contextId);

			if($genreId == $genreOther->getData('id')){

				$tplvars->_form->_submissionFile->_data["name"][$locale] = "csp_".$request->_requestVars["submissionId"]."_".date("Y")."_".$tplvars->_form->_submissionFile->_data["originalFileName"];

			}else{

				$genre = $genreDao->getById($genreId);
				$genreName = str_replace(" ","_",$genre->getLocalizedName());
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
				'skipDiscussionSkip' => true,
				'recommendationOptions' =>	array(
												'' => 'common.chooseOne',
												SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS => 'editor.submission.decision.requestRevisions',
												SUBMISSION_EDITOR_RECOMMEND_ACCEPT => 'editor.submission.decision.accept',
												SUBMISSION_EDITOR_RECOMMEND_DECLINE => 'editor.submission.decision.decline',
											)
			));

			$args[4] = $templateMgr->fetch($this->getTemplateResource('recommendationForm.tpl'));
			return true;

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

		if ($fileStage == 2){
			if($submissionProgress == 0){
				$result = $userDao->retrieve(
					'SELECT A.genre_id, setting_value
					FROM genre_settings A
					LEFT JOIN genres B
					ON B.genre_id = A.genre_id
					WHERE locale = ? AND entry_key = ?',
					array((string)$locale, (string)'SUBMISSAO_PDF')
				);
				while (!$result->EOF) {
					$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];
					$result->MoveNext();
				}
				$templateMgr->setData('submissionFileGenres', $genreList);
				$templateMgr->setData('isReviewAttachment', false); // SETA A VARIÁVEL PARA FALSE POIS ELA É VERIFICADA NO TEMPLATE PARA EXIBIR OS COMPONENTES
			}
		}elseif ($fileStage == 4) { // UPLOAD DE ARQUIVOS PARA AVALIAÇÃO
			$context = $request->getContext();
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
			$userInGroup = $userGroupDao->userInGroup($userId, 8); // Secretaria
			if($userInGroup){
				$genreDao = \DAORegistry::getDAO('GenreDAO');
				$genre = $genreDao->getByKey('SUBMISSAO_PDF', $context->getId());
				$genreList[$genre->getData('id')] =  $genre->getLocalizedName();
				$genre = $genreDao->getByKey('AVAL_AUTOR_ALTERACOES', $context->getId());
				$genreList[$genre->getData('id')] =  $genre->getLocalizedName();
				$templateMgr->setData('submissionFileGenres', $genreList);
			}
		}elseif ($fileStage == 5) { // AVALIADOR FAZENDO UPLOAD DE PARECER
			$result = $userDao->retrieve(
				'SELECT A.genre_id, setting_value
				FROM genre_settings A
				LEFT JOIN genres B
				ON B.genre_id = A.genre_id
				WHERE locale = ? AND entry_key LIKE ?',
				array((string)$locale, (string)'AVAL_AVALIADOR%')
			);
			while (!$result->EOF) {
				$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];
				$result->MoveNext();
			}
			$templateMgr->setData('submissionFileGenres', $genreList);
			$templateMgr->setData('isReviewAttachment', false); // SETA A VARIÁVEL PARA FALSE POIS ELA É VERIFICADA NO TEMPLATE PARA EXIBIR OS COMPONENTES
		}elseif ($fileStage == 6) { // Envio de  arquivo de versão final
			$context = $request->getContext();
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
			$userInGroup = $userGroupDao->userInGroup($userId, 10); // Revisor de figura
			if($userInGroup){
				$genreDao = \DAORegistry::getDAO('GenreDAO');
				$genre = $genreDao->getByKey('EDICAO_TEXTO_FIG_ALT', $context->getId());
				$genre =  array($genre->getData('id') => $genre->getLocalizedName());
				$templateMgr->setData('submissionFileGenres', $genre);
			}else{
				$templateMgr->setData('isReviewAttachment', TRUE); // Seta variável para true pois é verificada no template para não exibir os componentes de arquivo
			}			
		}elseif ($fileStage == 9) { // Upload de arquivo em box de arquivos de revisão de texto
			$context = $request->getContext();
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
			$userInGroup = $userGroupDao->userInGroup($userId, 7); // Revisor / tradutor
			if($userInGroup){
				$result = $userDao->retrieve(
					'SELECT A.genre_id, setting_value
					FROM genre_settings A
					LEFT JOIN genres B
					ON B.genre_id = A.genre_id
					WHERE locale = ? AND entry_key LIKE ?',
					array((string)$locale,(string)'EDICAO_TRADUT%')
				);
			}else{
				$result = $userDao->retrieve(
					'SELECT A.genre_id, setting_value
					FROM genre_settings A
					LEFT JOIN genres B
					ON B.genre_id = A.genre_id
					WHERE locale = ? AND entry_key LIKE ?',
					array((string)$locale, (string)'EDICAO_ASSIST_ED%')
				);
			}
			while (!$result->EOF) {
				$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];
				$result->MoveNext();
			}
			$templateMgr->setData('submissionFileGenres', $genreList);
		}elseif ($fileStage == 10) { // Upload de PDF para publicação
			$result = $userDao->retrieve(
				'SELECT A.genre_id, setting_value
				FROM genre_settings A
				LEFT JOIN genres B
				ON B.genre_id = A.genre_id
				WHERE locale = ? AND entry_key LIKE ?',
				array((string)$locale, (string)'EDITORACAO_DIAGRM%')
			);
			while (!$result->EOF) {
				$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];
				$result->MoveNext();
			}
			$templateMgr->setData('submissionFileGenres', $genreList);
		}elseif ($fileStage == 11) { // Upload de arquivo em box "Arquivos prontos para layout"
			$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO'); /* @var $userGroupAssignmentDao UserGroupAssignmentDAO */
			$userGroupAssignments = $userGroupAssignmentDao->getByUserId($request->getUser()->getId());
			while ($assignment = $userGroupAssignments->next()) {
				$userGroupIds[] = $assignment->getUserGroupId();
			}
			if(!empty(array_intersect($userGroupIds, ['4']))){ // Ed. Assistente
				$genreDao = \DAORegistry::getDAO('GenreDAO');
				$context = $request->getContext();
				$genres = array();
				$genre = $genreDao->getByKey('EDITORACAO_FIG_FORMATAR', $context->getId());
				$genres[$genre->getData('id')] = $genre->getLocalizedName();
				$genre = $genreDao->getByKey('EDITORACAO_TEMPLT_PT', $context->getId());
				$genres[$genre->getData('id')] = $genre->getLocalizedName();
				$genre = $genreDao->getByKey('EDITORACAO_TEMPLT_EN', $context->getId());
				$genres[$genre->getData('id')] = $genre->getLocalizedName();
				$genre = $genreDao->getByKey('EDITORACAO_TEMPLT_ES', $context->getId());
				$genres[$genre->getData('id')] = $genre->getLocalizedName();
			}
			if(!empty(array_intersect($userGroupIds, ['11','12']))){ // Editor de figura ou diagramador
				$genreDao = \DAORegistry::getDAO('GenreDAO');
				$context = $request->getContext();
				$genres = array();
				$genre = $genreDao->getByKey('EDITORACAO_FIG_FORMATAD', $context->getId());
				$genres[$genre->getData('id')] = $genre->getLocalizedName();
				$genre = $genreDao->getByKey('EDITORACAO_PDF_DIAGRAMADO', $context->getId());
				$genres[$genre->getData('id')] = $genre->getLocalizedName();
			}
			if(!empty($genres)){
				$templateMgr->setData('submissionFileGenres', $genres);
			}else{
				$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
			}
		}elseif ($fileStage == 15) { // Upload de nova versão
			$context = $request->getContext();
			$genreDao = \DAORegistry::getDAO('GenreDAO');
			$genre = $genreDao->getByKey('AVAL_AUTOR_ALTERACOES', $context->getId());
			$templateMgr->_data["submissionFileGenres"][$genre->getData('id')] = $genre->getLocalizedName();
			$templateMgr->setData('alert', 'É obrigatória a submissão de uma carta ao editor associado escolhendo o componete "Alterações realizadas"');
		}elseif ($fileStage == 17) { // ARQUIVOS DEPENDENTES EM PUBLICAÇÃO
			$templateMgr->setData('isReviewAttachment', TRUE); // SETA A VARIÁVEL PARA TRUE POIS ELA É VERIFICADA NO TEMPLATE PARA NÃO EXIBIR OS COMPONENTES
		}elseif ($fileStage == 18) {  // Upload no box de discussão
			if($stageId == 5){
				$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
				$stageAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($request->getUserVar('submissionId'), ROLE_ID_AUTHOR, null, $request->getUser()->getId());
				$isAuthor = $stageAssignments->getCount()>0;
				if($isAuthor){
					$result = $userDao->retrieve(
						'SELECT A.genre_id, setting_value
						FROM genre_settings A
						LEFT JOIN genres B
						ON B.genre_id = A.genre_id
						WHERE locale = ? AND entry_key LIKE ?',
						array((string)$locale, (string)'EDITORACAO_AUTOR%')
					);
				}else{
					$result = $userDao->retrieve(
						'SELECT A.genre_id, setting_value
						FROM genre_settings A
						LEFT JOIN genres B
						ON B.genre_id = A.genre_id
						WHERE locale = ? AND entry_key LIKE ?',
						array((string)$locale,(string)'EDITORACAO_ASSIST_ED%')
					);
				}
				while (!$result->EOF) {
					$genreList[$result->GetRowAssoc(0)['genre_id']] = $result->GetRowAssoc(0)['setting_value'];
					$result->MoveNext();
				}
				$templateMgr->setData('submissionFileGenres', $genreList);
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
				if(isset($isAuthor)){
					$result = $userDao->retrieve(
						'SELECT A.genre_id, setting_value
						FROM genre_settings A
						LEFT JOIN genres B
						ON B.genre_id = A.genre_id
						WHERE locale = ? AND entry_key LIKE ?',
						array((string)$locale,(string)'PEND_TEC_%')
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
		}else{
			$templateMgr->setData('isReviewAttachment', TRUE); // Atribui TRUE para variável utilizada para não exibir os componentes
		}
	}

	function pkp_services_pkpuserservice_getmany($hookName, $args)
	{
		$request = \Application::get()->getRequest();
		$refObject   = new ReflectionObject($args[1]);
		$refReviewStageId = $refObject->getProperty('reviewStageId');
		$refReviewStageId->setAccessible( true );
		$reviewStageId = $refReviewStageId->getValue($args[1]);
		if ($reviewStageId or $request->_router->_op == "addQuery" or $request->_router->_op == "editQuery" or $request->_router->_op == "updateQuery") {
			return;
		}
		if (strpos($_SERVER["HTTP_REFERER"], 'submission/wizard') || strpos($_SERVER["HTTP_REFERER"], 'workflow/index')) {
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
					'SELECT COLUMN_NAME
					FROM INFORMATION_SCHEMA.COLUMNS
					WHERE TABLE_SCHEMA = ?
					AND TABLE_NAME = ?',
					array((string)'ojs', (string)'users')
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
					FROM stage_assignments a
					JOIN submissions s
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
		$locale = AppLocale::getLocale();
		$request = \Application::get()->getRequest();
		$type = $request->getUserVar('type');
		$form = $args[0];
		if ($type == 'ojs') {
			$userId = $request->getUserVar('userId');
			$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
			$user = $userDao->getById($userId);
			$form->setData('givenName', [$locale => $user->getlocalizedGivenName()]);
			$form->setData('familyName', [$locale => $user->getlocalizedfamilyName()]);
			$form->setData('affiliation', [$locale =>  $user->getlocalizedAffiliation()]);
			$form->setData('email',  $user->getEmail());
			$form->setData('orcid',  $user->getOrcid());
			$form->setData('country',  $user->getCountry());
		}elseif ($type == 'csp') {
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
			$form->setData('givenName', [$locale => $userCsp['given_name']]);
			$form->setData('familyName', [$locale => $userCsp['family_name']]);
			$form->setData('affiliation', [$locale => $userCsp['affiliation']]);
			$form->setData('email', $userCsp['email']);
			$form->setData('orcid', $userCsp['orcid']);
		}elseif($form->getAuthor() != null){
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

		if($sectionId == 5){ // Espaço temático
			$output .= $smarty->fetch($this->getTemplateResource('tema.tpl'));
			$output .= $smarty->fetch($this->getTemplateResource('codigoTematico.tpl'));
		}

		if($sectionId == 15){ // Comentários
			$output .= $smarty->fetch($this->getTemplateResource('codigoArtigoRelacionado.tpl'));
		}

		$output .= $smarty->fetch($this->getTemplateResource('conflitoInteresse.tpl'));
		$output .= $smarty->fetch($this->getTemplateResource('agradecimentos.tpl'));
		$output .= $smarty->fetch($this->getTemplateResource('InclusaoAutores.tpl'));
		$output .= $smarty->fetch($this->getTemplateResource('consideracoesEticas.tpl'));
		$output .= $smarty->fetch($this->getTemplateResource('ensaiosClinicos.tpl'));


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
		$article->setData('codigoArtigo', $result->GetRowAssoc(false)['code']);

		$now = date('Y-m-d H:i:s');
		$submissionId = $article->getData('id');
		$userDao->retrieve(
			'INSERT INTO status_csp (submission_id, status, date_status) VALUES (?,?,?)',
			array((int)$submissionId, (string)'pre_aguardando_secretaria',$now)
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


	function publicationEdit($hookName, $params) {
		$router = $params[3]->getRouter();
		if($router->_page == 'submission'){
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
			$params[0]->setData('consideracoesEticas', $params[3]->_requestVars["consideracoesEticas"]);
			$params[1]->setData('consideracoesEticas', $params[3]->_requestVars["consideracoesEticas"]);
			$params[2]["consideracoesEticas"] = $params[3]->_requestVars["consideracoesEticas"];
			$params[0]->setData('ensaiosClinicos', $params[3]->_requestVars["ensaiosClinicos"]);
			$params[1]->setData('ensaiosClinicos', $params[3]->_requestVars["ensaiosClinicos"]);
			$params[2]["ensaiosClinicos"] = $params[3]->_requestVars["ensaiosClinicos"];
			$params[0]->setData('numRegistro', $params[3]->_requestVars["numRegistro"]);
			$params[1]->setData('numRegistro', $params[3]->_requestVars["numRegistro"]);
			$params[2]["numRegistro"] = $params[3]->_requestVars["numRegistro"];
			$params[0]->setData('orgao', $params[3]->_requestVars["orgao"]);
			$params[1]->setData('orgao', $params[3]->_requestVars["orgao"]);
			$params[2]["orgao"] = $params[3]->_requestVars["orgao"];
		}
		return false;
	}


	public function submissionfilesuploadformValidate($hookName, $args) {
		// Retorna o tipo do arquivo enviado
		$genreId = $args[0]->getData('genreId');
		$args[0]->_data["fileStage"];
		switch($genreId) {
			case 1: // Corpo do artigo
			case 13: // Tabela ou quadro
			case 18: // Legendas
				if (!in_array($_FILES['uploadedFile']['type'],
				['application/msword', 'application/wps-office.doc', /*Doc*/
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/wps-office.docx', /*docx*/
				'application/vnd.oasis.opendocument.text', /*odt*/
				'application/rtf'] /*rtf*/
				)) {
					$args[0]->addError('genreId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.AticleBody')
					);
					break;
				}

				if($genreId == 1){
					$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
					$submissionFiles = $submissionFileDao->getBySubmissionId($args[0]->getData('submissionId'));
					foreach ($submissionFiles as $submissionFile) {
						if(substr($submissionFile->getLocalizedName(), 0, 14) == 'Corpo_do_Texto'){
							$args[0]->addError('genreId',
							__('plugins.generic.CspSubmission.submission.bodyTextFile.Twice')
						);
						}
					}
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
					if($genreId == '46'){ // Quando a secretaria sobre um PDF no estágio de pré-avaliaçao, a submissão é designada para os editores chefe da revista

						$context = $request->getContext();
						$submissionId = $request->getUserVar('submissionId');
						$stageId = $request->getUserVar('stageId');
						$userGroupId = 3; /// Editor chefe
						$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
						$users = $userGroupDao->getUsersById($userGroupId, $context->getId());

						while ($user = $users->next()) {
							$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
							$assigned = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submissionId, $user->getData('id'), $stageId);
							$userId = $user->getData('id');
							if ($assigned->wasEmpty()){
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
						}
						$now = date('Y-m-d H:i:s');
						$userDao->retrieve(
							'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
							array((string)'pre_aguardando_editor_chefe',(string)$now,(int)$submissionId)
						);
					}
				}
			break;
			// Quando revisor/tradutor faz upload de arquivo no box de arquivo para edição de texto, editores assistentes são notificados
			case '48': // DE rev-trad corpo PT
			case '49': // DE rev-trad corpo  EN
			case '50': // DE rev-trad corpo  ES
			case '51': // DE rev-trad legenda  PT
			case '52': // DE rev-trad legenda  EN
			case '53': // DE rev-trad legenda  ES
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$stageId = $request->getUserVar('stageId');
				$userGroupId =  4; // Editor assistente
				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
				$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, $userGroupId);
				import('lib.pkp.classes.mail.MailTemplate');
				while ($user = $users->next()) {

					$mail = new MailTemplate('COPYEDIT_RESPONSE');
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
					'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
					array((string)'ed_texto_traducao_metadados', (string)$now, (int)$submissionId)
				);
			break;
			// Quando revisor de figura faz upload de figura alterada no box arquivos para edição de texto, editores assistentes são notificados
			case '54': // Figura alterada
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$stageId = $request->getUserVar('stageId');
				import('lib.pkp.classes.mail.MailTemplate');
				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
				$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, 4);
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
					'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
					array((string)'ed_text_envio_carta_aprovacao', (string)$now, (int)$submissionId)
				);
			break;
			case '57': // PDF para publicação PT
			case '58': // PDF para publicação EN
			case '59': // PDF para publicação ES
				// Quando é feito upload PDF para publicação, editores de XML recebem email de convite para produzir XML
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$context = $request->getContext();
				$userGroupId = 9; //  Editor de XML 
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
				$users = $userGroupDao->getUsersById($userGroupId, $context->getId());
				import('lib.pkp.classes.mail.MailTemplate');
				while ($user = $users->next()) {
					$mail = new MailTemplate('PRODUCAO_XML');
					$mail->addRecipient($user->getData('email'));
					$indexUrl = $request->getIndexUrl();
					$contextPath = $request->getRequestedContextPath();
					$mail->params["acceptLink"] = $indexUrl."/".$contextPath[0].
												"/$$\$call$$$/grid/users/stage-participant/stage-participant-grid/save-participant/submission?".
												"submissionId=$submissionId".
												"&userGroupId=$userGroupId".
												"&userIdSelected=".$user->getData('id').
												"&stageId=5&accept=1";
					if (!$mail->send()) {
						import('classes.notification.NotificationManager');
						$notificationMgr = new NotificationManager();
						$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
					}
				}
			break;        
			case '60': // Template PT
			case '61': // Template ES
			case '62': // Template EN
				// Quando é feito upload de template, diagramadores recebem email de convite para produzir PDF
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$context = $request->getContext();
				$userGroupId = 12; // Diagrmador
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
				$users = $userGroupDao->getUsersById($userGroupId, $context->getId());
				import('lib.pkp.classes.mail.MailTemplate');
				while ($user = $users->next()) {
					$mail = new MailTemplate('LAYOUT_REQUEST');
					$mail->addRecipient($user->getData('email'));
					$indexUrl = $request->getIndexUrl();
					$contextPath = $request->getRequestedContextPath();
					$mail->params["acceptLink"] = $indexUrl."/".$contextPath[0].
												"/$$\$call$$$/grid/users/stage-participant/stage-participant-grid/save-participant/submission?".
												"submissionId=$submissionId".
												"&userGroupId=$userGroupId".
												"&userIdSelected=".$user->getData('id').
												"&stageId=5&accept=1";
					if (!$mail->send()) {
						import('classes.notification.NotificationManager');
						$notificationMgr = new NotificationManager();
						$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
					}
				}
			break;        
			// Quando editor de figura faz upload de figura formatada no box arquivos para edição de texto
			case '64': // Figura formatada
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$stageId = $request->getUserVar('stageId');
				$locale = AppLocale::getLocale();
				$userGroupId = 13; // Padronizador
				import('lib.pkp.classes.mail.MailTemplate');
				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
				$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, $userGroupId);
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
					'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
					array((string)'edit_pdf_padronizado', (string)$now, (int)$submissionId)
				);
			break;
			case '65': // Figura para formatar
				// Quando é feito upload de figura para formatar, editores de figura recebem email de convite para formatar figura
				$request = \Application::get()->getRequest();
				$userGroupId = 11; // Editor de figura
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
				$context = $request->getContext();
				$users = $userGroupDao->getUsersById($userGroupId, $context->getId());
				$submissionId = $request->getUserVar('submissionId');
				import('lib.pkp.classes.mail.MailTemplate');
				while ($user = $users->next()) {
					$mail = new MailTemplate('LAYOUT_REQUEST_PICTURE');
					$mail->addRecipient($user->getData('email'));
					$indexUrl = $request->getIndexUrl();
					$contextPath = $request->getRequestedContextPath();
					$mail->params["acceptLink"] = $indexUrl."/".$contextPath[0].
												"/$$\$call$$$/grid/users/stage-participant/stage-participant-grid/save-participant/submission?".
												"submissionId=$submissionId".
												"&userGroupId=$userGroupId".
												"&userIdSelected=".$user->getData('id').
												"&stageId=5&accept=1";
					if (!$mail->send()) {
						import('classes.notification.NotificationManager');
						$notificationMgr = new NotificationManager();
						$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
					}
				}
				$userDao = DAORegistry::getDAO('UserDAO');
				$request = \Application::get()->getRequest();
				$now = date('Y-m-d H:i:s');
				$userDao->retrieve(
					'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
					array((string)'edit_em_formatacao_figura', (string)$now, (int)$submissionId)
				);
			break;
			case '':
				$genreDao = \DAORegistry::getDAO('GenreDAO');
				$request = \Application::get()->getRequest();
				$context = $request->getContext();
				$contextId = $context->getData('id');
				$genre = $genreDao->getByKey('OTHER', $contextId);
				$genreId = $genre->getData('id');
				$args[0]->setData('genreId',$genreId);
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
			case '73':// Quando diagramador faz upload de PDF diagramado editores assistentes são notificados
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$stageId = $request->getUserVar('stageId');
				$groupId = 4; // Editores assistentes
				import('lib.pkp.classes.mail.MailTemplate');
				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
				$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, $groupId);
				while ($user = $users->next()) {
					$mail = new MailTemplate('LAYOUT_COMPLETE');
					$mail->addRecipient($user->getEmail(), $user->getFullName());
					if (!$mail->send()) {
						import('classes.notification.NotificationManager');
						$notificationMgr = new NotificationManager();
						$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
					}
				}
			break;
			case '79':// Autor subindo arquivo de correções
				if (($_FILES['uploadedFile']['type'] <> 'application/pdf')/*PDF*/) {
					$args[0]->addError('typeId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.PDF')
					);
				}
      break;
			case '75': // XML publicação PT
			case '76': // XML publicação EN
			case '77': // XML publicação ES
				$request = \Application::get()->getRequest();
				$submissionId = $request->getUserVar('submissionId');
				$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
				$now = date('Y-m-d H:i:s');
				$userDao->retrieve(
					'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
					array((string)'edit_aguardando_publicacao', (string)$now, (int)$submissionId)
				);
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
		return false;
	}
	function fileManager_downloadFile($hookName, $args)
	{
		$request = \Application::get()->getRequest();
		$fileVersion = $request->_requestVars["fileId"].'-'.$request->_requestVars["revision"];

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFiles = $submissionFileDao->getBySubmissionId($request->_requestVars["submissionId"]);

		$submissionDAO = Application::getSubmissionDAO();
		$submission = $submissionDAO->getById($request->_requestVars["submissionId"]);
		$submissionIdCsp = $submission->getData('codigoArtigo');

		$localizedName = $submissionIdCsp.'_'.$submissionFiles[$fileVersion]->getLocalizedName();
		$args[4] = $localizedName;
	}

	public function submissionDelete($hookName, $args){
		$submissionId = $args[0]->getData('id');
		$userDao = DAORegistry::getDAO('UserDAO');
		$now = date('Y-m-d H:i:s');
		$userDao->retrieve(
			<<<QUERY
			UPDATE status_csp SET status = 'deletada', date_status = '$now' WHERE submission_id = $submissionId
			QUERY
		);
	}

	public function publicationPublish($hookName, $args){
		$submissionId = $args[0]->getData('id');
		$userDao = DAORegistry::getDAO('UserDAO');
		$now = date('Y-m-d H:i:s');
		$userDao->retrieve(
			<<<QUERY
			UPDATE status_csp SET status = 'publicada', date_status = '$now' WHERE submission_id = $submissionId
			QUERY
		);
	}
}
