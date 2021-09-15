<?php
import('plugins.generic.cspSubmission.class.AbstractPlugin');

class MailCsp extends AbstractPlugin
{
	function send($args)
	{
		$request = \Application::get()->getRequest();
		$stageId = $request->getUserVar('stageId');
		$submissionId = $request->getUserVar('submissionId');
		$locale = AppLocale::getLocale();
		$userDao = DAORegistry::getDAO('UserDAO');

		if ($args[0]->emailKey == "SUBMISSION_ACK_NOT_USER") {
			$args[0]->_data["body"] = str_replace('{$coAuthorName}', $args[0]->_data["recipients"][0]["name"], $args[0]->_data["body"]);
		}
		if ($args[0]->emailKey == "COPYEDIT_REQUEST") {
			$context = $request->getContext();
			$userGroupId = 8; /* Id da Secretaria */
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$users = $userGroupDao->getUsersById($userGroupId, $context->getId());
			while ($user = $users->next()) {
				$args[0]->_data["recipients"][] =  array("name" => $user->getFullName(), "email" => $user->getEmail());
			}
		}
		/** Quando submissão é rejeitada, não envia email para autor imediatamente */
		if ($args[0]->emailKey == "EDITOR_DECISION_DECLINE" or $args[0]->emailKey == "EDITOR_DECISION_INITIAL_DECLINE") {
			import('plugins.generic.cspSubmission.class.CspDeclinedSubmissions');
			(new CspDeclinedSubmissions())->saveDeclinedSubmission($submissionId, $args[0]);
			return true;
		}
		if ($stageId == 3) {
			/** Quando autor submete nova versão, secretaria é notificada e status é alterado*/
			if ($args[0]->emailKey == "REVISED_VERSION_NOTIFY") { 
				unset($args[0]->_data["recipients"]);
				$userGroupId = 8; // Secretaria
				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO');
				$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submissionId, $stageId, $userGroupId);
				while ($user = $users->next()) {
					$args[0]->_data["recipients"][] =  array("name" => $user->getFullName(), "email" => $user->getEmail());
				}
				$userDao = DAORegistry::getDAO('UserDAO');
				$userDao->retrieve(
					'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
					array((string)'ava_aguardando_secretaria', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$submissionId)
				);
			}

			$recipient = $userDao->getUserByEmail($args[0]->_data["recipients"][0]["email"]);
			if ($recipient) {
				$context = $request->getContext();
				$isManager = $recipient->hasRole(array(ROLE_ID_MANAGER), $context->getId());
				/* Se o destinatário for editor chefe, status é alterado para "Consulta ao editor chefe" */
				if ($isManager) {
					$userDao->retrieve(
						'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
						array((string)'ava_consulta_editor_chefe', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$submissionId)
					);
					$args[0]->_data["recipients"][0]["email"] = "noreply@fiocruz.br";
				}
			}
		}
		if ($request->getRequestedPage() == "reviewer") {
			if ($request->getUserVar('step') == 1) {
				return true;
			}
			if ($request->getUserVar('step') == 3) {
				return true;
			}
			if ($request->getUserVar('step') == 4) {

				$path = $request->getRequestPath();
				$pathItens = explode('/', $path);
				$submissionId = $pathItens[6];
				$submissionDAO = Application::getSubmissionDAO();
				$submission = $submissionDAO->getById($submissionId);
				$submissionIdCsp = $submission->getData('codigoArtigo');

				$userDao = DAORegistry::getDAO('UserDAO');
				$reviewer = $userDao->getUserByEmail($args[0]->_data["from"]["email"]);
				$reviewerName = $reviewer->getLocalizedGivenName();
				$tempId = rand(10, 100);

				import('lib.pkp.classes.file.TemporaryFileManager');
				$temporaryFileManager = new TemporaryFileManager();
				$temporaryBasePath = $temporaryFileManager->getBasePath();

				$date = getDate();
				$timestamp = strtotime('today');
				$dateFormatLong = Config::getVar('general', 'date_format_long');
				$dateFormatLong = strftime($dateFormatLong, $timestamp);

				$strings = ['[NOME]', '[IDARTIGO]', '[ANO]', '[DATA]'];
				$replaces = [$reviewerName, $submissionIdCsp, $date['year'], $dateFormatLong];

				$this->replaceStringOdtFile($temporaryBasePath . $tempId, 'files/usageStats/declaracoes/declaracao_parecer.odt', $temporaryBasePath . $tempId . '.odt', $strings, $replaces);
				$temporaryFileManager->rmtree($temporaryBasePath . $tempId);

				$converter = new NcJoes\OfficeConverter\OfficeConverter($temporaryBasePath . $tempId . '.odt');
				$converter->convertTo('declaracao_parecer' . $tempId . '.pdf');

				import('lib.pkp.classes.file.FileManager');
				$fileManager = new FileManager();
				$fileManager->deleteByPath($temporaryBasePath . $tempId . '.odt');

				$args[0]->AddAttachment($temporaryBasePath . 'declaracao_parecer' . $tempId . '.pdf', 'declaracao_parecer' . $tempId . '.pdf', 'application/pdf');
			}
		}

