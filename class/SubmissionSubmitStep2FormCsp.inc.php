<?php

import('plugins.generic.cspSubmission.class.AbstractPlugin');

/**
 * @file plugins/generic/cspSubmission/class/SubmissionSubmitStep2FormCsp.inc.php
 *
 * @class SubmissionSubmitStep2FormCsp
 *
 * @brief Class for modify behaviors in the second step of submission
 *
 */
class SubmissionSubmitStep2FormCsp extends AbstractPlugin
{
	/**
	 * Verifies if exists at least one file of body text
	 *
	 * @param [type] $params
	 * @return void
	 */
	function constructor($params)
	{
		$request = \Application::get()->getRequest();
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFiles = $submissionFileDao->getBySubmissionId($request->_requestVars["submissionId"]);

		if(!empty($submissionFiles)){
			foreach ($submissionFiles as $submissionFile) {
				$name = $submissionFile->getLocalizedName();
				if(str_contains($name, 'Corpo_do_Texto')){
					$corpoTexto = true;
				}
			}
		}
		if(!$corpoTexto){
			$params[0]->addError('genreId',__('plugins.generic.CspSubmission.submission.Step2.MissingFile'));
			return true;
		}
	}
}
