<?php

use PKP\components\listPanels\ListPanel;

import('classes.core.Services');

class CoautorListPanel extends ListPanel {

	/** @var int Count of items to retrieve in initial page/request */
	public $count = 5;

	/**
	 * @copydoc GridHandler::getConfig()
	 */
	public function getConfig() {

		$request = \Application::get()->getRequest();

		$config = parent::getConfig();

		$config['minWordsToSearch'] = 3;
		$config['lazyLoad'] = false;

		$config['fillUser'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.users.author.AuthorGridHandler',
			'addAuthor'
		);

		$config['count'] = $this->count;
		$config['page'] = 1;

		$config['getParams'] = $this->getParams;

		$config['searchPhrase'] = 'Buscando um troço';
		$config['apiUrl'] = $request->getDispatcher()->url($request, ROUTE_API, $request->getContext()->getPath(), 'users');

		// Attach a CSRF token for post requests
		$config['csrfToken'] = $request->getSession()->getCSRFToken();

		return $config;
	}
}
