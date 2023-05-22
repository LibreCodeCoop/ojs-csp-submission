<?php

import('plugins.generic.cspSubmission.class.AbstractPlugin');

/**
 * @file plugins/generic/cspSubmission/class/PublicationCsp.inc.php
 *
 * @class PublicationCsp
 *
 * @brief Class for modify behaviors on publication tables
 *
 */
class PublicationCsp extends AbstractPlugin
{
	public function add($args)
	{
		$args[0]->_data["submissionIdCSP"] = $args[1]->getUserVar('submissionIdCSP');
		return false;
	}

	function edit($params)
	{
		$request =& $params[3];
		$router = $request->getRouter();
		if ($router->getRequestedOp($request) == "importexport" or $router->getRequestedOp($request) == "saveStep") {
			$params[0]->setData('agradecimentos', $request->getUserVar('agradecimentos'));
			$params[1]->setData('agradecimentos', $request->getUserVar('agradecimentos'));
			$params[2]["agradecimentos"] = $request->getUserVar('agradecimentos');
			$params[0]->setData('codigoTematico', $request->getUserVar('codigoTematico'));
			$params[1]->setData('codigoTematico', $request->getUserVar('codigoTematico'));
			$params[2]["codigoTematico"] = $request->getUserVar('codigoTematico');
			$params[0]->setData('codigoArtigoRelacionado', $request->getUserVar('codigoArtigoRelacionado'));
			$params[1]->setData('codigoArtigoRelacionado', $request->getUserVar('codigoArtigoRelacionado'));
			$params[2]["codigoArtigoRelacionado"] = $request->getUserVar('codigoArtigoRelacionado');
			$params[0]->setData('conflitoInteresse', $request->getUserVar('conflitoInteresse'));
			$params[1]->setData('conflitoInteresse', $request->getUserVar('conflitoInteresse'));
			$params[2]["conflitoInteresse"] = $request->getUserVar('conflitoInteresse');
			$params[0]->setData('tema', $request->getUserVar('tema'));
			$params[1]->setData('tema', $request->getUserVar('tema'));
			$params[2]["tema"] = $request->getUserVar('tema');
			$params[0]->setData('consideracoesEticas', $request->getUserVar('consideracoesEticas'));
			$params[1]->setData('consideracoesEticas', $request->getUserVar('consideracoesEticas'));
			$params[2]["consideracoesEticas"] = $request->getUserVar('consideracoesEticas');
			$params[0]->setData('ensaiosClinicos', $request->getUserVar('ensaiosClinicos'));
			$params[1]->setData('ensaiosClinicos', $request->getUserVar('ensaiosClinicos'));
			$params[2]["ensaiosClinicos"] = $request->getUserVar('ensaiosClinicos');
			$params[0]->setData('numRegistro', $request->getUserVar('numRegistro'));
			$params[1]->setData('numRegistro', $request->getUserVar('numRegistro'));
			$params[2]["numRegistro"] = $request->getUserVar('numRegistro');
			$params[0]->setData('orgao', $request->getUserVar('orgao'));
			$params[1]->setData('orgao', $request->getUserVar('orgao'));
			$params[2]["orgao"] = $request->getUserVar('orgao');
			$params[0]->setData('pub-id::doi', $request->getUserVar('doi'));
			$params[1]->setData('pub-id::doi', $request->getUserVar('doi'));
			$params[2]["pub-id::doi"] = $request->getUserVar('doi');
			$params[0]->setData('submissionIdCSP', $request->getUserVar('submissionIdCSP'));
			$params[1]->setData('submissionIdCSP', $request->getUserVar('submissionIdCSP'));
			$params[2]["submissionIdCSP"] = $request->getUserVar('submissionIdCSP');
		}
		return false;
	}

	public function publish($args)
	{
		$submissionId = $args[0]->getData('id');
		$userDao = DAORegistry::getDAO('UserDAO');
		$userDao->update(
			'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
			[(string)'publicada', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$submissionId]
		);
	}

	public function unpublish($args)
	{
		$submissionId = $args[0]->getData('id');
		$userDao = DAORegistry::getDAO('UserDAO');
		$userDao->update(
			'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
			[(string)'edit_aguardando_publicacao', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$submissionId]
		);
	}

	public function addToSchema($args) {
		$schema = $args[0];
		$schema->properties->submissionIdCSP = (object) [
			'type' => 'string',
			'apiSummary' => true,
			'multilingual' => false,
			'validation' => ['nullable']
		];

		return false;
  }

}
