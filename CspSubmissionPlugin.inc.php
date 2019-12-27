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

import('lib.pkp.classes.plugins.GenericPlugin');


class CspSubmissionPlugin extends GenericPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			// Insert new field into author metadata submission form (submission step 3) and metadata form
			HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'metadataFieldEdit'));
			// HookRegistry::register('TemplateManager::fetch', array($this, 'testeStep1'));

			// Hook for initData in two forms -- init the new field
			HookRegistry::register('submissionsubmitstep3form::initdata', array($this, 'metadataInitData'));

			// Hook for readUserVars in two forms -- consider the new field entry
			HookRegistry::register('submissionsubmitstep3form::readuservars', array($this, 'metadataReadUserVars'));

			// Hook for execute in two forms -- consider the new field in the article settings
			HookRegistry::register('submissionsubmitstep3form::execute', array($this, 'metadataExecute'));

			// Hook for save in two forms -- add validation for the new field
			HookRegistry::register('submissionsubmitstep3form::Constructor', array($this, 'addCheck'));

			// Consider the new field for ArticleDAO for storage
			HookRegistry::register('articledao::getAdditionalFieldNames', array($this, 'metadataReadUserVars'));

		}
		return $success;
	}

	function testeStep1($hookName, $args) {
		if ($args[1] == 'submission/form/step1.tpl') {
			$templateMgr =& $args[0];
			// $templateMgr->assign('additionalFormContent1', 'toaqui_1');
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

		$output .= $smarty->fetch($this->getTemplateResource('Campo1Edit.tpl'));
		return false;
	}

	/**
	 * Concern Campo1 field in the form
	 */
	function metadataReadUserVars($hookName, $params) {
		$userVars =& $params[1];
		$userVars[] = 'Campo1';
		return false;
	}

	/**
	 * Set article Campo1
	 */
	function metadataExecute($hookName, $params) {
		$form =& $params[0];
		$article = $form->submission;
		$formCampo1 = $form->getData('Campo1');
		$article->setData('Campo1', $formCampo1);
		return false;
	}

	/**
	 * Init article Campo1
	 */
	function metadataInitData($hookName, $params) {
		$form =& $params[0];
		$article = $form->submission;
		$this->sectionId = $article->getData('sectionId');
		$articleCampo1 = $article->getData('Campo1');
		$form->setData('Campo1', $articleCampo1);
		return false;
	}

	/**
	 * Add check/validation for the Campo1 field (= 6 numbers)
	 */
	function addCheck($hookName, $params) {
		$form =& $params[0];
		$form->addCheck(new FormValidatorRegExp($form, 'Campo1', 'optional', 'plugins.generic.CspSubmission.Campo1Valid', '/^\d{6}$/'));
		return false;
	}

}
