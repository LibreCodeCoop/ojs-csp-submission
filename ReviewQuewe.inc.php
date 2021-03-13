<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');

class ReviewQuewe extends ScheduledTask
{
    /** @var array */
    protected $args = [];
    /**@var $reviewAssignmentDao ReviewAssignmentDAO */
    private $reviewAssignmentDao;
    public function __construct($args)
    {
        $this->args = $args;
        parent::__construct($args);
        $this->reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
    }

    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName()
    {
        return __('plugins.generic.cspSubmission.ReviewQuewe');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    protected function executeActions()
    {
        $result = $this->reviewAssignmentDao->retrieve(
            <<<SQL
            SELECT *
              FROM review_assignments
             WHERE declined = 0
               AND cancelled = 0
               AND date_completed IS NULL
            SQL
        );
        foreach ($result as $review) {
            if (!$review['date_confirmed']) {
                $incrementedResponseDue = strtotime($review['date_response_due'] . ' + ' . $this->args[0] . ' days');
                if ($incrementedResponseDue < time()) {
                    $this->unasign($review);
                    continue;
                }
            } else {
                $incrementedDateDue = strtotime($review['date_due'] . ' + ' . $this->args[0] . ' days');
                if ($incrementedDateDue < time()) {
                    $this->unasign($review);
                    continue;
                }
            }
        }
        return true;
    }

    private function unasign(array $review)
    {
    }
}
