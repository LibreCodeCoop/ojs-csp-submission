<?php

use APP\Services\QueryBuilders\SubmissionQueryBuilder;
use Illuminate\Database\Capsule\Manager as Capsule;

import('plugins.generic.cspSubmission.class.AbstractPlugin');

/**
 * @file plugins/generic/cspSubmission/class/SubmissionCsp.inc.php
 *
 * @class SubmissionCsp
 *
 * @brief Class for modify behaviors of submissions
 *
 */
class SubmissionCsp extends AbstractPlugin
{
	public function getBackendListProperties($args)
	{
		$args[0][] = 'codigoArtigo';
	}

	public function add($args)
	{
		$userDao = DAORegistry::getDAO('UserDAO');
		$result = $userDao->retrieve(
			<<<QUERY
			SELECT CONCAT(LPAD(count(*)+1, CASE WHEN count(*) > 9999 THEN 5 ELSE 4 END, 0), '/', DATE_FORMAT(now(), '%y')) code
			FROM submissions
			WHERE YEAR(date_submitted) = YEAR(now())
			QUERY
		);
		$row = $result->current();
		$args[0]->setData('codigoArtigo', $row->code);
		$args[1]->_requestVars["codigoArtigo"] =  $args[0]->getData('codigoArtigo');

		$userDao->update(
			'INSERT INTO csp_status (submission_id, status, date_status) VALUES (?,?,?)',
			array((int)$args[0]->getData('id'), (string)'em_progresso',(string)(new DateTimeImmutable())->format('Y-m-d H:i:s'))
		);
		return false;
	}

	public function delete($args)
	{
		$userDao = DAORegistry::getDAO('UserDAO');
		$userDao->update(
			'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
			array((string)'deletada', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$args[0]->getData('id'))
		);
	}

	public function getManyQueryBuilder($args)
	{
		$request = \Application::get()->getRequest();
		if($request->getUserVar('substage')){
			$args[1]["substage"] = $request->getUserVar('substage');
			$sessionManager = SessionManager::getManager();
			$session = $sessionManager->getUserSession();
			$session->setSessionVar('substage', $request->getUserVar('substage'));
		}
	}

	public function getManyQueryObject($args)
	{
		$request = \Application::get()->getRequest();
		if($request->_router->_page == 'submissions' or $request->_dispatcher->_router->_dispatcher->_router->_handler->_id == '_submissions'){
			$qb = $args[0];
			$status = $request->getUserVar('status');
			$sessionManager = SessionManager::getManager();
			$session = $sessionManager->getUserSession();
			$role = $session->getSessionVar('role');
			$substage = $session->getSessionVar('substage');

			if ($substage) {
				$substages = explode(',', str_replace("'", "", $substage));
				$qb->leftJoin('csp_status as sc', 'sc.submission_id', '=', 's.submission_id');
				$qb->whereIn('sc.status', $substages);

				if ($substage == 'ava_aguardando_autor_mais_60_dias') {
					$qb->where('sc.date_status', '<=', date('Y-m-d H:i:s', strtotime('-2 months')));
				}
			}
			$qb->wheres[1]["values"][0] = $status;
			$qb->bindings["where"][1] = $status;
			if ($role == "Gerente") {
				unset($qb->joins[0]->wheres[1]);
			}
			if ($role == "Autor") {
				$qb->where('sa.user_group_id', '=', 14);
			}
			if ($role == "Avaliador") {
				$qb->where('ra.date_completed', '=', null);
			}
			if($request->_requestVars['searchPhrase']){
				$qb->orwhere([
					['ps.setting_name','=','codigoArtigo'],
					['ps.setting_value','like','%'.$request->getUserVar('searchPhrase').'%'],
					]);
			}
			$qb->orders[0]["column"] = 's.date_last_activity';
			$qb->orders[0]["direction"] = 'asc';
		}
	}
}
