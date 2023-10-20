<?php

/**
 * @file plugins/generic/CspSubmission/CspSubmissionPlugin.php
 *
 * Copyright (c) 2014-2023 LibreCode Coop
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CspSubmissionPlugin
 * @ingroup plugins_generic_CspSubmission
 *
 * @brief CspSubmission plugin class
 */

namespace APP\plugins\generic\CspSubmission;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use APP\core\Application;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\submission\GenreDAO;
use PKP\core\JSONMessage;
use APP\core\Services;
use PKP\submissionFile\SubmissionFile;
use APP\facades\Repo;
require_once(dirname(__FILE__) . '/vendor/autoload.php');

class CspSubmissionPlugin extends GenericPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) { 
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled()) {

			$request = Application::get()->getRequest();
			$url = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/style.css';
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->addStyleSheet('CspSubmission', $url, ['contexts' => 'backend']);

			// Hook::add('userdao::_getbyusername', array($this, 'userdao__getbyusername'));

			// Hook::add('advancedsearchreviewerform::validate', array($this, 'advancedsearchreviewerform_validate'));

			// Hook::add('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'metadataFieldEdit'));
			// Hook::add('Templates::Article::Main::Csp', array($this, 'TemplatesCsp_articleMain'));
			// Hook::add('TemplateManager::fetch', array($this, 'TemplateManager_fetch'));
			// Hook::add('TemplateManager::display',array(&$this, 'TemplateManagerCsp_display'));
			// Hook::add('FileManager::downloadFile',array($this, 'fileManager_downloadFile'));
			// Hook::add('Mail::send', array($this,'MailCsp_send'));

			// Hook::add('Submission::getBackendListProperties::properties', array($this, 'SubmissionCsp_getBackendListProperties'));
			// Hook::add('Submission::getMany::queryObject', array($this,'SubmissionCsp_getManyQueryObject'));
			// Hook::add('Submission::getMany::queryBuilder', array($this,'SubmissionCsp_getManyQueryBuilder'));
			// Hook::add('Submission::delete', array($this, 'SubmissionCsp_delete'));
			// Hook::add('Submission::add', array($this, 'SubmissionCsp_add'));

			// Hook::add('APIHandler::endpoints', array($this,'APIHandler_endpoints'));

			// Hook::add('authorform::initdata', array($this, 'AuthorformCsp_initData'));
			// Hook::add('authorform::readuservars', array($this, 'AuthorformCsp_readUserVars'));
			// Hook::add('authorform::execute', array($this, 'AuthorformCsp_execute'));

			// Hook::add('submissionsubmitstep4form::execute', array($this, 'metadataExecuteStep4'));

			// Hook::add('submissionsubmitstep3form::Constructor', array($this, 'SubmissionSubmitStep3FormCsp_constructor'));
			// Hook::add('submissionsubmitstep3form::initdata', array($this, 'SubmissionSubmitStep3FormCsp_initData'));
			// Hook::add('submissionsubmitstep3form::readuservars', array($this, 'SubmissionSubmitStep3FormCsp_readUserVars'));
			// Hook::add('submissionsubmitstep3form::execute', array($this, 'SubmissionSubmitStep3FormCsp_execute'));

			// Hook::add('quicksubmitform::validate', array($this, 'QuickSubmitFormCsp_validate'));
			// Hook::add('quicksubmitform::readuservars', array($this, 'QuickSubmitFormCsp_readuservars'));
			// Hook::add('quicksubmitform::execute', array($this, 'QuickSubmitFormCsp_execute'));

			// Hook::add('submissionsubmitstep2form::Constructor', array($this, 'SubmissionSubmitStep2FormCsp_constructor'));

			// // Consider the new field for ArticleDAO for storage
			// Hook::add('articledao::getAdditionalFieldNames', array($this, 'metadataReadUserVars'));

			Hook::add('SubmissionFile::validate', [$this, 'submissionFileValidate']);
			

			// Hook::add('User::getMany::queryObject', array($this, 'pkp_services_pkpuserservice_getmany'));
			// Hook::add('UserDAO::_returnUserFromRowWithData', array($this, 'userDAO__returnUserFromRowWithData'));
			// Hook::add('User::getProperties::values', array($this, 'user_getProperties_values'));

			// // This hook is used to register the components this plugin implements to
			// // permit administration of custom block plugins.
			// Hook::add('LoadComponentHandler', array($this, 'LoadComponentHandler'));

			// Hook::add('userstageassignmentdao::_filterusersnotassignedtostageinusergroup', array($this, 'userstageassignmentdao_filterusersnotassignedtostageinusergroup'));

			// Hook::add('addparticipantform::execute', array($this, 'addparticipantformExecute'));

			// Hook::add('Schema::get::publication', array($this, 'PublicationCsp_addToSchema'));
			// Hook::add('Publication::add', array($this, 'PublicationCsp_add'));
			// Hook::add('Publication::edit', array($this, 'PublicationCsp_edit'));
			// Hook::add('Publication::publish', array($this, 'PublicationCsp_publish'));
			// Hook::add('Publication::unpublish', array($this, 'PublicationCsp_unpublish'));

			// Hook::add('reviewergridhandler::initfeatures', array($this, 'reviewergridhandler_initfeatures'));

			// Hook::add('submissionfiledaodelegate::getAdditionalFieldNames', array($this, 'submissionfiledaodelegateAdditionalFieldNames'));

			// Hook::add('submissionfilesmetadataform::readuservars', array($this, 'SubmissionFilesMetadataFormCsp_readUserVars'));
			// Hook::add('submissionfilesmetadataform::execute', array($this, 'SubmissionFilesMetadataFormCsp_execute'));

			// // Displays extra fields in the workflow metadata area
			// Hook::add('Form::config::after', array($this, 'formConfigAfter'));

			// Hook::add('newreviewroundform::validate', array($this, 'newreviewroundform_validate'));

			// Hook::add('userdao::getAdditionalFieldNames', array($this, 'UserdaoCsp_getAdditionalFieldNames'));

			// Hook::add('EditorAction::recordDecision', array($this, 'EditorActionCsp_recordDecision'));

			// Hook::add('Schema::get::author', array($this, 'SchemaGetAuthorCsp_getAuthor'));

			// Hook::add('managefinaldraftfilesform::validate', array($this, 'managefinaldraftfilesformvalidate'));

			// Hook::add('queryform::readuservars', array($this, 'QueryFormCsp_readUservars'));

			// Hook::add('pkp\services\pkpsubmissionservice::_getmany', array($this, 'PkpSubmissionServiceCsp_getmany'));




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

	public function submissionFileValidate($hookName, $args) {
		if($args[1] instanceof \submissionFile){

			$file = Services::get('file')->get($args[1]->_data["fileId"]);

			$request = \Application::get()->getRequest();
			$submissionId = $request->getUserVar('submissionId');
			$context = $request->getContext();
			$genreId = $request->getUserVar('genreId');
			$genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
			$genre = $genreDao->getById($genreId, $context->getId());
			$genreKey = $genre->getKey();
			$mimetype = $args[1]->getData('mimetype');
			
			if(in_array($genreKey, ['SUBMISSION', 'TABELA_QUADRO', 'LEGENDAS'])){
				if (!in_array($mimetype,
				['application/msword', 'application/wps-office.doc', /*Doc*/
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/wps-office.docx', /*docx*/
				'application/vnd.oasis.opendocument.text', /*odt*/
				'application/rtf'] /*rtf*/
				)) {
					$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.invalidFormat.AticleBody')];
					return;
				}
				if($genreKey == 'SUBMISSION'){
					$submissionFiles = Repo::submissionFile()
					->getCollector()
					->filterBySubmissionIds([$submissionId])
					->getMany();
					foreach ($submissionFiles as $submissionFile) {
						$submissionFileGenre = $genreDao->getById($submissionFile->getData('genreId'), $context->getId());
						if ($submissionFileGenre && $submissionFileGenre->getKey() == 'SUBMISSION'){
							$args[0]['genreId'] = [__('plugins.generic.CspSubmission.submission.bodyTextFile.Twice')];
							return;
						}
					}

					$formato = explode('.', $args[1]->getLocalizedData('name'));
					$formato = trim(strtolower(end($formato)));

					$readers = array('docx' => 'Word2007', 'odt' => 'ODText', 'rtf' => 'RTF', 'doc' => 'ODText');
					$doc = \PhpOffice\PhpWord\IOFactory::load('files/'.$args[1]->getData('path'), $readers[$formato]);
					$html = new \PhpOffice\PhpWord\Writer\HTML($doc);
					$contagemPalavras = str_word_count(strip_tags($html->getWriterPart('Body')->write()));

					$submission = Repo::submission()->get((int) $submissionId);
					$publication = Repo::publication()->get((int) $submission->getData('currentPublicationId'));
					$section = Repo::section()->get((int) $publication->getData('sectionId'));
					$sectionAbbrev = $section->getAbbrev($args[4]);

					switch($sectionAbbrev) {
						case 'ARTIGO':
						case 'DEBATE':
						case 'QUEST_METOD':
						case 'ENTREVISTA':
							if ($contagemPalavras > 6000) {
								$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
									'sectoin' => $section->getTitle($publication->getData('locale')),
									'max'     => 6000,
									'count'   => $contagemPalavras
									])
								];
							}
						break;
						case 'EDITORIAL':
						case 'COM_BREVE':
							if ($contagemPalavras > 2000) {
								$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
									'sectoin' => $section->getTitle($publication->getData('locale')),
									'max'     => 2000,
									'count'   => $contagemPalavras
									])
								];
							}
						break;
						case 'PERSPECT':
							if ($contagemPalavras > 2200) {
								$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
									'sectoin' => $section->getTitle($publication->getData('locale')),
									'max'     => 2200,
									'count'   => $contagemPalavras
									])
								];
							}
						break;
						case 'REVISAO':
						case 'ENSAIO':
							if ($contagemPalavras > 8000) {
								$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
									'sectoin' => $section->getTitle($publication->getData('locale')),
									'max'     => 8000,
									'count'   => $contagemPalavras
									])
								];
							}
						break;
						case 'ESP_TEMATICO':
							if ($contagemPalavras > 4000) {
								$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
									'sectoin' => $section->getTitle($publication->getData('locale')),
									'max'     => 4000,
									'count'   => $contagemPalavras
									])
								];
							}
						break;
						case 'CARTA':
						case 'COMENTARIOS':
						case 'RESENHA':
							if ($contagemPalavras > 1300) {
								$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
									'sectoin' => $section->getTitle($publication->getData('locale')),
									'max'     => 1300,
									'count'   => $contagemPalavras
									])
								];
							}
						break;
						case 'OBTUARIO':
							if ($contagemPalavras > 1000) {
								$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
									'sectoin' => $section->getTitle($publication->getData('locale')),
									'max'     => 1000,
									'count'   => $contagemPalavras
									])
								];
							}
						break;
						case 'ERRATA':
							if ($contagemPalavras > 700) {
								$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
									'sectoin' => $section->getTitle($publication->getData('locale')),
									'max'     => 70,
									'count'   => $contagemPalavras
									])
								];
							}
						break;
					}
				}

			}

			if(in_array($genreKey, ['IMAGE'])){
				if (!in_array($mimetype, ['image/bmp', 'image/tiff', 'image/png', 'image/jpeg'])) {
					$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.invalidFormat.Image')];
				}
			}
		}
	}
}
