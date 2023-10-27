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
use PKP\components\forms\submission\ForTheEditors;
use APP\facades\Repo;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldRadioInput;
use PKP\security\Role;
// use PKP\components\forms\FieldAutosuggestPreset;
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
			$templateMgr->addJavaScript(
				'submissionfiles',
				"{$request->getBaseUrl()}/{$this->getPluginPath()}/js/build.js",
				[
					'contexts' => ['submissionFile', 'backend'],
					'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
					'inline' => false,
				]
			);
			Hook::add('SubmissionFile::validate', [$this, 'submissionFileValidate']);
			Hook::add('Schema::get::context', [$this, 'schemaGetContext']);
			Hook::add('Form::config::before', [$this, 'formConfigBefore']);
			Hook::add('Submission::validateSubmit', [$this, 'submissionValidateSubmit']);

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

    public function schemaGetContext(string $hookName, array $args){
		$schema = $args[0]; /** @var stdClass */
		$schema->properties->agradecimentos = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];

		$schema->properties->codigoTematico = (object) [
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
		$schema->properties->conflitoInteresse = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];
		$schema->properties->consideracoesEticas = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];
		return false;
    }

	public function formConfigBefore($hookName, $args) {
		$context = \Application::get()->getRequest()->getContext();
		$request = \Application::get()->getRequest();

		if($request->getRequestedPage() == 'submission'){

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
					if(in_array($sectionAbbrev, ['ARTIGO', 'COM_BREVE', 'DEBATE', 'ENSAIO', 'QUEST_METOD', 'REVISAO'])) {
						$keywords = $args->getField('keywords');
						$keywords->isRequired = true;
					}

					if($sectionAbbrev == "ESP_TEMATICO") {
						$args->addField(new FieldText('codigoTematico', [
							'label' => __('plugins.generic.CspSubmission.codigoTematico'),
							'groupId' => 'default',
							'isRequired' => true,
							'size' => 'small',
							'value' => $context->getData('codigoTematico'),
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
				}

				if($args->id == "forTheEditors"){
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
					$args->fields[1]->size = "large";
				}

				if($args->id == "commentsForTheEditors"){
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
		$context = \Application::get()->getRequest()->getContext();
        $publication = $args[1]->getCurrentPublication();
		$keywords = count($publication->getData('keywords', $locale));
		$section = Repo::section()->get((int) $publication->getData('sectionId'));
		$sectionAbbrev = $section->getAbbrev($context->getData('primaryLocale'));
		if(in_array($sectionAbbrev, ['ARTIGO', 'COM_BREVE', 'DEBATE', 'ENSAIO', 'QUEST_METOD', 'REVISAO'])) {
			if($keywords < 3 or $keywords > 5){
				$args[0]["keywords"] = [$locale => [__('plugins.generic.CspSubmission.keywords.Notification')]];
			}
			if (!$keywords) {
				$args[0]["keywords"] = [$locale => [__('validator.required')]];
			}
		}
	}
}
