<?php

import('plugins.generic.cspSubmission.class.AbstractPlugin');

/**
 * @file plugins/generic/cspSubmission/class/QuickSubmitFormCsp.inc.php
 *
 * @class QuickSubmitFormCsp
 *
 */
class QuickSubmitFormCsp extends AbstractPlugin
{

	function validate($params)
	{
		$form = &$params[0];
		$request = \Application::get()->getRequest();
		$doi = $request->getUserVar('doi');

		import('plugins.pubIds.doi.DOIPubIdPlugin');
		$DOIPubIdPlugin = new DOIPubIdPlugin();

		$contextId = $request->getContext()->getData('id');
		$doiPrefix = $DOIPubIdPlugin->getSetting($contextId, 'doiPrefix');

		if (strpos($doi, $doiPrefix) !== 0) {
			$doiErrors = __('plugins.pubIds.doi.editor.missingPrefix', ['doiPrefix' => $doiPrefix]);
		}

		if (!$DOIPubIdPlugin->checkDuplicate($doi, 'Publication', $request->getUserVar('submissionId'), $contextId)) {
			$doiErrors = __('plugins.pubIds.doi.editor.doiSuffixCustomIdentifierNotUnique');
		}
		if (!empty($doiErrors)) {
			$form->addError('doi', $doiErrors);
		}
	}

	function execute($params){
		$request = Application::get()->getRequest();
		$editorDecision = array(
			'decision' => 1,
			'dateDecided' => $request->getUserVar('dateAccepted')
		);
		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /* @var $editDecisionDao EditDecisionDAO */
		$editDecisionDao->updateEditorDecision($request->getUserVar('submissionId'), $editorDecision);

		$params[1]->_data["dateSubmitted"] = $request->getUserVar('dateSubmitted');

	}

	function readuservars($params){
		$request = Application::get()->getRequest();
		$params[1][] = "dateAccepted";
		$params[1][] = "dateSubmitted";

	}

}
