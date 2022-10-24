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

		$submissionFilesIterator = Services::get('submissionFile')->getMany([
			'submissionIds' => [$request->getUserVar('submissionId')],
		]);

		if(!empty($submissionFilesIterator)){
			foreach ($submissionFilesIterator as $submissionFile) {
				$genres = DAORegistry::getDAO('GenreDAO')->getById($submissionFile->getData('genreId'));
				if(str_contains($genres->getLocalizedData('name'), "Corpo do Texto")){
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
