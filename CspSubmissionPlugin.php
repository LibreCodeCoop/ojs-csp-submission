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
use PKP\facades\Locale;
use PKP\submissionFile\SubmissionFile;
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
			Hook::add('submissionfilesuploadform::validate', [$this, 'submissionfilesuploadformValidate']);
			Hook::add('Template::SubmissionWizard::Section::Review::Editors', [$this, 'reviewEditorsSection']);

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
		$schema->properties->usoIA = (object) [
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
			// Customiza campo "Fonte" para adicionar pergunta prévia: "Seu texto está depositado em servidor preprint?"
			$sourceValue = $args->publication->getData('source');
			$preprintOption = ($sourceValue !== null && $sourceValue !== '') ? 'S' : '';

			$args->addField(new FieldOptions('preprintOption', [
				'label' => __('plugins.generic.CspSubmission.preprint'),
				'groupId' => 'default',
				'isRequired' => true,
				'type' => 'radio',
				'options' => [
					['value' => 'S', 'label' => __('common.yes')],
					['value' => 'N', 'label' => __('common.no')],
				],
				'value' => $preprintOption,
			]), [FIELD_POSITION_BEFORE, 'source']);

			$source = $args->getField('source');
			$source->isRequired = true;
			$source->label = __('plugins.generic.CspSubmission.preprint.doi');
			$source->description = '';
			$source->size = 'large';
			$source->showWhen = ['preprintOption', 'S'];
			$source->value = ($preprintOption === 'S' ? $sourceValue : '');

			// Adiciona campo monografia, dissertação ou tese depositada
			$monografDissertTeseValue = $args->publication->getData('monografDissertTese');
			$monografDissertTeseOption = ($monografDissertTeseValue !== null && $monografDissertTeseValue !== '') ? 'S' : '';
			$args->addField(new FieldOptions('monografDissertTeseOption', [
				'label' => __('plugins.generic.CspSubmission.monografDissertTese.options'),
				'groupId' => 'default',
				'isRequired' => true,
				'type' => 'radio',
				'options' => [
					['value' => 'S', 'label' => __('common.yes')],
					['value' => 'N', 'label' => __('common.no')],
				],
				'value' => $monografDissertTeseOption,
			]), [FIELD_POSITION_BEFORE, 'monografDissertTese']);
			$args->addField(new FieldText('monografDissertTese', [
				'label' => __('plugins.generic.CspSubmission.monografDissertTese.repo'),
				'groupId' => 'default',
				'isRequired' => true,
				'size' => 'large',
				'showWhen' => ['monografDissertTeseOption', 'S'],
				'value' => ($monografDissertTeseOption === 'S' ? $monografDissertTeseValue : '')
			]),[FIELD_POSITION_AFTER, 'monografDissertTeseOption']);

			// Adiciona campos de opções sobre disponibilidade dos dados de pesquisa
			$args->addField(new FieldRadioInput('dataAvailabilityRadios', [
				'label' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.label'),
				'groupId' => 'default',
				'isRequired' => true,
				'size' => 'large',
				'type' => 'radio',
				'value' => $args->publication->getData('dataAvailabilityRadios') ?? '',
                'options' => [
                    ['value' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosDisponiveisNoRepo'), 'label' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosDisponiveisNoRepo'),],
					['value' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosDisponiveisMedianteSolicitacao'), 'label' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosDisponiveisMedianteSolicitacao'),],
                    ['value' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.fontesIndicadasNoCorpoDoArtigo'), 'label' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.fontesIndicadasNoCorpoDoArtigo'),],
                    ['value' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosNaoDisponiveis'), 'label' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosNaoDisponiveis'),],
                    ['value' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.naoSeAplica'), 'label' => __('plugins.generic.CspSubmission.dataAvailabilityRadios.naoSeAplica'),],
                ],
			]),[FIELD_POSITION_BEFORE, 'dataAvailability']);
			$dataAvailability = $args->getField('dataAvailability');
			$dataAvailability->showWhen = ['dataAvailabilityRadios', __('plugins.generic.CspSubmission.dataAvailabilityRadios.dadosDisponiveisNoRepo')];

		}
		// Customiza formulário de autor/coautor
		if($args->id == "contributor"){
			$orcid = $args->getField('orcid');
			if ($orcid) {
				$orcid->isRequired = true;
			}

			$familyName = $args->getField('familyName');
			$familyName->isRequired = true;

			$familyName = $args->getField('biography');
			$familyName->isRequired = true;

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
			$args->addHiddenField('userGroupId', $authorgroup->id);
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
					$usoIA = $args->submission->getData('usoIA');
					$usoIAOption = ($usoIA === null || $usoIA === '') ? '' : ($usoIA === 'N' ? 'N' : 'S');
					$args->addField(new FieldOptions('usoIAOption', [
						'label' => __('plugins.generic.CspSubmission.usoIA.options'),
						'groupId' => 'default',
						'isRequired' => true,
						'type' => 'radio',
						'options' => [
							['value' => 'S', 'label' => __('common.yes')],
							['value' => 'N', 'label' => __('common.no')],
						],
						'value' => $usoIAOption,
					]));
					$args->addField(new FieldTextarea('usoIA', [
						'label' => __('plugins.generic.CspSubmission.usoIA.description'),
						'groupId' => 'default',
						'isRequired' => true,
						'showWhen' => ['usoIAOption', 'S'],
						'value' => ($usoIAOption === 'S' ? $usoIA : ''),
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
							$section = Repo::section()->get((int) $publication->getData('sectionId'));
							$sectionAbbrev = $section->getAbbrev($context->_data["primaryLocale"]);
							$limit = self::getWordCountLimit($sectionAbbrev);
							if ($limit !== null) {
								try {
									$wordCount = self::countWordsInFile('files/' . $file->getData('path'));
									if ($wordCount > $limit['threshold']) {
										$args[0]['files'][] = __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
											'section' => $section->getTitle($publication->getData('locale')),
											'max'     => $limit['max'],
											'count'   => $wordCount
										]);
									}
								} catch (\BadMethodCallException) {
									$args[0]["files"][] = __('plugins.generic.CspSubmission.SectionFile.errorWordCountParse', ['genre' => $genre->getLocalizedData('name')]);
								}
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
		if(!$submission->getData('usoIA')){
			$args[0]["usoIA"] = [$locale => [__('plugins.generic.CspSubmission.usoIA.Notification')]];
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
		if ($request->getUserVar('usoIAOption') === 'N') {
			$args[0]->setData('usoIA', 'N');
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
	/**
	 * Returns word count limit for a section.
	 * Returns ['max' => int, 'threshold' => int] or null if no limit applies.
	 */
	public static function getWordCountLimit(string $sectionAbbrev): ?array
	{
		switch ($sectionAbbrev) {
			case 'ARTIGO':
			case 'DEBATE':
			case 'QUEST_METOD':
			case 'ENTREVISTA':
			case 'ESP_TEMATICO':
				return ['max' => 6000, 'threshold' => 6600];
			case 'EDITORIAL':
			case 'COM_BREVE':
			case 'PERSPECT':
				return ['max' => 2500, 'threshold' => 2750];
			case 'REVISAO':
			case 'ENSAIO':
				return ['max' => 8000, 'threshold' => 8800];
			case 'CARTA':
			case 'COMENTARIOS':
			case 'RESENHA':
				return ['max' => 1400, 'threshold' => 1540];
			case 'OBTUARIO':
				return ['max' => 1000, 'threshold' => 1050];
			case 'ERRATA':
				return ['max' => 700, 'threshold' => 770];
			default:
				return null;
		}
	}


	public function templateManagerDisplay($hookName, $args) {
		if($args[1] == "submission/wizard.tpl"){
			unset($args[0]->tpl_vars["locales"]->value["en"]);
			unset($args[0]->tpl_vars["locales"]->value["es"]);
		}
	}

	//Exibe campos Disponibilidade de dados, Monografia/Dissertação/Tese e Agradecimentos em etapa "Revisar" da submissão
	public function reviewEditorsSection(string $hookName, array $args): bool
	{
		$params = $args[0];
		$smarty = $args[1];
		$submission = $params['submission'];
		$publication = $submission->getCurrentPublication();

		$smarty->assign([
			'cspPublication' => $publication,
			'cspSubmission' => $submission,
		]);
		$args[2] = $smarty->fetch($this->getTemplateResource('submission/reviewEditorsSection.tpl'));
		return false;
	}

	public function submissionfilesuploadformValidate($hookName, array $args)
	{
		//No upload de revisões, verifica se o número de palavras dos arquivos Corpo do Texto está dentro do limite permitido para a seção
		$form = $args[0];
		if ((int) $form->getData('fileStage') !== SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION) {
			return false;
		}

		$genreId = (int) $form->getData('genreId');
		if (!$genreId || empty($_FILES['uploadedFile']['tmp_name'])) {
			return false;
		}

		$context = Application::get()->getRequest()->getContext();
		$genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
		$genre = $genreDao->getById($genreId, $context->getId());
		if (!$genre || $genre->getKey() !== 'SUBMISSION') {
			return false;
		}

		$extension = strtolower(pathinfo($_FILES['uploadedFile']['name'], PATHINFO_EXTENSION));
		if (!in_array($extension, ['doc', 'docx', 'odt', 'rtf'])) {
			return false;
		}

		$submission = Repo::submission()->get((int) $form->getData('submissionId'));
		$publication = Repo::publication()->get((int) $submission->getData('currentPublicationId'));
		$section = Repo::section()->get((int) $publication->getData('sectionId'));
		$sectionAbbrev = $section->getAbbrev($context->getData('primaryLocale'));

		$limit = self::getWordCountLimit($sectionAbbrev);
		if ($limit === null) {
			return false;
		}

		$tempPath = $_FILES['uploadedFile']['tmp_name'] . '.' . $extension;
		copy($_FILES['uploadedFile']['tmp_name'], $tempPath);
		try {
			$wordCount = self::countWordsInFile($tempPath);
		} catch (\Exception) {
			@unlink($tempPath);
			return false;
		}
		@unlink($tempPath);

		if ($wordCount > $limit['threshold']) {
			$form->addError('uploadedFile', __('plugins.generic.CspSubmission.SectionFile.errorWordCount', [
				'section' => $section->getTitle($publication->getData('locale')),
				'max'     => $limit['max'],
				'count'   => $wordCount,
			]));
		}

		return false;
	}

	private static function countWordsInFile(string $pathWithExtension): int
	{
		$extension = strtolower(pathinfo($pathWithExtension, PATHINFO_EXTENSION));
		$readerType = match ($extension) {
			'docx' => 'Word2007',
			'doc' => 'MsDoc',
			'odt' => 'ODText',
			'rtf' => 'RTF',
			default => throw new \BadMethodCallException("Unsupported extension for word count: {$extension}"),
		};
		try {
			$phpWord = \PhpOffice\PhpWord\IOFactory::load($pathWithExtension, $readerType);
		} catch (\Throwable $e) {
			throw new \BadMethodCallException("Failed to parse {$extension} file for word count: {$e->getMessage()}", 0, $e);
		}
		$htmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
		$tmpHtml = tempnam(sys_get_temp_dir(), 'cspwordcount_') . '.html';
		$htmlWriter->save($tmpHtml);
		$htmlContent = file_get_contents($tmpHtml);
		// PhpWord's HTML writer always emits a <style> block; strip_tags() would
		// otherwise leave its CSS text behind to be miscounted as words.
		$htmlContent = preg_replace('#<head\b[^>]*>.*?</head>#is', '', $htmlContent);
		$htmlContent = preg_replace('/<img[^>]+>/i', '(image) ', $htmlContent);
		$wordCount = str_word_count(strip_tags($htmlContent));
		@unlink($tmpHtml);
		return $wordCount;
	}
}