		if ($stageId == 4 && strpos($args[0]->params["notificationContents"], "aprov")) {
			$periodico = $args[0]->params["contextName"];

			$submissionDAO = Application::getSubmissionDAO();
			$submission = $submissionDAO->getById($submissionId);
			$publication = $submission->getCurrentPublication();
			$titulo = $publication->getLocalizedTitle($locale);

			$authorDao = DAORegistry::getDAO('AuthorDAO');
			$primaryContact = $authorDao->getById($publication->getData('primaryContactId'));

			$autorCorrespondencia = $primaryContact->getLocalizedGivenName($locale) . " " . $primaryContact->getLocalizedFamilyName($locale);
			$authors = $publication->getData('authors');
			foreach ($authors as $author) {
				if ($publication->getData('primaryContactId') <> $author->getData('id')) {
					$coAutores[] = $author->getLocalizedFamilyName($locale);
				}
			}

			$timestamp = strtotime('today');
			$dateFormatLong = Config::getVar('general', 'date_format_long');
			$dateFormatLong = strftime($dateFormatLong, $timestamp);

			$strings = ['[AUTOR_CORRESPONDENCIA]', '[PERIODICO]', '[CO_AUTORES]', '[TITULO]', '[DATA]'];
			$replaces = [$autorCorrespondencia, $periodico, implode(',', $coAutores), $titulo, $dateFormatLong];

			$tempId = rand(10, 100);

			import('lib.pkp.classes.file.TemporaryFileManager');
			$temporaryFileManager = new TemporaryFileManager();
			$temporaryBasePath = $temporaryFileManager->getBasePath();

			$this->replaceStringOdtFile($temporaryBasePath . $tempId, 'files/usageStats/declaracoes/declaracao_aprovacao.odt', $temporaryBasePath . $tempId . '.odt', $strings, $replaces);

			$temporaryFileManager->rmtree($temporaryBasePath . $tempId);

			$converter = new NcJoes\OfficeConverter\OfficeConverter($temporaryBasePath . $tempId . '.odt');
			$converter->convertTo('declaracao_aprovacao' . $tempId . '.pdf');

			import('lib.pkp.classes.file.FileManager');
			$fileManager = new FileManager();
			$fileManager->deleteByPath($temporaryBasePath . $tempId . '.odt');

			$args[0]->AddAttachment($temporaryBasePath . 'declaracao_aprovacao' . $tempId . '.pdf', 'declaracao_aprovacao' . $tempId . '.pdf', 'application/pdf');

			$userDao->retrieve(
				'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
				array((string)'ed_text_para_revisao_traducao', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$submissionId)
			);
		}
		if ($stageId == 5 && strpos($args[0]->params["notificationContents"], "Prova de prelo")) {
			$periodico = $args[0]->params["contextName"];

			$submissionDAO = Application::getSubmissionDAO();
			$submission = $submissionDAO->getById($submissionId);
			$publication = $submission->getCurrentPublication();
			$titulo = $publication->getLocalizedTitle($locale);

			$authorDao = DAORegistry::getDAO('AuthorDAO');
			$primaryContact = $authorDao->getById($publication->getData('primaryContactId'));
			$autorCorrespondencia = $primaryContact->getLocalizedGivenName($locale) . " " . $primaryContact->getLocalizedFamilyName($locale);
			$authors = $publication->getData('authors');
			foreach ($authors as $author) {
				$autores[] = $author->getLocalizedFamilyName($locale);
			}
			$timestamp = strtotime('today');
			$dateFormatLong = Config::getVar('general', 'date_format_long');
			$dateFormatLong = strftime($dateFormatLong, $timestamp);

			$strings = ['[AUTOR_CORRESPONDENCIA]', '[PERIODICO]', '[AUTORES]', '[TITULO]', '[DATA]'];
			$replaces = [$autorCorrespondencia, $periodico, implode(',', $autores), $titulo, $dateFormatLong];

			$tempId = rand(10, 100);

			import('lib.pkp.classes.file.TemporaryFileManager');
			$temporaryFileManager = new TemporaryFileManager();
			$temporaryBasePath = $temporaryFileManager->getBasePath();

			$this->replaceStringOdtFile($temporaryBasePath . $tempId, 'files/usageStats/declaracoes/aprovacao_prova_prelo.odt', $temporaryBasePath . $tempId . '.odt', $strings, $replaces);

			$temporaryFileManager->rmtree($temporaryBasePath . $tempId);

			$converter = new NcJoes\OfficeConverter\OfficeConverter($temporaryBasePath . $tempId . '.odt');
			$converter->convertTo('aprovacao_prova_prelo' . $tempId . '.pdf');

			import('lib.pkp.classes.file.FileManager');
			$fileManager = new FileManager();
			$fileManager->deleteByPath($temporaryBasePath . $tempId . '.odt');

			$args[0]->AddAttachment($temporaryBasePath . 'aprovacao_prova_prelo' . $tempId . '.pdf', 'aprovacao_prova_prelo' . $tempId . '.pdf', 'application/pdf');
			$args[0]->AddAttachment('files/usageStats/declaracoes/cessao_direitos_autorais.pdf', 'cessao_direitos_autorais.pdf', 'application/pdf');
			$args[0]->AddAttachment('files/usageStats/declaracoes/termos_condicoes.pdf', 'termos_condicoes.pdf', 'application/pdf');

			$userDao->retrieve(
				'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
				array((string)'edit_em_prova_prelo', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$submissionId)
			);
		}
		$args[0]->_data["from"]["name"] = "Cadernos de Saúde Pública";
		$args[0]->_data["from"]["email"] = "noreply@fiocruz.br";
		$args[0]->_data["replyTo"][0]["name"] =  "Cadernos de Saúde Pública";
		$args[0]->_data["replyTo"][0]["email"] = "noreply@fiocruz.br";

