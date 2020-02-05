<?php

import('lib.pkp.classes.controllers.grid.GridHandler');
import('classes.core.ServicesContainer');

class AddAuthorHandler extends GridHandler {

    function __construct() {
        parent::__construct();
        $this->plugin = PluginRegistry::getPlugin('generic', 'cspsubmissionplugin');
    }
    public function searchAuthor($args, $request)
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_PKP_USER);
        $authorId = (int) $request->getUserVar('authorId');
        $submissionDAO = Application::getSubmissionDAO();
        $submission = $submissionDAO->getById($request->getUserVar('submissionId'));

        $authorDao = DAORegistry::getDAO('AuthorDAO');
        $author = $authorDao->getById($authorId, $submission->getId());

        // Form handling
        import('lib.pkp.controllers.grid.users.author.form.AuthorForm');
        $authorForm = new AuthorForm($submission, $author, 'submissionId');
        $authorForm->setTemplate($this->plugin->getTemplateResource('authorFormAdd.tpl'));

        $userService = ServicesContainer::instance()->get('user');
        $user = $userService->getUser($request->getUserVar('userId'));
        $authorForm->_data = $user->_data;
        $authorForm->setData('includeInBrowse', true);
        $authorForm->setData('csrfToken', $request->getSession()->getCSRFToken());

        return new JSONMessage(true, $authorForm->fetch($request));
    }
}