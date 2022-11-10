<?php

import('plugins.generic.cspSubmission.class.AbstractPlugin');

/**
 * @file plugins/generic/cspSubmission/class/SubmissionFilesUploadFormCsp.inc.php
 *
 * @class SubmissionFilesUploadFormCsp
 *
 * @brief Class for modify behaviors on files upload moment
 *
 */
class SubmissionFilesUploadFormCsp extends AbstractPlugin
{
	public function display($args)
	{
		$request = \Application::get()->getRequest();
		$submissionId = $request->getUserVar('submissionId');
		$fileStage = $request->getUserVar('fileStage');
		$submissionDAO = Application::getSubmissionDAO();
		$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
		$submissionProgress = $submission->getData('submissionProgress');
		$stageId = $request->getUserVar('stageId');
		$userId = $_SESSION["userId"];
		$context = $request->getContext();
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
				foreach ($result as $row) {
					$genreList[$row->genre_id] = $row->setting_value;
				}
				$templateMgr->setData('submissionFileGenres', $genreList);
				$templateMgr->setData('isReviewAttachment', false); // SETA A VARIÁVEL PARA FALSE POIS ELA É VERIFICADA NO TEMPLATE PARA EXIBIR OS COMPONENTES
			}
		}elseif ($fileStage == 4) { // UPLOAD DE ARQUIVOS PARA AVALIAÇÃO
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
			foreach ($result as $row) {
				$genreList[$row->genre_id] = $row->setting_value;
			}
			$templateMgr->setData('submissionFileGenres', $genreList);
			$templateMgr->setData('isReviewAttachment', false); // SETA A VARIÁVEL PARA FALSE POIS ELA É VERIFICADA NO TEMPLATE PARA EXIBIR OS COMPONENTES
		}elseif ($fileStage == 9) { // Upload de arquivo em box de arquivos de revisão de texto
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
			foreach ($result as $row) {
				$genreList[$row->genre_id] = $row->setting_value;
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
			foreach ($result as $row) {
				$genreList[$row->genre_id] = $row->setting_value;
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
			$genreDao = \DAORegistry::getDAO('GenreDAO');
			$genre = $genreDao->getByKey('AVAL_AUTOR_ALTERACOES', $context->getId());
			$templateMgr->_data["submissionFileGenres"][$genre->getData('id')] = $genre->getLocalizedName();
			$templateMgr->setData('alert', __('plugins.generic.CspSubmission.submission.newVersion.alert'));
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
				foreach ($result as $row) {
					$genreList[$row->genre_id] = $row->setting_value;
				}
				$templateMgr->setData('submissionFileGenres', $genreList);
			}elseif($stageId == 4){
				// Buscar componentes de arquivos específicos para o autor
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
					foreach ($result as $row) {
						$genreList[$row->genre_id] = $row->setting_value;
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

	public function validate($args) {
		$request = \Application::get()->getRequest();
		$submissionId = $request->getUserVar('submissionId');
		$context = $request->getContext();
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
				if($genreId == 1 && $args[0]->_stageId == 1){
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
				$userDao = DAORegistry::getDAO('UserDAO');
				if (($_FILES['uploadedFile']['type'] <> 'application/pdf')/*PDF*/) {
					$args[0]->addError('typeId',
						__('plugins.generic.CspSubmission.SectionFile.invalidFormat.PDF')
					);
				}else{
					if($genreId == '46'){ // Quando a secretaria sobre um PDF no estágio de pré-avaliaçao, a submissão é designada para os editores chefe da revista
						$stageId = $request->getUserVar('stageId');
						$userGroupId = 3; /// Editor chefe
						$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
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
						$userDao->retrieve(
							'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
							array((string)'pre_aguardando_editor_chefe',(string)(new DateTimeImmutable())->format('Y-m-d H:i:s'),(int)$submissionId)
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
				$userDao->retrieve(
					'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
					array((string)'ed_texto_traducao_metadados', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$submissionId)
				);
			break;
			case '57': // PDF para publicação PT
			case '58': // PDF para publicação EN
			case '59': // PDF para publicação ES
				// Quando é feito upload PDF para publicação, editores de XML recebem email de convite para produzir XML
				$userGroupId = 9; //  Editor de XML 
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
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
			// Quando editor de figura faz upload de figura formatada no box arquivos para edição de texto
			case '64': // Figura formatada
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
				$userDao->retrieve(
					'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
					array((string)'edit_pdf_padronizado', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$submissionId)
				);
			break;
			case '65': // Figura para formatar
				// Quando é feito upload de figura para formatar, editores de figura recebem email de convite para formatar figura
				$userGroupId = 11; // Editor de figura
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
				$users = $userGroupDao->getUsersById($userGroupId, $context->getId());
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
				$userDao->retrieve(
					'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
					array((string)'edit_em_formatacao_figura', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$submissionId)
				);
			break;
			case '':
				$genreDao = \DAORegistry::getDAO('GenreDAO');
				$contextId = $context->getData('id');
				$genre = $genreDao->getByKey('OTHER', $contextId);
				$genreId = $genre->getData('id');
				$args[0]->setData('genreId',$genreId);
				$args[1] = true;
				unset($args[0]->_errors);
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
				$stageId = $request->getUserVar('stageId');
				$groupId = 4; // Editores assistentes
				import('lib.pkp.classes.mail.MailTemplate');
				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO');
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
				$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
				$userDao->retrieve(
					'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
					array((string)'edit_aguardando_publicacao', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$submissionId)
				);
			break;
		return true;
		}
		if (!defined('SESSION_DISABLE_INIT')) {
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
}
