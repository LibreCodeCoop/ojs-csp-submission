<?php

import('plugins.generic.cspSubmission.class.AbstractPlugin');

/**
 * @file plugins/generic/cspSubmission/class/SubmissionFilesMetadataCsp.inc.php
 *
 * @class SubmissionFilesMetadataCsp
 *
 * @brief Class for modify behaviors on files metadata insertion
 *
 */
class SubmissionFilesMetadataFormCsp extends AbstractPlugin
{
	function readUserVars($params) {
		$userVars =& $params[1];
		$userVars[] = 'comentario';
		return false;
	}

	function execute($params) {
		$name = $params[0]->_data["name"][$params[0]->requiredLocale];
		if (substr($name,0,38) == "Arquivo_padronizado_para_diagramação") {
			// Quando é feito upload de Arquivo padronizado para diagramção, diagramadores são designados e recebem email para produzir PDF
			$userGroupId = 12; // Diagramador
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
			$submissionId = $params[0]->_submissionFile->_data["submissionId"];
			$request = \Application::get()->getRequest();
			$context = $request->getContext();
			$users = $userGroupDao->getUsersById($userGroupId, $context->getId());
			import('lib.pkp.classes.mail.MailTemplate');
			while ($user = $users->next()) {
				$stageId = $params[0]->_stageId;
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

					$mail = new MailTemplate('LAYOUT_REQUEST');
					$mail->addRecipient($user->getData('email'));
					import('classes.core.Services');
					$submissionUrl = Services::get('submission')->getWorkflowUrlByUserRoles($submission, $user->getId());
					$mail->params["submissionUrl"] = $submissionUrl;
					if (!$mail->send()) {
						import('classes.notification.NotificationManager');
						$notificationMgr = new NotificationManager();
						$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
					}
				}
			}
		}
		$form =& $params[0];
		$submissionFile = $form->_submissionFile;
		$submissionFile->setData('comentario', $form->getData('comentario'));
		return false;
	}
}
