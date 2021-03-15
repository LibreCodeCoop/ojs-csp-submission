<?php

import('lib.pkp.classes.controllers.grid.GridRow');

class ReviewerQueueGridRow extends GridRow {
	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$element = $row->getData();
		$columnId = $column->getId();
		switch ($columnId) {
			case 'name':
				return array('label' => $element['user']->getFullName());
			case 'considered':
				return array('label' => __('plugins.generic.CspSubmission.reviewerQueue.inQueue'));
			case 'actions':
				return array('label' => '');
		}
		return [];
	}

	/**
	 * To be used by a GridRow to generate a rendered representation of
	 * the element for the given column.
	 *
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return string the rendered representation of the element for the given column
	 */
	function renderCell($request, $row, $column) {
		// Assign values extracted from the element for the cell.
		$templateMgr = TemplateManager::getManager($request);
		$templateVars = $this->getTemplateVarsFromRowColumn($row, $column);
		foreach ($templateVars as $varName => $varValue) {
			$templateMgr->assign($varName, $varValue);
		}
		$templateMgr->assign(array(
			'column' => $column,
			'actions' => $this->getCellActions($column, $row),
			'flags' => $column->getFlags(),
			'formLocales' => AppLocale::getSupportedFormLocales(),
		));
		$template = $column->getTemplate();
		assert(!empty($template));
		return $templateMgr->fetch($template);
	}

	private function getCellActions($column, $row) {
		$data = $row->getData();
		$columnId = $column->getId();
		switch ($columnId) {
			case 'actions':
				$request = \Application::get()->getRequest();
				$router = $request->getRouter();
				import('lib.pkp.classes.linkAction.LinkAction');
				import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
				return [new LinkAction(
					'removeFromQueue',
					new RemoteActionConfirmationModal(
						$request->getSession(),
						__('plugins.generic.CspSubmission.reviewer.removeFromQueueText'),
						__('plugins.generic.CspSubmission.reviewer.removeFromQueue'),
						$router->url(
							$request, null,
							'plugins.generic.cspSubmission.controllers.grid.users.reviewer.ReviewerGridHandler',
							'removeFromQueue',
							null,
							array(
								'userId' => $data['user_id'],
								'reviewRoundId' => $data['review_round_id'],
								'stageId' => $request->getUserVar('stageId'),
								'submissionId' => $request->getUserVar('submissionId')
							)
						),
						'modal_information'
					),
					__('plugins.generic.CspSubmission.reviewer.removeFromQueue'),
					'removefromqueue'
				)];
		}
		return [];
	}
}
