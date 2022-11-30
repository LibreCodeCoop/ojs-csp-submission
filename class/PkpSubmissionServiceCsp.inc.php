<?php

use APP\Services\QueryBuilders\SubmissionQueryBuilder;
use Illuminate\Database\Capsule\Manager as Capsule;

import('plugins.generic.cspSubmission.class.AbstractPlugin');

/**
 * @file plugins/generic/cspSubmission/class/PkpSubmissionServiceCsp.inc.php
 *
 * @class PkpSubmissionServiceCsp
 *
 * @brief Class for modify behaviors query return
 *
 */
class PkpSubmissionServiceCsp extends AbstractPlugin
{
	public function getmany($arguments){
		$request = \Application::get()->getRequest();
		$status = $request->getUserVar('status');
		$sessionManager = SessionManager::getManager();
		$session = $sessionManager->getUserSession();
		$role = $session->getSessionVar('role');
		$substage = $session->getSessionVar('substage');
	}
}