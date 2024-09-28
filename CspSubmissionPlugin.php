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
use APP\core\Services;
use APP\facades\Repo;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldRadioInput;
use PKP\security\Role;
use NcJoes\OfficeConverter\OfficeConverter;
use PKP\facades\Locale;
use PKP\controllers\grid\users\stageParticipant\form\AddParticipantForm;
use PKP\core\PKPApplication;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\security\Validation;
use PKP\core\Core;
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

			Hook::add('SubmissionFile::validate', [$this, 'submissionFileValidate']);
			Hook::add('SubmissionFile::edit', [$this, 'submissionFileEdit']);
			Hook::add('Schema::get::submission', [$this, 'schemaGetSubmission']);
			Hook::add('Form::config::before', [$this, 'formConfigBefore']);
			Hook::add('Submission::validateSubmit', [$this, 'submissionValidateSubmit']);
			Hook::add('Submission::edit', [$this, 'submissionEdit']);
			Hook::add('Schema::get::publication', [$this, 'schemaGetPublication']);
			Hook::add('TemplateManager::display', [$this, 'templateManagerDisplay']);
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
			$request = Application::get()->getRequest();
			$submissionId = $request->getUserVar('submissionId');
			$context = $request->getContext();
			$genreId = $request->getUserVar('genreId');
			$genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
			$genre = $genreDao->getById($genreId, $context->getId());
			$genreKey = $genre->getKey();
			$mimetype = $args[1]->getData('mimetype');
			
			if(in_array($genreKey, ['SUBMISSION', 'TABELA_QUADRO', 'TRANSCRIPTS', 'MATERIAL_SUPLEMENTAR'])){
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
							$args[0]['genreId'] = [__('plugins.generic.CspSubmission.submission.bodyTextFile.limit')];
							return;
						}
					}

					$formato = explode('.', $args[1]->getData('path'));
					$formato = trim(strtolower(end($formato)));

					$converter = new OfficeConverter('files/'.$args[1]->getData('path'));
					$htmlFile = $converter->convertTo(str_replace($formato, 'html', 'files/'.$args[1]->getData('path')));
					$htmlContent = file_get_contents($htmlFile);
					$htmlContent = preg_replace("/<img[^>]+\>/i", "(image) ", $htmlContent);
					file_put_contents($htmlFile, $htmlContent);
					$doc = \PhpOffice\PhpWord\IOFactory::load($htmlFile, 'HTML');
					$html = new \PhpOffice\PhpWord\Writer\HTML($doc);
					$contagemPalavras = str_word_count(strip_tags($html->getWriterPart('Body')->write()));
					unlink($htmlFile);

					$submission = Repo::submission()->get((int) $submissionId);
					$publication = Repo::publication()->get((int) $submission->getData('currentPublicationId'));
					$section = Repo::section()->get((int) $publication->getData('sectionId'));
					$sectionAbbrev = $section->getAbbrev($args[4]);

					switch($sectionAbbrev) {
						case 'ARTIGO':
						case 'DEBATE':
						case 'QUEST_METOD':
						case 'ENTREVISTA':
							if ($contagemPalavras > 6300) {
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
							if ($contagemPalavras > 2100) {
								$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
									'sectoin' => $section->getTitle($publication->getData('locale')),
									'max'     => 2000,
									'count'   => $contagemPalavras
									])
								];
							}
						break;
						case 'PERSPECT':
							if ($contagemPalavras > 2310) {
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
							if ($contagemPalavras > 8400) {
								$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
									'sectoin' => $section->getTitle($publication->getData('locale')),
									'max'     => 8000,
									'count'   => $contagemPalavras
									])
								];
							}
						break;
						case 'ESP_TEMATICO':
							if ($contagemPalavras > 4200) {
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
							if ($contagemPalavras > 1365) {
								$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
									'sectoin' => $section->getTitle($publication->getData('locale')),
									'max'     => 1300,
									'count'   => $contagemPalavras
									])
								];
							}
						break;
						case 'OBTUARIO':
							if ($contagemPalavras > 1050) {
								$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
									'sectoin' => $section->getTitle($publication->getData('locale')),
									'max'     => 1000,
									'count'   => $contagemPalavras
									])
								];
							}
						break;
						case 'ERRATA':
							if ($contagemPalavras > 735) {
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
				if($genreKey == 'TRANSCRIPTS'){
					$submissionFiles = Repo::submissionFile()
					->getCollector()
					->filterBySubmissionIds([$submissionId])
					->getMany();
					foreach ($submissionFiles as $submissionFile) {
						$submissionFileGenre = $genreDao->getById($submissionFile->getData('genreId'), $context->getId());
						if ($submissionFileGenre && $submissionFileGenre->getKey() == 'TRANSCRIPTS'){
							$args[0]['genreId'] = [__('plugins.generic.CspSubmission.submission.transcriptsFile.limit')];
							return;
						}
					}
				}

			}

			if(in_array($genreKey, ['IMAGE'])){
				if (!in_array($mimetype, ['image/bmp', 'image/tiff', 'image/png', 'image/jpeg','image/svg+xml'])) {
					$args[0]['genreId'] = [__('plugins.generic.CspSubmission.SectionFile.invalidFormat.Image')];
				}
			}
		}
	}

	public function submissionFileEdit(string $hookName, array $args){
		$request = Application::get()->getRequest();
		$submission = Repo::submission()->get((int) $args[0]->getData('submissionId'));
		$locale = $args[0]->getData('locale');
		$context = $request->getContext();
		$primaryLocale = $context->getData('primaryLocale');

		if($request->_requestVars["revisedFileId"]){
			$newName = $args[1]->getData('name',$args[1]->getData('locale'));
			$args[0]->setData('name', $newName,  $locale);
			$newName = $args[1]->getData('name',$primaryLocale);
			$args[0]->setData('name', $newName,  $primaryLocale);
			return true;
		}
		// Renomeia arquivo inserido na etapa de envio de arquivos da submissão, atribuindo o nome do gênero do arquivo
		if($submission->getData('submissionProgress') == "start" && !$args[2]["notRename"]){
			$genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
			$genre = $genreDao->getById($args[0]->getData('genreId'), $context->getId());
			$genreNameLocale = $genre->getName($locale);
			$genreNamePrimaryLocale = $genre->getName($primaryLocale);
			$submissionFiles = Repo::submissionFile()
			->getCollector()
			->filterBySubmissionIds([$args[0]->getData('submissionId')])
			->filterByGenreIds([$args[0]->getData('genreId')])
			->getMany()
			->toArray();
			$args[0]->setData('name', $genreNameLocale, $locale);
			$args[0]->setData('name', $genreNamePrimaryLocale, $primaryLocale);
			if(in_array($genre->getData('key'),['IMAGE','SUBMISSION_TABLE','MATERIAL_SUPLEMENTAR'])){
				$args[0]->setData('name', $genreNameLocale . ' ' .(count($submissionFiles)+1), $locale);
				$args[0]->setData('name', $genreNamePrimaryLocale . ' ' .(count($submissionFiles)+1), $primaryLocale);
			}
		}
	}

	public function schemaGetSubmission(string $hookName, array $args){
		$schema = $args[0]; /** @var stdClass */
		$schema->properties->consideracoesEticas = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];
		$schema->properties->conflitoInteresse = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];
		$schema->properties->agradecimentos = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];
		return false;
    }

    public function schemaGetPublication(string $hookName, array $args){
		$schema = $args[0]; /** @var stdClass */
		$schema->properties->codigoFasciculoTematico = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];
		$schema->properties->codigoArtigoRelacionado = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];
		$schema->properties->espacoTematico = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];
		$schema->properties->submissionIdCSP = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];
		return false;
    }

	public function formConfigBefore($hookName, $args) {
		$context = Application::get()->getRequest()->getContext();
		$request = Application::get()->getRequest();
		if($request->_router->_page == 'submission'){
			if($args->id == "startSubmission"){
				$args->removeField('title');
			}

			if(in_array($args->id, ['titleAbstract', 'submissionFile', 'forTheEditors', 'commentsForTheEditors', 'commentsForTheEditors', 'contributor'])){
				$submissionId = $request->getUserVar('id');
				$submission = Repo::submission()->get((int) $submissionId);
				$publication = Repo::publication()->get((int) $submissionId);
				$section = Repo::section()->get((int) $publication->getData('sectionId'));
				$sectionAbbrev = $section->getAbbrev($context->getData('primaryLocale'));

				if($args->id == "titleAbstract"){
					$title = $args->getField('title');
					$title->description = __('plugins.generic.CspSubmission.submission.title.description');

					if(in_array($sectionAbbrev, ['ARTIGO', 'COM_BREVE', 'DEBATE', 'ENSAIO', 'QUEST_METOD', 'REVISAO'])) {
						$keywords = $args->getField('keywords');
						$keywords->isRequired = true;
					}

					if($sectionAbbrev == "ESP_TEMATICO") {
						$args->addField(new FieldText('espacoTematico', [
							'label' => __('plugins.generic.CspSubmission.espacoTematico'),
							'groupId' => 'default',
							'isRequired' => true,
							'size' => 'medium',
							'value' => $context->getData('espacoTematico'),
						]));
					}

					if($sectionAbbrev == "COMENTARIOS") {
						$args->addField(new FieldText('codigoArtigoRelacionado', [
							'label' => __('plugins.generic.CspSubmission.codigoArtigoRelacionado'),
							'groupId' => 'default',
							'isRequired' => true,
							'size' => 'small',
							'value' => $context->getData('codigoArtigoRelacionado'),
						]));
					}

					if($sectionAbbrev == "CARTA") {
						$args->removeField('abstract');
						$args->removeField('keywords');
					}

					$args->addField(new FieldText('codigoFasciculoTematico', [
						'label' => __('plugins.generic.CspSubmission.codigoFasciculoTematico'),
						'description' => __('plugins.generic.CspSubmission.codigoFasciculoTematico.description'),
						'groupId' => 'default',
						'isRequired' => false,
						'size' => 'medium',
						'value' => $context->getData('codigoFasciculoTematico'),
					]));
				}
				if($args->id == "commentsForTheEditors"){
					$args->addField(new FieldRadioInput('conflitoInteresse', [
						'label' => __('plugins.generic.CspSubmission.conflitoInteresse'),
						'groupId' => 'default',
						'isRequired' => true,
						'type' => 'radio',
						'size' => 'small',
						'options' => [
							['value' => 'S', 'label' => __('common.yes')],
							['value' => 'N', 'label' => __('common.no')],
						],
						'value' => $context->getData('conflitoInteresse'),
					]));
					$args->addField(new FieldRadioInput('consideracoesEticas', [
						'label' => __('plugins.generic.CspSubmission.consideracoesEticas'),
						'groupId' => 'default',
						'isRequired' => true,
						'type' => 'radio',
						'size' => 'small',
						'options' => [
							['value' => 'S', 'label' => __('plugins.generic.CspSubmission.consideracoesEticas.checkbox.yes')],
							['value' => 'N', 'label' => __('plugins.generic.CspSubmission.consideracoesEticas.checkbox.no')],
						],
						'value' => $context->getData('consideracoesEticas'),
					]));
					$args->addField(new FieldTextarea('agradecimentos', [
						'label' => __('plugins.generic.CspSubmission.agradecimentos'),
						'groupId' => 'default',
						'isRequired' => false,
						'size' => 'normal',
						'value' => $context->getData('agradecimentos'),
					]));
				}

				if($args->id == "contributor"){
					$orcid = $args->getField('orcid');
					$orcid->isRequired = true;

					$familyName = $args->getField('familyName');
					$familyName->isRequired = true;

					$affiliation = $args->getField('affiliation');
					$affiliation->description = __('user.affiliation.description');
					$affiliation->size = "large";

					$args->removeField('preferredPublicName');
					$args->removeField('url');
					$args->removeField('userGroupId');

					$authorgroup = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_AUTHOR], $context->getId(), true)->first();
					$args->addHiddenField('userGroupId', $authorgroup->getData('id'));
				}
			}
		}
	}

	public function submissionValidateSubmit($hookName, $args) {
		$locale = $args[1]->getData('locale');
		$context = Application::get()->getRequest()->getContext();
        $publication = $args[1]->getCurrentPublication();
		$keywords = count($publication->getData('keywords'));
		$section = Repo::section()->get((int) $publication->getData('sectionId'));
		$sectionAbbrev = $section->getAbbrev($context->getData('primaryLocale'));
		if(in_array($sectionAbbrev, ['ARTIGO', 'COM_BREVE', 'DEBATE', 'ENSAIO', 'QUEST_METOD', 'REVISAO'])) {
			if (!$keywords) {
				$args[0]["keywords"] = [$locale => [__('validator.required')]];
			}elseif(count($publication->getData('keywords', $locale)) < 3 or count($publication->getData('keywords', $locale)) > 5){
				$args[0]["keywords"] = [$locale => [__('plugins.generic.CspSubmission.keywords.Notification')]];
			}
		}
	}

	public function submissionEdit($hookName, $args) {
		if($args[0]->getData('submissionProgress') == "" && $args[1]->getData('submissionProgress') == "start"){
			// Atribui código CSP à nova submissão
			$contextDao = Application::getContextDao();
			$result = $contextDao->retrieve(
				<<<QUERY
				SELECT CONCAT(LPAD(count(*)+1, CASE WHEN count(*) > 9999 THEN 5 ELSE 4 END, 0), '/', DATE_FORMAT(now(), '%y')) code
				FROM submissions
				WHERE YEAR(date_submitted) = YEAR(now())
				QUERY
			);
			$row = $result->current();
			$params['submissionIdCSP'] = $row->code;
			$publication = $args[0]->getCurrentPublication();
			Repo::publication()->edit($publication, $params);

			// Renomeia arquivos de nova submissão com código CSP
			$submissionFiles = Repo::submissionFile()
				->getCollector()
				->filterBySubmissionIds([$args[0]->getData('id')])
				->getMany()
				->toArray();
			$primaryLocale = Locale::getPrimaryLocale();
			foreach ($submissionFiles as $file) {
				$file->setData('notRename', true);
				$fileArray = explode('.', $file->getData('path'));
				$file->setData('name', str_replace(' ', '_', $file->getData('name',$file->getData('locale'))) . '_csp_' . str_replace('/', '_', $row->code) .'_V1.' . $fileArray[1], $file->getData('locale'));
				if($file->getData('locale') <> $primaryLocale){
					$file->setData('name', str_replace(' ', '_', $file->getData('name',$primaryLocale)) . '_csp_' . str_replace('/', '_', $row->code) .'_V1.' . $fileArray[1], $primaryLocale);
				}
                Repo::submissionFile()->edit($file, $file->_data);
            }

			// Designa usuários com papel de Secretaria à nova submissão
			$users = Repo::user()
				->getCollector()
				->filterByUserGroupIds([19])
				->filterByContextIds([1])
				->getMany()
				->toArray();
			foreach ($users as $user) {
				$submission = $args[1];
				$stageId = 1;
				$assignmentId = '';
				$userGroup = Repo::userGroup()->get(19);
				$form = new AddParticipantForm($submission, $stageId, $assignmentId);
				$form->readInputData();
				$form->setData('userGroupId', $userGroup->getData('id'));
				$form->setData('userId', $user->getData('id'));
				$form->setData('template', "DISCUSSION_NOTIFICATION_SUBMISSION");
				$form->execute();

				$eventLog = Repo::eventLog()->newDataObject([
					'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
					'assocId' => $submission->getId(),
					'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_ADD_PARTICIPANT,
					'userId' => Validation::loggedInAs() ?? $user->getId(),
					'message' => 'submission.event.participantAdded',
					'isTranslated' => false,
					'dateLogged' => Core::getCurrentDate(),
					'userFullName' => $user->getFullName(),
					'username' => $user->getUsername(),
					'userGroupName' => $userGroup->getData('name')
				]);
				Repo::eventLog()->add($eventLog);
			}
		}
	}
	public function templateManagerDisplay($hookName, $args) {
		if($args[1] == "submission/wizard.tpl"){
			unset($args[0]->tpl_vars["locales"]->value["en"]);
			unset($args[0]->tpl_vars["locales"]->value["es"]);
		}
	}
}
