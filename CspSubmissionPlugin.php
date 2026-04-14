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
use APP\facades\Repo;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldRadioInput;
use PKP\components\forms\FieldOptions;
use PKP\security\Role;
use NcJoes\OfficeConverter\OfficeConverter;
use PKP\facades\Locale;
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

			Hook::add('TemplateResource::getFilename', [$this, '_overridePluginTemplates']);
			Hook::add('SubmissionFile::edit', [$this, 'submissionFileEdit']);
			Hook::add('Schema::get::submission', [$this, 'schemaGetSubmission']);
			Hook::add('Form::config::before', [$this, 'formConfigBefore']);
			Hook::add('Submission::validateSubmit', [$this, 'submissionValidateSubmit']);
			Hook::add('Submission::edit', [$this, 'submissionEdit']);
			Hook::add('Schema::get::publication', [$this, 'schemaGetPublication']);
			Hook::add('TemplateManager::display', [$this, 'templateManagerDisplay']);
			Hook::add('Schema::get::author', [$this, 'SchemaGetAuthor']);
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

	public function submissionFileEdit(string $hookName, array $args){
		$request = Application::get()->getRequest();
		$submission = Repo::submission()->get((int) $args[0]->getData('submissionId'));
		$locale = $args[0]->getData('locale');
		$context = $request->getContext();
		$primaryLocale = $context->getData('primaryLocale');
		$fileStage = $args[0]->getData('fileStage'); //Estágio de publicação

		if($request->_requestVars["revisedFileId"]){
			$newName = $args[1]->getData('name',$args[1]->getData('locale'));
			$args[0]->setData('name', $newName,  $locale);
			$newName = $args[1]->getData('name',$primaryLocale);
			$args[0]->setData('name', $newName,  $primaryLocale);
			return true;
		}
		// Renomeia arquivo inserido na etapa de envio de arquivos da submissão, atribuindo o nome do gênero do arquivo
		if($submission->getData('submissionProgress') == "start" && !$args[2]["notRename"] && $fileStage <> 17){
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
		$schema->properties->monografDissertTese = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];
		$schema->properties->dataAvailabilityRadios = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];

		return false;
    }

	public function SchemaGetAuthor(string $hookName, array $args){
		$schema = $args[0]; /** @var stdClass */
		// Adiciona campos de endereço em esquema de Autor
		$schema->properties->mailingAddress = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['required']
		];		$schema->properties->region = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['required']
		];		$schema->properties->city = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['required']
		];
	}

	public function formConfigBefore($hookName, $args) {
		$context = Application::get()->getRequest()->getContext();
		$request = Application::get()->getRequest();
		if($args->id == "forTheEditors"){
			// Customiza campo "Fonte" para adicionar caixas de seleção
			$source = $args->getField('source');
			$source->isRequired = true;
			$source->description = __('plugins.generic.CspSubmission.preprint');
			$source->size = 'large';

			$args->addField(new FieldText('monografDissertTese', [
				'label' => __('plugins.generic.CspSubmission.monografDissertTese.label'),
				'description' => __('plugins.generic.CspSubmission.monografDissertTese.description'),
				'groupId' => 'default',
				'isRequired' => true,
				'size' => 'large',
				'value' => $args->publication->getData('monografDissertTese')
			]),[FIELD_POSITION_AFTER, 'source']);

			$args->addField(new FieldRadioInput('dataAvailabilityRadios', [
				'label' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.label'),
				'groupId' => 'default',
				'isRequired' => true,
				'size' => 'large',
				'type' => 'radio',
				'value' => $args->publication->getData('dataAvailabilityRadios'),
                'options' => [
                    ['value' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosDisponiveisNoRepo'), 'label' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosDisponiveisNoRepo'),],
					['value' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosDisponiveisMedianteSolicitacao'), 'label' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosDisponiveisMedianteSolicitacao'),],
                    ['value' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.fontesIndicadasNoCorpoDoArtigo'), 'label' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.fontesIndicadasNoCorpoDoArtigo'),],
                    ['value' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosNaoDisponiveis'), 'label' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosNaoDisponiveis'),],
                    ['value' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.naoSeAplica'), 'label' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.naoSeAplica'),],
                ],
			]),[FIELD_POSITION_BEFORE, 'dataAvailability']);

		}
		// Customiza formulário de autor/coautor
		if($args->id == "contributor"){
			$orcid = $args->getField('orcid');
			$orcid->isRequired = true;

			$familyName = $args->getField('familyName');
			$familyName->isRequired = true;

			$familyName = $args->getField('biography');
			$familyName->isRequired = true;

			$affiliation = $args->getField('affiliation');
			$affiliation->description = __('user.affiliation.description');
			$affiliation->size = "large";
			$affiliation->isRequired = true;

			// Adiciona campo Endereço em formulário de inclusão de autor/coautor na submissão
			$args->addField(new FieldText('region', [
				'label' => __('plugins.themes.csp.user.region'),
				'isRequired' => true,
				'size' => 'small',
			]),[FIELD_POSITION_AFTER, 'country']);

			$args->addField(new FieldText('city', [
				'label' => __('stats.city'),
				'isRequired' => true,
				'size' => 'medium',
			]),[FIELD_POSITION_AFTER, 'region']);

			$args->addField(new FieldText('mailingAddress', [
				'label' => __('common.mailingAddress'),
				'isRequired' => true,
				'size' => 'large',
			]),[FIELD_POSITION_AFTER, 'city']);

			$args->removeField('preferredPublicName');
			$args->removeField('url');
			$args->removeField('userGroupId');

			// Atribui colaborador com papel de autor pois o campo de escolha do papel foi ocultado
			$authorgroup = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_AUTHOR], $context->getId(), true)->first();
			$args->addHiddenField('userGroupId', $authorgroup->getData('id'));
		}
		if($request->_router->_page == 'submission'){
			if($args->id == "startSubmission"){
				$args->removeField('title');
			}

			if(in_array($args->id, ['titleAbstract', 'submissionFile', 'forTheEditors', 'commentsForTheEditors', 'commentsForTheEditors', 'contributor'])){
				$submissionId = $request->getUserVar('id');
				$submission = Repo::submission()->get((int) $submissionId);
				$publication = Repo::publication()->get((int) $submission->getData('currentPublicationId'));
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
					$conflitoInteresse = $args->submission->getData('conflitoInteresse');
					$conflitoInteresseOption = ($conflitoInteresse === null || $conflitoInteresse === '')
						? '' : ($conflitoInteresse === 'N' ? 'N' : 'S');
					$args->addField(new FieldOptions('conflitoInteresseOption', [
						'label' => __('plugins.generic.CspSubmission.conflitoInteresse'),
						'groupId' => 'default',
						'isRequired' => true,
						'type' => 'radio',
						'options' => [
							['value' => 'S', 'label' => __('common.yes')],
							['value' => 'N', 'label' => __('common.no')],
						],
						'value' => $conflitoInteresseOption,
					]));
					$args->addField(new FieldTextarea('conflitoInteresse', [
						'label' => __('plugins.generic.CspSubmission.conflitoInteresseTexto'),
						'groupId' => 'default',
						'isRequired' => true,
						'showWhen' => ['conflitoInteresseOption', 'S'],
						'value' => ($conflitoInteresseOption === 'S' ? $conflitoInteresse : ''),
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
						'value' => $args->submission->getData('consideracoesEticas'),
					]));
					$args->addField(new FieldTextarea('agradecimentos', [
						'label' => __('plugins.generic.CspSubmission.agradecimentos'),
						'groupId' => 'default',
						'isRequired' => false,
						'size' => 'normal',
						'value' => $args->submission->getData('agradecimentos'),
					]));
				}
			}
		}
	}

	public function submissionValidateSubmit($hookName, $args) {
		$locale = $args[1]->getData('locale');
		$context = Application::get()->getRequest()->getContext();
        $publication = $args[1]->getCurrentPublication();
		$submission = Repo::submission()->get((int) $publication->_data["submissionId"]);

		$genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
		$submissionFiles = Repo::submissionFile()
			->getCollector()
			->filterBySubmissionIds([$submission->getId()])
			->getMany();
		$submissionGenreCount = 0;
		$transcriptsGenreCount = 0;
		foreach ($submissionFiles as $file) {
			$genre = $genreDao->getById($file->getData('genreId'), $context->getId());
			if ($genre) {
				// Verifica se há mais de um arquivo com o gênero 'Corpo do Texto'
				if ($genre->getKey() === 'SUBMISSION') {
					$submissionGenreCount++;
					if ($submissionGenreCount > 1) {
						$args[0]["files"][] = __('plugins.generic.CspSubmission.submission.bodyTextFile.limit');
					}
				}
				// Verifica se há mais de um arquivo com o gênero 'Legendas'
				if ($genre->getKey() === 'LEGENDA_FIGURAS') {
					$transcriptsGenreCount++;
					if ($transcriptsGenreCount > 1) {
						$args[0]["files"][] = __('plugins.generic.CspSubmission.submission.transcriptsFile.limit');
					}
				}
				if (in_array($genre->getKey(), ['SUBMISSION', 'SUBMISSION_TABLE', 'LEGENDA_FIGURAS', 'MATERIAL_SUPLEMENTAR'])) {
					$allowedMimetypes = [
						'application/msword',
						'application/wps-office.doc',
						'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
						'application/wps-office.docx',
						'application/vnd.oasis.opendocument.text',
						'application/rtf',
					];
					$mimetype = $file->getData('mimetype');
					// Verifica se o tipo do arquivo é permitido para o gênero do arquivo
					$allowedFormats = ['doc', 'docx', 'odt', 'rtf'];
					if (!in_array($mimetype, $allowedMimetypes)) {
						$args[0]["files"][] = __('plugins.generic.CspSubmission.SectionFile.invalidFormat', ['genre' => $genre->getLocalizedData('name'), 'allowedFormats' => implode(', ', $allowedFormats)]);
					}else{
						//Verifica se o número de palavras dos arquivos Corpo do Texto está dentro do limite permitido para a seção da submissão
						if ($genre->getKey() === 'SUBMISSION') {
							$path = $file->getData('path');
							$formato = explode('.', $path);
							$formato = trim(strtolower(end($formato)));
							$converter = new OfficeConverter('files/' . $path);
							$htmlFile = $converter->convertTo(str_replace($formato, 'html', 'files/' . $path));
							$htmlContent = file_get_contents($htmlFile);
							$htmlContent = preg_replace("/\\<img[^>]+\\>/i", "(image) ", $htmlContent);
							file_put_contents($htmlFile, $htmlContent);
							$doc = \PhpOffice\PhpWord\IOFactory::load($htmlFile, 'HTML');
							$htmlWriter = new \PhpOffice\PhpWord\Writer\HTML($doc);
							$wordCount = str_word_count(strip_tags($htmlWriter->getWriterPart('Body')->write()));
							@unlink($htmlFile);
							$section = Repo::section()->get((int) $publication->getData('sectionId'));
							$sectionAbbrev = $section->getAbbrev($context->_data["primaryLocale"]);

							switch($sectionAbbrev) {
								case 'ARTIGO':
								case 'DEBATE':
								case 'QUEST_METOD':
								case 'ENTREVISTA':
								case 'ESP_TEMATICO':
									if ($wordCount > 6600) {
										$args[0]['files'][] = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
											'sectoin' => $section->getTitle($publication->getData('locale')),
											'max'     => 6000,
											'count'   => $wordCount
											]);
									}
								break;
								case 'EDITORIAL':
								case 'COM_BREVE':
								case 'PERSPECT':
									if ($wordCount > 2750) {
										$args[0]['files'][] = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
											'sectoin' => $section->getTitle($publication->getData('locale')),
											'max'     => 2500,
											'count'   => $wordCount
											]);
									}
								break;
								case 'REVISAO':
								case 'ENSAIO':
									if ($wordCount > 8800) {
										$args[0]['files'][] = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
											'sectoin' => $section->getTitle($publication->getData('locale')),
											'max'     => 8000,
											'count'   => $wordCount
											]);
									}
								break;
								case 'CARTA':
								case 'COMENTARIOS':
								case 'RESENHA':
									if ($wordCount > 1540) {
										$args[0]['files'][] = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
											'sectoin' => $section->getTitle($publication->getData('locale')),
											'max'     => 1400,
											'count'   => $wordCount
											]);
									}
								break;
								case 'OBTUARIO':
									if ($wordCount > 1050) {
										$args[0]['files'][] = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
											'sectoin' => $section->getTitle($publication->getData('locale')),
											'max'     => 1000,
											'count'   => $wordCount
											]);
									}
								break;
								case 'ERRATA':
									if ($wordCount > 770) {
										$args[0]['files'][] = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
											'sectoin' => $section->getTitle($publication->getData('locale')),
											'max'     => 700,
											'count'   => $wordCount
											]);
									}
								break;
							}
						}
					}
				}
				if ($genre->getKey() === 'IMAGE') {
					$allowedMimetypes = [
						'image/bmp',
						'image/tiff',
						'image/png',
						'image/jpeg',
						'image/svg+xml',
						'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
						'application/vnd.oasis.opendocument.spreadsheet',
						"application/vnd.ms-excel",
					];
					$mimetype = $file->getData('mimetype');
					$allowedFormats = ['bmp', 'tiff', 'png', 'jpeg', 'jpg', 'svg', 'xlsx', 'ods', 'xls'];
					if (!in_array($mimetype, $allowedMimetypes)) {
						$args[0]["files"][] = __('plugins.generic.CspSubmission.SectionFile.invalidFormat', ['genre' => $genre->getLocalizedData('name'), 'allowedFormats' => implode(', ', $allowedFormats)]);
					}
				}

			}
		}

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
		if(!$submission->getData('conflitoInteresse')){
			$args[0]["conflitoInteresse"] = [$locale => [__('plugins.generic.CspSubmission.conflitoInteresse.Notification')]];
		}
		if(!$submission->getData('consideracoesEticas')){
			$args[0]["consideracoesEticas"] = [$locale => [__('plugins.generic.CspSubmission.consideracoesEticas.Notification')]];
		}
		// Valida se campos Endereço postal e Contribuição do autor no trabalho foram preenchidos
		foreach ($publication->getData('authors') as $author) {
			if($author->getData('region') == null){
				$args[0]["contributors"] = [__('plugins.generic.CspSubmission.authorAddress.Notification')];
			}
			if($author->getData('city') == null){
				$args[0]["contributors"] = [__('plugins.generic.CspSubmission.authorAddress.Notification')];
			}
			if($author->getData('mailingAddress') == null){
				$args[0]["contributors"] = [__('plugins.generic.CspSubmission.authorAddress.Notification')];
			}
			if($author->getData('biography') == null){
				$args[0]["contributors"] = [__('plugins.generic.CspSubmission.authorBiography.Notification')];
			}
		}
	}

	public function submissionEdit($hookName, $args) {
		$request = Application::get()->getRequest();
		if ($request->getUserVar('conflitoInteresseOption') === 'N') {
			$args[0]->setData('conflitoInteresse', 'N');
		}
		if(isset($args[2]["submissionProgress"]) && $args[2]["submissionProgress"] == ""){
			// Atribui código CSP à nova submissão
			$contextDao = Application::getContextDao();
			$result = $contextDao->retrieve(
				<<<QUERY
				SELECT CONCAT(LPAD(count(*)+1, CASE WHEN count(*) > 9999 THEN 5 ELSE 4 END, 0), '/', DATE_FORMAT(now(), '%y')) code
				FROM submissions
				WHERE submission_progress NOT IN ('start', 'details', 'files', 'contributors', 'editors', 'review')
				AND YEAR(date_submitted) = YEAR(now())
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
		}
	}
	public function templateManagerDisplay($hookName, $args) {
		if($args[1] == "submission/wizard.tpl"){
			unset($args[0]->tpl_vars["locales"]->value["en"]);
			unset($args[0]->tpl_vars["locales"]->value["es"]);
		}
	}
}