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
        import('lib.pkp.classes.form.Form');

        $request = Application::getRequest();
        $context = $request->getContext();

        $userService = ServicesContainer::instance()->get('user');
        $user = $userService->getUser($request->getUserVar('userId'));

        $locale = AppLocale::getLocale();
        $form = new Form($this->plugin->getTemplateResource('authorFormAdd.tpl'));
        $form->_data = $user->_data;

        $countryDao = DAORegistry::getDAO('CountryDAO');
        $countries = $countryDao->getCountries($locale);
        $form->setData('countries', $countries);
        $form->setData('submissionId', $request->getUserVar('submissionId'));
        $form->setData('includeInBrowse', 'on');
        $form->setData('csrfToken', $request->getSession()->getCSRFToken());

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $authorUserGroups = $userGroupDao->getByRoleId($context->getId(), ROLE_ID_AUTHOR);
        $form->setData('authorUserGroups', $authorUserGroups);

        return new JSONMessage(true, $form->fetch($request));
    }
}