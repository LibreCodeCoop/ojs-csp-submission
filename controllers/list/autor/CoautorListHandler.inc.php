<?php

import('lib.pkp.classes.services.UserService');
import('lib.pkp.controllers.list.ListHandler');
import('classes.core.ServicesContainer');

class CoautorListHandler extends ListHandler {

	/** @var int Count of items to retrieve in initial page/request */
	public $_count = 5;

	/** @var array Query parameters to pass with every GET request */
	public $_getParams = array();

	/** @var string Used to generate URLs to API endpoints for this component. */
	public $_apiPath = 'users';

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

		$config['minWordsToSearch'] = 3;
		if ($this->_lazyLoad) {
			$config['lazyLoad'] = true;
		} elseif(!$config['minWordsToSearch']) {
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
		$config['fillUser'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'plugins.generic.cspSubmission.controllers.grid.AddAuthorHandler',
			'searchAuthor'
		);

		$config['apiPath'] = $this->_apiPath;

		$config['count'] = $this->_count;
		$config['page'] = 1;

		$config['getParams'] = $this->_getParams;

		$config['i18n'] = array(
			'title' => __($this->_title),
			'search' => __('common.search'),
			'clearSearch' => __('common.clearSearch'),
			'itemCount' => __('author.list.count'),
			'itemsOfTotal' => __('author.list.itemsOfTotal'),
			'loadMore' => __('grid.action.moreItems'),
			'loading' => __('common.loading'),
			'filter' => __('common.filter'),
			'filterRemove' => __('common.filterRemove'),
			'viewMore' => __('list.viewMore'),
			'viewLess' => __('list.viewLess'),
			'notFoundAndCreate' => __('plugins.generic.cspSubmission.authorNotFoundCreate'),
			'informAName' => __('plugins.generic.cspSubmission.informAName'),
		);

		// Attach a CSRF token for post requests
		$config['csrfToken'] = $request->getSession()->getCSRFToken();

		return $config;
	}

	/**
	 * @copydoc ListHandler::getItems()
	 */
	public function getItems() {
		$userService = ServicesContainer::instance()->get('user');
		$request = Application::getRequest();
		$context = $request->getContext();
		$users = $userService->getUsers($context->getId(), $this->_getItemsParams());
		$items = array();
		foreach ($users as $user) {
			$items[] = [
				'fullName' => $user->getFullName(),
				'email' => $user->getEmail(),
				'id' => $user->getId(),
				'type' => $user->getData('type'),
				'instituicao' => $user->getData('instituicao')
			];
		}

		return $items;
	}

	/**
	 * @copydoc ListHandler::getItemsMax()
	 */
	public function getItemsMax() {
		$request = Application::getRequest();
		$context = $request->getContext();

		return ServicesContainer::instance()
			->get('user')
			->getUsersMaxCount($context->getId(), $this->_getItemsParams());
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