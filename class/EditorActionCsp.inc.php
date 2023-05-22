<?php

import('plugins.generic.cspSubmission.class.AbstractPlugin');

/**
 * @file plugins/generic/cspSubmission/class/EditorActionCsp.inc.php
 *
 * @class EditorActionCsp
 *
 * @brief Class for modify behaviors at the editor decision moment
 *
 */
class EditorActionCsp extends AbstractPlugin
{
	/**
	 * Updates csp status tables as the editor decision
	 *
	 * @param [type] $args
	 * @return void
	 */
	public function recordDecision($args)
	{
		$userDao = DAORegistry::getDAO('UserDAO');
		$dateDecided = $args[2]["dateDecided"];
		if ($args[2]["decision"] == 4 or $args[2]["decision"] == 9) { // Submissão é rejeitada
			$userDao->update(
				'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
				[(string)'rejeitada', (string)$dateDecided, (int)$args[0]->getData('id')]
			);
		} elseif ($args[2]["decision"] == 8) { // Envia para avaliação
			$userDao->update(
				'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
				[(string)'ava_aguardando_editor_chefe', (string)$dateDecided, (int)$args[0]->getData('id')]
			);
		} elseif ($args[2]["decision"] == 2) { // Solicita modificações ao ao autor
			$userDao->update(
				'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
				[(string)'ava_aguardando_autor', (string)$dateDecided, (int)$args[0]->getData('id')]
			);
		} elseif (in_array($args[2]["decision"], array(11, 12, 14))) { // Editor associado faz recomendação
			$userDao->update(
				'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
				[(string)'ava_aguardando_editor_chefe', (string)$dateDecided, (int)$args[0]->getData('id')]
			);
		} elseif ($args[2]["decision"] == 2) { // Submissão é enviada para editoração
			$userDao->update(
				'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
				[(string)'edit_aguardando_padronizador', (string)$dateDecided, (int)$args[0]->getData('id')]
			);
			/* Quando submissõa é aceita, editores assistentes são designados */
		} elseif ($args[2]["decision"] == 1) {
			$request = \Application::get()->getRequest();
			$submissionId = $args[0]->_data["id"];
			$stageId = $request->getUserVar('stageId');
			$userGroupId = 4; /// Editor assistente
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$users = $userGroupDao->getUsersById($userGroupId, $args[0]->_data["contextId"]);
			while ($user = $users->next()) {
				$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
				$assigned = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submissionId, $user->getData('id'), $stageId);
				$userId = $user->getData('id');
				if ($assigned->wasEmpty()) {
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
					$userDao = DAORegistry::getDAO('UserDAO');
					$assignedUser = $userDao->getById($userId);
					$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
					$userGroup = $userGroupDao->getById($userGroupId);

					import('lib.pkp.classes.log.SubmissionLog');
					SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_ADD_PARTICIPANT, 'submission.event.participantAdded', array('name' => $assignedUser->getFullName(), 'username' => $assignedUser->getUsername(), 'userGroupName' => $userGroup->getLocalizedName()));
				}
			}

			$submissionId = $args[0]->_data["id"];
			$userDao = DAORegistry::getDAO('UserDAO');

			import('lib.pkp.classes.file.SubmissionFileManager');

			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
			$submissionFiles = $submissionFileDao->getBySubmissionId($submissionId);
			foreach ($submissionFiles as $submissionFile) {
				$genreIds[] = $submissionFile->_data["genreId"];
			}
			/* Se houverem figuras, revisores de figura são designados e conversa é iniciada com autor */
			if (in_array(10, $genreIds)) {
				$stageId = 4;
				$userGroupId = 10; /// Revisor de figura
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
				$users = $userGroupDao->getUsersById($userGroupId, $args[0]->_data["contextId"]);
				$messageParticipants = array();
				while ($user = $users->next()) {
					$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
					$userId = $user->getData('id');
					$messageParticipants[] = $userId;
					$assigned = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submissionId, $userId, $stageId);
					if ($assigned->wasEmpty()) {
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
						$userDao = DAORegistry::getDAO('UserDAO');
						$assignedUser = $userDao->getById($userId);
						$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
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
				$userDao->update(
					'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
					[(string)'ed_text_em_avaliacao_ilustracao', (string)$dateDecided, (int)$args[0]->getData('id')]
				);
				/* Se não, assitentes editoriais são notificados e status é alterado para "Envio de carta de aprovação" */
			} else {
				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO');
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
				$userDao->update(
					'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
					[(string)'ed_text_envio_carta_aprovacao', (string)$dateDecided, (int)$args[0]->getData('id')]
				);
			}
		}
	}
}