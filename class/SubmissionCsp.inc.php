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
	public function getBackendListProperties($args) {
		$args[0][] = 'codigoArtigo';
	}

	public function delete($args){
		$userDao = DAORegistry::getDAO('UserDAO');
		$userDao->retrieve(
			'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
			array((string)'deletada', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$args[0]->getData('id'))
		);
	}

	public function getManyQueryBuilder($args) {
		$request = \Application::get()->getRequest();
		$args[1]["substage"] = $request->_requestVars["substage"];
	}

	public function getManyQueryObject($args) {
		/**
		 * @var SubmissionQueryBuilder
		 */
		$qb = $args[0];
		$request = \Application::get()->getRequest();
		$substage = $request->_requestVars["substage"];
		$status = $request->_requestVars["status"];

		$sessionManager = SessionManager::getManager();
		$session = $sessionManager->getUserSession();
		$role = $session->getSessionVar('role');

		if ($substage) {
			$substages = explode(',',str_replace("'", "", $substage));
			$queryStatusCsp = Capsule::table('status_csp');
			$queryStatusCsp->select(Capsule::raw('DISTINCT status_csp.submission_id'));
			$queryStatusCsp->whereIn('status_csp.status', $substages);

			if($substage == 'ava_aguardando_autor_mais_60_dias'){
				$queryStatusCsp->where('status_csp.date_status', '<=', date('Y-m-d H:i:s', strtotime('-2 months')));
			}elseif($substage == "'em_progresso'"){
				$qb->where('s.date_submitted','=' ,null);
			}

			$qb->wheres[1]["values"][0] = $status;
			$qb->bindings["where"][1] = $status;

			if($role == "Gerente"){
				unset($qb->joins[0]->wheres[1]);
			}
			if($role == "Autor"){
				$qb->where('sa.user_group_id','=' ,14);
			}
			if($role == "Avaliador"){
				$qb->where('ra.date_completed','=' ,null);
			}
		}
		$qb->orders[0]["column"] = 's.date_last_activity';
		$qb->orders[0]["direction"] = 'asc';
	}
}
