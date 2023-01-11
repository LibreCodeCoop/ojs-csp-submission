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
		$dateDecided = $request->getUserVar('dateAccepted') ? $request->getUserVar('dateAccepted') : date('Y-m-d H:i:s');
		$dateSubmitted = $request->getUserVar('dateSubmitted') ? $request->getUserVar('dateSubmitted') : date('Y-m-d H:i:s');
		$editorDecision = array(
			'decision' => 1,
			'dateDecided' => $dateDecided
		);
		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /* @var $editDecisionDao EditDecisionDAO */
		$editDecisionDao->updateEditorDecision($request->getUserVar('submissionId'), $editorDecision);
		$params[1]->_data["dateSubmitted"] = $dateSubmitted;

		$userDao = DAORegistry::getDAO('UserDAO');
		if ($request->getUserVar('dateAccepted')) {
			$userDao->update(
				'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
				array((string)'publicada', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$request->getUserVar('submissionId'))
			);
		}else{
			$userDao->update(
				'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
				array((string)'edit_aguardando_publicacao', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$request->getUserVar('submissionId'))
			);
		}

	}

	function readuservars($params){
		$request = Application::get()->getRequest();
		$params[1][] = "dateAccepted";
		$params[1][] = "dateSubmitted";

	}

}
