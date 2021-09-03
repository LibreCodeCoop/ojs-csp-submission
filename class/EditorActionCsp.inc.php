<?php

import('plugins.generic.cspSubmission.class.AbstractPlugin');

/**
 * @file plugins/generic/cspSubmission/class/EditorActionCsp.inc.php
 *
 * @class EditorActionCsp
 *
 * @brief Class for modify behaviors in the editor decision moment
 *
 */
class EditorActionCsp extends AbstractPlugin
{
	/**
	 * Updates csp status tables as the editor decision
	 *
	 * @param [type] $args
	 * @return void
	 */
	public function recordDecision($args) {
		if($args[2]["decision"] == 4 or $args[2]["decision"] == 9){
			$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
			$dateDecided = $args[2]["dateDecided"];
			
			$userDao->retrieve(
				'UPDATE status_csp SET status = ?, date_status = ? WHERE submission_id = ?',
				array((string)'rejeitada', (string)$dateDecided, (int)$args[0]->getData('id'))
			);
		}
	}
}
