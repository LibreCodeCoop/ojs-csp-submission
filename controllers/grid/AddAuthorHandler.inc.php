<?php

import('lib.pkp.classes.controllers.grid.GridHandler');

class AddAuthorHandler extends GridHandler {

    function __construct() {
        parent::__construct();
        $this->plugin = PluginRegistry::getPlugin('generic', CSPSUBMISSION_PLUGIN_NAME);
    }
    public function searchAuthor($args, $request)
    {
        import('lib.pkp.classes.form.Form');
        $form = new Form($this->plugin->getTemplateResource('searchAuthor.tpl'));
        return new JSONMessage(true, $form->fetch($request));
    }
}