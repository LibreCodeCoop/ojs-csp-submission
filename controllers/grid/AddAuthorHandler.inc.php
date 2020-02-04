<?php

import('lib.pkp.classes.controllers.grid.GridHandler');

class AddAuthorHandler extends GridHandler {

    function __construct() {
        parent::__construct();
        $this->plugin = PluginRegistry::getPlugin('generic', 'cspsubmissionplugin');
    }
    public function searchAuthor($args, $request)
    {
        import('lib.pkp.classes.form.Form');
        $form = new Form($this->plugin->getTemplateResource('authorFormAdd.tpl'));
        return new JSONMessage(true, $form->fetch($request));
    }
}