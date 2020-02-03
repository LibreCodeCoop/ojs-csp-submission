<?php

import('lib.pkp.controllers.list.ListHandler');

class CoautorListHandler extends ListHandler {

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

		$config['apiPath'] = $this->_apiPath;

		$config['count'] = $this->_count;
		$config['page'] = 1;

		$config['getParams'] = $this->_getParams;

		$config['i18n'] = array(
			'title' => __($this->_title),
			'search' => __('common.search'),
			'clearSearch' => __('common.clearSearch'),
			'itemCount' => __('submission.list.count'),
			'itemsOfTotal' => __('submission.list.itemsOfTotal'),
			'loadMore' => __('grid.action.moreItems'),
			'loading' => __('common.loading'),
			'filter' => __('common.filter'),
			'filterRemove' => __('common.filterRemove'),
			'viewMore' => __('list.viewMore'),
			'viewLess' => __('list.viewLess'),
			'notFoundAndCreate' => 'Autor não encontrado, cadastrar'
		);

		// Attach a CSRF token for post requests
		$config['csrfToken'] = $request->getSession()->getCSRFToken();

		return $config;
	}

	/**
	 * @copydoc ListHandler::getItems()
	 */
	public function getItems() {
		$items = array();
		for ($i = 1; $i<= $this->_count; $i++) {
			$items[] = [
				'fullTitle' => ['pt_BR' => 'Título ' . $i],
				'authorString' => 'Autor ' . $i,
				'id' => $i
			];
		}

		return $items;
	}

	/**
	 * @copydoc ListHandler::getItemsMax()
	 */
	public function getItemsMax() {
		return 70;
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
