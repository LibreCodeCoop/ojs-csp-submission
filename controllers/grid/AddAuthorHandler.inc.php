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

        import('lib.pkp.controllers.grid.users.author.form.AuthorForm');
        $form = new Form($this->plugin->getTemplateResource('authorFormAdd.tpl'));

        $countryDao = DAORegistry::getDAO('CountryDAO');
        $countries = $countryDao->getCountries();
        $form->setData('countries', $countries);

        if ($args['type'] == 'csp') {
            $userDao = DAORegistry::getDAO('UserDAO');
            $userCsp = $userDao->retrieve(
                <<<QUERY
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(p.nome, ' ', 1), ' ', -1) as given_name,
                       TRIM( SUBSTR(p.nome, LOCATE(' ', p.nome)) ) family_name,
                       pais, email, orcid
                  FROM csp.Pessoa p
                 WHERE p.idPessoa = ?
                QUERY,
                [$args['userId']]
            )->GetRowAssoc(0);
            $locale = AppLocale::getLocale();
            $form->setData('givenName', [$locale => $userCsp['given_name']]);
            $form->setData('familyName', [$locale => $userCsp['family_name']]);
            $form->setData('email', $userCsp['email']);
            $form->setData('orcid', $userCsp['orcid']);
            if (!empty($userCsp['pais'])) {
                $abbreviation = array_search($userCsp['pais'], $countries);
                if ($abbreviation) {
                    $form->setData('country', $abbreviation);
                }
            }
        } else {
            $userService = ServicesContainer::instance()->get('user');
            $user = $userService->getUser($request->getUserVar('userId'));
            // $form->_data = $user->_data;
            $form->setData('givenName', $user->getData('givenName'));
            $form->setData('familyName',$user->getData('familyName'));
            $form->setData('email', $user->getData('email'));
            $form->setData('country', $user->getData('country'));
            $form->setData('orcid', $user->getData('orcid'));
        }

        $router = $request->getRouter();
        $context = $router->getContext($request);
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $authorUserGroups = $userGroupDao->getByRoleId($context->getId(), ROLE_ID_AUTHOR);
        $form->setData('authorUserGroups', $authorUserGroups);

        $form->setData('submissionId', $args['submissionId']);
        $form->setData('includeInBrowse', true);
        $form->setData('csrfToken', $request->getSession()->getCSRFToken());

        return new JSONMessage(true, $form->fetch($request));
    }
}