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

use Symfony\Component\HttpClient\HttpClient;

import('lib.pkp.classes.plugins.GenericPlugin');
require_once(dirname(__FILE__) . '/vendor/autoload.php');

class CspSubmissionPlugin extends GenericPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			// Insert new field into author metadata submission form (submission step 3) and metadata form
			HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'metadataFieldEdit'));
			HookRegistry::register('TemplateManager::fetch', array($this, 'additionalMetadataStep1'));
			HookRegistry::register('FileManager::downloadFile',array($this, 'fileManager_downloadFile'));
			HookRegistry::register('Mail::send', array($this,'mail_send'));

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

			HookRegistry::register('ArticleDAO::_fromRow', array($this, 'articleDAO_fromRow'));
		}
		return $success;
	}

	
	function mail_send($hookName, $args){
		$stageId = $this->article->getData('stageId');

		if (!empty($args[0]->emailKey) && $args[0]->emailKey == "REVIEW_REQUEST_ONECLICK"){			
			$body = $args[0]->_data['body'];
			
			preg_match("/href='(?P<url>.*)' class='submissionReview/",$body,$matches);
			$body = str_replace('{$submissionReviewUrlAccept}', $matches['url']."&accept=yes", $body);
			$body = str_replace('{$submissionReviewUrlReject}', $matches['url']."&accept=no", $body);
			$args[0]->_data['body'] = $body;
		}elseif ($stageId == 3 && !empty($args[0]->emailKey) && $args[0]->emailKey == "NOTIFICATION"){
			return true;
		}

	}

	function additionalMetadataStep1($hookName, $args) {
		//file_put_contents('/tmp/templates.txt', $args[1] . "\n", FILE_APPEND);
		$args[1];
		$templateMgr =& $args[0];
		if ($args[1] == 'submission/form/step1.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('step1.tpl'));
			
			return true;
		} elseif ($args[1] == 'submission/form/step3.tpl'){
			$args[4] = $templateMgr->fetch($this->getTemplateResource('step3.tpl'));
			
			return true;
		} elseif ($args[1] == 'controllers/wizard/fileUpload/form/submissionArtworkFileMetadataForm.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('submissionArtworkFileMetadataForm.tpl'));
			
			return true;
		} elseif($args[1] == 'controllers/grid/users/author/form/authorForm.tpl'){
			$args[4] = $templateMgr->fetch($this->getTemplateResource('authorForm.tpl'));
			
			return true;
		} elseif ($args[1] == 'controllers/modals/submissionMetadata/form/issueEntrySubmissionReviewForm.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('issueEntrySubmissionReviewForm.tpl'));

			return true;
		} elseif ($args[1] == 'controllers/grid/users/reviewer/form/advancedSearchReviewerForm.tpl') {
			$request = Application::getRequest();
			$submissionDAO = Application::getSubmissionDAO();
			$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
			$templateMgr->assign('title',$submission->getTitle(AppLocale::getLocale()));
			$args[4] = $templateMgr->fetch($this->getTemplateResource('advancedSearchReviewerForm.tpl'));

			return true;
		} elseif ($args[1] == 'controllers/wizard/fileUpload/form/fileUploadForm.tpl') {
			$args[0];
			$article = $args[0]->submission;
			$submissionProgress = $this->article->getData('submissionProgress');

			$request = Application::getRequest();
			$fileStage = $request->getUserVar('fileStage');

			if ($submissionProgress == 0 && $fileStage == 2){
				$templateMgr->assign('revisionOnly',false);
				$templateMgr->assign('isReviewAttachment',true);
				$templateMgr->assign('submissionFileOptions',[]);
			}			
		}elseif ($args[1] == 'reviewer/review/step1.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('reviewStep1.tpl'));
			
			return true;
		}elseif ($args[1] == 'reviewer/review/step3.tpl') {
			$args[4] = $templateMgr->fetch($this->getTemplateResource('reviewStep3.tpl'));
			
			return true;
		}elseif ($args[1] == 'controllers/grid/users/stageParticipant/addParticipantForm.tpl') {
			$request = Application::getRequest();
			$submissionId = $request->_requestVars["submissionId"];
			$template = new SubmissionMailTemplate($submissionId);

			$templateMgr->assign('message',$template->getBody(),AppLocale::getLocale());

			$args[4] = $templateMgr->fetch($this->getTemplateResource('addParticipantForm.tpl'));


			return true;
		}

		return false;
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
		// $output .= $smarty->fetch($this->getTemplateResource('RemovePrefixoTitulo.tpl'));
		
		if($this->sectionId == 5){
			$output .= $smarty->fetch($this->getTemplateResource('Revisao.tpl'));
		}
		
		if($this->sectionId == 4){					
			$output .= $smarty->fetch($this->getTemplateResource('Tema.tpl'));
			$output .= $smarty->fetch($this->getTemplateResource('CodigoTematico.tpl'));
		}

		$output .= $smarty->fetch($this->getTemplateResource('ConflitoInteresse.tpl'));
		$output .= $smarty->fetch($this->getTemplateResource('FonteFinanciamento.tpl'));
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
		$userVars[] = 'FonteFinanciamento';
		$userVars[] = 'FonteFinanciamentoQual';		
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
		$article->setData('FonteFinanciamento', $form->getData('FonteFinanciamento'));
		$article->setData('FonteFinanciamentoQual', $form->getData('FonteFinanciamentoQual'));		
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
		$form->setData('FonteFinanciamento', $article->getData('FonteFinanciamento'));				
		$form->setData('FonteFinanciamentoQual', $article->getData('FonteFinanciamentoQual'));			
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
		if($_POST['FonteFinanciamento'] == "yes"){
			$form->addCheck(new FormValidatorLength($form, 'FonteFinanciamentoQual', 'required', 'plugins.generic.CspSubmission.FonteFinanciamentoQual.Valid', '>', 0));			
		}		

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

				$sectionId = $this->article->getData('sectionId');
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
							'sectoin' => $this->article->getData('sectionTitle'),
							'max'     => $wordCount,
							'count'   => $contagemPalavras
						]);
						$args[0]->addError('genreId', $phrase);
					}
				}
				break;
			case 10: // Fotografia / Imagem satélite (Resolução mínima de 300 dpi)
				if (($_FILES['uploadedFile']['type'] <> 'image/bmp') /*bmp*/
				and ($_FILES['uploadedFile']['type'] <> 'image/tiff') /*tiff*/) {
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
			$request = Application::getRequest();
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

	public function articleDAO_fromRow($hookName, $args)
	{
		$this->article = $args[0];
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
