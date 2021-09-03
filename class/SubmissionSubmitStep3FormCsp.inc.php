<?php

import('plugins.generic.cspSubmission.class.AbstractPlugin');
use Symfony\Component\HttpClient\HttpClient;

/**
 * @file plugins/generic/cspSubmission/class/SubmissionSubmitStep3FormCsp.inc.php
 *
 * @class SubmissionSubmitStep3FormCsp
 *
 * @brief Class for modify behaviors in the third step of submission
 *
 */
class SubmissionSubmitStep3FormCsp extends AbstractPlugin
{
	/**
	 * Checks validity of fields
	 *
	 * @param [type] $params
	 * @return void
	 */
	function constructor($params)
	{
		$form = &$params[0];
		$request = \Application::get()->getRequest();
		$submissionDAO = Application::getSubmissionDAO();
		$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
		$publication = $submission->getCurrentPublication();
		$sectionId = $publication->getData('sectionId');

		if ($sectionId == 4) {
			$form->addCheck(new FormValidatorLength($form, 'codigoTematico', 'required', 'plugins.generic.CspSubmission.codigoTematico.Valid', '>', 0));
			$form->addCheck(new FormValidatorLength($form, 'tema', 'required', 'plugins.generic.CspSubmission.Tema.Valid', '>', 0));
			$form->addCheck(new FormValidatorLength($form, 'codigoArtigoRelacionado', 'required', 'plugins.generic.CspSubmission.codigoArtigoRelacionado.Valid', '>', 0));
		}

		$form->addCheck(new FormValidatorCustom($form, 'source', 'optional', 'plugins.generic.CspSubmission.doi.Valid', function ($doi) {
			if (!filter_var($doi, FILTER_VALIDATE_URL)) {
				if (strpos(reset($doi), 'doi.org') === false) {
					$doi = 'http://dx.doi.org/' . reset($doi);
				} elseif (strpos(reset($doi), 'http') === false) {
					$doi = 'http://' . reset($doi);
				} else {
					return false;
				}
			}
			$client = HttpClient::create();
			$response = $client->request('GET', $doi);
			$statusCode = $response->getStatusCode();
			return in_array($statusCode, [303, 200]);
		}));

		if(!in_array($sectionId, [2, 3, 10, 11, 12, 13, 14, 15])){
			$keywords = $request->_requestVars["keywords"][$form->defaultLocale."-keywords"];
			if(count($keywords) < 3 or count($keywords) > 5){
				$form->addError('genreId', __('plugins.generic.CspSubmission.submission.keywords.Notification'));
				return false;
			}
		}
		return false;
	}

	function initData($params)
	{
		$form = &$params[0];
		$article = $form->submission;
		$form->setData('agradecimentos', $article->getData('agradecimentos'));
		$form->setData('codigoTematico', $article->getData('codigoTematico'));
		$form->setData('codigoArtigoRelacionado', $article->getData('codigoArtigoRelacionado'));
		$form->setData('conflitoInteresse', $article->getData('conflitoInteresse'));
		$form->setData('tema', $article->getData('tema'));
		$form->setData('codigoArtigo', $article->getData('codigoArtigo'));
		$form->setData('consideracoesEticas', $article->getData('consideracoesEticas'));
		$form->setData('ensaiosClinicos', $article->getData('ensaiosClinicos'));
		$form->setData('numRegistro', $article->getData('numRegistro'));
		$form->setData('orgao', $article->getData('orgao'));

		return false;
	}

	function readUserVars($params)
	{
		$userVars = &$params[1];
		$userVars[] = 'conflitoInteresse';
		$userVars[] = 'agradecimentos';
		$userVars[] = 'codigoTematico';
		$userVars[] = 'tema';
		$userVars[] = 'codigoArtigoRelacionado';
		$userVars[] = 'codigoArtigo';
		$userVars[] = 'consideracoesEticas';
		$userVars[] = 'ensaiosClinicos';
		$userVars[] = 'numRegistro';
		$userVars[] = 'orgao';

		return false;
	}

	function execute($params)
	{
		$form = &$params[0];
		$article = $form->submission;
		$article->setData('conflitoInteresse', $form->getData('conflitoInteresse'));
		$article->setData('agradecimentos', $form->getData('agradecimentos'));
		$article->setData('codigoTematico', $form->getData('codigoTematico'));
		$article->setData('tema', $form->getData('tema'));
		$article->setData('codigoArtigoRelacionado', $form->getData('codigoArtigoRelacionado'));
		$article->setData('consideracoesEticas', $form->getData('consideracoesEticas'));
		$article->setData('ensaiosClinicos', $form->getData('ensaiosClinicos'));
		$article->setData('numRegistro', $form->getData('numRegistro'));
		$article->setData('orgao', $form->getData('orgao'));

		return false;
	}
}
