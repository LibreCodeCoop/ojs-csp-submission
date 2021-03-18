<?php

import('lib.pkp.classes.controllers.grid.users.reviewer.PKPReviewerGridHandler');

class ReviewerGridHandler extends PKPReviewerGridHandler {

	public function __construct()
	{
		parent::__construct();
		$this->addRoleAssignment(
			[ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR],
			['removeFromQueue']
		);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$this->markRoleAssignmentsChecked();
		return parent::authorize($request, $args, $roleAssignments);
	}

	function getRequestArgs() {
		$request = \Application::get()->getRequest();
		if (strpos($request->getRequestPath(), 'reviewer-grid/remove-from-queue')) {
			return [];
		}
		return parent::getRequestArgs();
	}

	/**
	 * Edit a reviewer
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateReviewer($args, $request) {
		$selectionType = $request->getUserVar('selectionType');
		$formClassName = $this->_getReviewerFormClassName($selectionType);

		// Form handling
		import('lib.pkp.controllers.grid.users.reviewer.form.' . $formClassName );
		$reviewerForm = new $formClassName($this->getSubmission(), $this->getReviewRound());
		$reviewerForm->addCheck(new FormValidatorRegExp(
			$reviewerForm,
			'reviewerId',
			FORM_VALIDATOR_REQUIRED_VALUE,
			'editor.review.reviewerId.emptyArray',
			'/\[(\d,?)+(?<!,)\]/i'
		));
		$reviewerForm->readInputData();
		if ($reviewerForm->validate()) {
			$reviewerIds = json_decode($reviewerForm->getData('reviewerId'), true);
			$assignedIds = [];
			foreach ($reviewerIds as $reviewerId) {
				$reviewerForm->setData('reviewerId', $reviewerId);
				$reviewAssignment = $reviewerForm->execute();
				$assignedIds[] = $reviewAssignment->getId();
			}
			// Create and render the JSON message with the
			// event to be triggered on the client side.
			import('lib.pkp.classes.core.JSONMessage');
			$json = new JSONMessage(true, '');
			$json->setEvent('dataChanged', $assignedIds);
			return $json;
		} else {
			// There was an error, redisplay the form
			return new JSONMessage(true, $reviewerForm->fetch($request));
		}
	}

	public function removeFromQueue($args, $request) {
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignmentDao->update(
			'DELETE FROM csp_reviewer_queue WHERE user_id = ? AND review_round_id = ?',
			[
				'user_id' => $args['userId'],
				'review_round_id' => $args['reviewRoundId']
			]
		);
		$json = new JSONMessage(true, null);
		$json->setEvent('dataChanged', null);
		return $json;
	}
}