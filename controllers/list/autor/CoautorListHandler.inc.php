<?php
/**
 * @file controllers/list/submissions/SubmissionsListHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsListHandler
 * @ingroup controllers_list
 *
 * @brief Instantiates and manages a UI component to list submissions.
 */
import('lib.pkp.controllers.list.ListHandler');
import('lib.pkp.classes.db.DBResultRange');
import('lib.pkp.classes.submission.Submission');
import('classes.core.ServicesContainer');
import('lib.pkp.classes.db.DBResultRange');
import('lib.pkp.classes.submission.Submission');

class CoautorListHandler extends ListHandler {

	/**
	 * @copydoc PKPSubmissionsListHandler::getConfig()
	 */
	// public function getConfig() {
	// 	$config = parent::getConfig();

	// 	$request = Application::getRequest();
	// 	if ($request->getContext()) {
	// 		if (!isset($config['filters'])) {
	// 			$config['filters'] = array();
	// 		}
	// 		$config['filters']['sectionIds'] = array(
	// 			'heading' => __('section.sections'),
	// 			'filters' => self::getSectionFilters(),
	// 		);
	// 	}

	// 	return $config;
	// }

	/**
	 * @copydoc PKPSubmissionsListHandler::getWorkflowStages()
	 */
	// public function getWorkflowStages() {
	// 	return array(
	// 		array(
	// 			'param' => 'stageIds',
	// 			'val' => WORKFLOW_STAGE_ID_SUBMISSION,
	// 			'title' => __('manager.publication.submissionStage'),
	// 		),
	// 		array(
	// 			'param' => 'stageIds',
	// 			'val' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
	// 			'title' => __('manager.publication.reviewStage'),
	// 		),
	// 		array(
	// 			'param' => 'stageIds',
	// 			'val' => WORKFLOW_STAGE_ID_EDITING,
	// 			'title' => __('submission.copyediting'),
	// 		),
	// 		array(
	// 			'param' => 'stageIds',
	// 			'val' => WORKFLOW_STAGE_ID_PRODUCTION,
	// 			'title' => __('manager.publication.productionStage'),
	// 		),
	// 	);
	// }

	/**
	 * Compile the sections for passing as filters
	 *
	 * @return array
	 */
	static function getSectionFilters() {
		$request = Application::getRequest();
		$context = $request->getContext();

		if (!$context) {
			return array();
		}

		import('classes.core.ServicesContainer');
		$sections = ServicesContainer::instance()
				->get('section')
				->getSectionList($context->getId());

		return array_map(function($section) {
			return array(
				'param' => 'sectionIds',
				'val' => $section['id'],
				'title' => $section['title'],
			);
		}, $sections);
	}

	/** @var int Count of items to retrieve in initial page/request */
	public $_count = 5;

	/** @var array Query parameters to pass with every GET request */
	public $_getParams = array();

	/** @var string Used to generate URLs to API endpoints for this component. */
	public $_apiPath = '_submissions';

	/**
	 * @copydoc ListHandler::init()
	 */
	public function init( $args = array() ) {
		parent::init($args);

		$this->_count = isset($args['count']) ? (int) $args['count'] : $this->_count;
		$this->_getParams = isset($args['getParams']) ? $args['getParams'] : $this->_getParams;
	}

