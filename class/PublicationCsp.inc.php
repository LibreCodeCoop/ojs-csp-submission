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
		$args[0]->_data["codigoArtigo"] = $args[1]->_requestVars["codigoArtigo"];
		return false;
	}

	function edit($params)
	{
		$router = $params[3]->getRouter();
		if ($router->_page == 'submission') {
			$params[0]->setData('agradecimentos', $params[3]->_requestVars["agradecimentos"]);
			$params[1]->setData('agradecimentos', $params[3]->_requestVars["agradecimentos"]);
			$params[2]["agradecimentos"] = $params[3]->_requestVars["agradecimentos"];
			$params[0]->setData('codigoTematico', $params[3]->_requestVars["codigoTematico"]);
			$params[1]->setData('codigoTematico', $params[3]->_requestVars["codigoTematico"]);
			$params[2]["codigoTematico"] = $params[3]->_requestVars["codigoTematico"];
			$params[0]->setData('codigoArtigoRelacionado', $params[3]->_requestVars["codigoArtigoRelacionado"]);
			$params[1]->setData('codigoArtigoRelacionado', $params[3]->_requestVars["codigoArtigoRelacionado"]);
			$params[2]["codigoArtigoRelacionado"] = $params[3]->_requestVars["codigoArtigoRelacionado"];
			$params[0]->setData('conflitoInteresse', $params[3]->_requestVars["conflitoInteresse"]);
			$params[1]->setData('conflitoInteresse', $params[3]->_requestVars["conflitoInteresse"]);
			$params[2]["conflitoInteresse"] = $params[3]->_requestVars["conflitoInteresse"];
			$params[0]->setData('tema', $params[3]->_requestVars["tema"]);
			$params[1]->setData('tema', $params[3]->_requestVars["tema"]);
			$params[2]["tema"] = $params[3]->_requestVars["tema"];
			$params[0]->setData('consideracoesEticas', $params[3]->_requestVars["consideracoesEticas"]);
			$params[1]->setData('consideracoesEticas', $params[3]->_requestVars["consideracoesEticas"]);
			$params[2]["consideracoesEticas"] = $params[3]->_requestVars["consideracoesEticas"];
			$params[0]->setData('ensaiosClinicos', $params[3]->_requestVars["ensaiosClinicos"]);
			$params[1]->setData('ensaiosClinicos', $params[3]->_requestVars["ensaiosClinicos"]);
			$params[2]["ensaiosClinicos"] = $params[3]->_requestVars["ensaiosClinicos"];
			$params[0]->setData('numRegistro', $params[3]->_requestVars["numRegistro"]);
			$params[1]->setData('numRegistro', $params[3]->_requestVars["numRegistro"]);
			$params[2]["numRegistro"] = $params[3]->_requestVars["numRegistro"];
			$params[0]->setData('orgao', $params[3]->_requestVars["orgao"]);
			$params[1]->setData('orgao', $params[3]->_requestVars["orgao"]);
			$params[2]["orgao"] = $params[3]->_requestVars["orgao"];
		}
		return false;
	}

	public function publish($args)
	{
		$submissionId = $args[0]->getData('id');
		$userDao = DAORegistry::getDAO('UserDAO');
		$userDao->retrieve(
			'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
			array((string)'publicada', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$submissionId)
		);
	}
}