		if (!$args[0]->submission) {
			$submissionDAO = Application::getSubmissionDAO();
			$submissionId = $submissionId == "" ? $request->getUserVar('submissionId') : $submissionId;
			$submission = $submissionDAO->getById($submissionId);
		} else {
			$submission = $args[0]->submission;
		}
		if ($submission) {
			$submissionIdCSP = $submission->getData('codigoArtigo');
			$args[0]->_data["body"] = str_replace('{$submissionIdCSP}', $submissionIdCSP, $args[0]->_data["body"]);
			$args[0]->_data["subject"] = str_replace('{$submissionIdCSP}', $submissionIdCSP, $args[0]->_data["subject"]);
		}
	}
	function replaceStringOdtFile($extractFolder, $inputFile, $zipOutputFile, $string, $replaces)
	{
		$zip = new ZipArchive;
		if ($zip->open($inputFile) === true) {
			$zip->extractTo($extractFolder);
			$zip->close();
		}
		$source = file_get_contents($extractFolder . '/content.xml');
		$source = str_replace($string, $replaces, $source);
		file_put_contents($extractFolder . '/content.xml', $source);
		if (!extension_loaded('zip') || !file_exists($extractFolder)) {
			return false;
		}
		if (!$zip->open($zipOutputFile, ZIPARCHIVE::CREATE)) {
			return false;
		}
		if (is_dir($extractFolder) === true) {
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractFolder), RecursiveIteratorIterator::SELF_FIRST);
			foreach ($files as $file) {
				$file = str_replace('\\', DIRECTORY_SEPARATOR, $file);
				if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..'))) {
					continue;
				}
				if (is_dir($file) === true) {
					$dirName = str_replace($extractFolder . DIRECTORY_SEPARATOR, '', $file . DIRECTORY_SEPARATOR);
					$zip->addEmptyDir($dirName);
				} else if (is_file($file) === true) {
					$fileName = str_replace($extractFolder . DIRECTORY_SEPARATOR, '', $file);
					$zip->addFromString($fileName, file_get_contents($file));
				}
			}
		} else if (is_file($extractFolder) === true) {
			$zip->addFromString(basename($extractFolder), file_get_contents($extractFolder));
		}
		return $zip->close();
	}
}
