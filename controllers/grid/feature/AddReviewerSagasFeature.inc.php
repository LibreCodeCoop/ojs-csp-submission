<?php


import('lib.pkp.classes.controllers.grid.feature.GridFeature');

class AddReviewerSagasFeature extends GridFeature
{
    /**
     * Constructor.
     */
    public function __construct($id = 'addReviewerSagas')
    {
        parent::__construct($id);
    }

    /**
     * Undocumented function
     *
     * @param array[grid]<ReviewerGridHandler> $args
     * @return void
     */
    public function fetchGrid($args)
    {
        if (!is_a($args['grid'], 'ReviewerGridHandler')) {
            return;
        }
        $above = $args['grid']->getActions('above');
        foreach ($above as $position => $action) {
            if (is_a($action, 'LinkAction') && $action->getId() == 'addReviewer') {
                unset($args['grid']->_actions['above'][$position]);
            }
        }
    }
}