	/**
	 * @copydoc ListHandler::getConfig()
	 */
	public function getConfig() {

		$request = Application::getRequest();

		$config = array();

		if ($this->_lazyLoad) {
			$config['lazyLoad'] = true;
		} else {
			$config['items'] = $this->getItems();
			$config['itemsMax'] = $this->getItemsMax();
		}

		// URL to add a new submission
		$config['addUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_PAGE,
			null,
			'submission',
			'wizard'
		);

		// URL to view info center for a submission
		$config['infoUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'informationCenter.SubmissionInformationCenterHandler',
			'viewInformationCenter',
			null,
			array('submissionId' => '__id__')
		);

		// URL to assign a participant
		$config['assignParticipantUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.users.stageParticipant.StageParticipantGridHandler',
			'addParticipant',
			null,
			array('submissionId' => '__id__', 'stageId' => '__stageId__')
		);

		$config['apiPath'] = $this->_apiPath;

		$config['count'] = $this->_count;
		$config['page'] = 1;

		$config['getParams'] = $this->_getParams;

		$config['filters'] = array(
			'attention' => array(
				'filters' => array(
					array(
						'param' => 'isOverdue',
						'val' => true,
						'title' => __('common.overdue'),
					),
					array(
						'param' => 'isIncomplete',
						'val' => true,
						'title' => __('submissions.incomplete'),
					),
					array(
						'param' => 'daysInactive',
						'val' => 30,
						'title' => __('submissions.inactive',array('days' => '30')),
					),
				),
			),
			// 'stageIds' => array(
			// 	'heading' => __('settings.roles.stages'),
			// 	'filters' => $this->getWorkflowStages(),
			// ),
		);

		// Load grid localisation files
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_GRID);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);

		$config['i18n'] = array(
			'id' => __('common.id'),
			'title' => __($this->_title),
			'add' => __('submission.submit.newSubmissionSingle'),
			'search' => __('common.search'),
			'clearSearch' => __('common.clearSearch'),
			'itemCount' => __('submission.list.count'),
			'itemsOfTotal' => __('submission.list.itemsOfTotal'),
			'loadMore' => __('grid.action.moreItems'),
			'loading' => __('common.loading'),
			'incomplete' => __('submissions.incomplete'),
			'delete' => __('common.delete'),
			'infoCenter' => __('submission.list.infoCenter'),
			'yes' => __('common.yes'),
			'no' => __('common.no'),
			'deleting' => __('common.deleting'),
			'currentStage' => __('submission.list.currentStage'),
			'filter' => __('common.filter'),
			'filterRemove' => __('common.filterRemove'),
			'itemOrdererUp' => __('submission.list.itemOrdererUp'),
			'itemOrdererDown' => __('submission.list.itemOrdererDown'),
			'viewSubmission' => __('submission.list.viewSubmission'),
			'reviewsCompleted' => __('submission.list.reviewsCompleted'),
			'revisionsSubmitted' => __('submission.list.revisionsSubmitted'),
			'copyeditsSubmitted' => __('submission.list.copyeditsSubmitted'),
			'galleysCreated' => __('submission.list.galleysCreated'),
			'filesPrepared' => __('submission.list.filesPrepared'),
			'discussions' => __('submission.list.discussions'),
			'assignEditor' => __('submission.list.assignEditor'),
			'viewMore' => __('list.viewMore'),
			'viewLess' => __('list.viewLess'),
			'notFoundAndCreate' => 'Autor não encontrado, cadastrar'
		);

		// Attach a CSRF token for post requests
		$config['csrfToken'] = $request->getSession()->getCSRFToken();

		// Provide required constants
		import('lib.pkp.classes.submission.reviewRound.ReviewRound');
		import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignment');
		import('lib.pkp.classes.services.PKPSubmissionService'); // STAGE_STATUS_SUBMISSION_UNASSIGNED

		return $config;
	}

	/**
	 * @copydoc ListHandler::getItems()
	 */
	public function getItems() {
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		$submissionService = ServicesContainer::instance()->get('submission');
		$submissions = $submissionService->getSubmissions($context->getId(), $this->_getItemsParams());
		$items = array();
		if (!empty($submissions)) {
			$propertyArgs = array(
				'request' => $request,
			);
			foreach ($submissions as $submission) {
				$items[] = $submissionService->getBackendListProperties($submission, $propertyArgs);
			}
		}

		return $items;
	}

	/**
	 * @copydoc ListHandler::getItemsMax()
	 */
	public function getItemsMax() {
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		return ServicesContainer::instance()
			->get('submission')
			->getSubmissionsMaxCount($context->getId(), $this->_getItemsParams());
	}

	/**
	 * @copydoc ListHandler::_getItemsParams()
	 */
	protected function _getItemsParams() {
		return array_merge(
			array(
				'count' => $this->_count,
				'offset' => 0,
			),
			$this->_getParams
		);
	}
}
