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
        while (!$result->EOF) {
            $reviewAssignment = $this->reviewAssignmentDao->_fromRow($result->GetRowAssoc(false));
            if (!$reviewAssignment->getDateConfirmed()) {
                $incrementedResponseDue = strtotime($reviewAssignment->getDateResponseDue() . ' + ' . $this->args[0] . ' days');
                if ($incrementedResponseDue < time()) {
                    $this->unasign($reviewAssignment);
                    $result->MoveNext();
                    continue;
                }
            } else {
                $incrementedDateDue = strtotime($reviewAssignment->getDateDue() . ' + ' . $this->args[0] . ' days');
                if ($incrementedDateDue < time()) {
                    $this->unasign($reviewAssignment);
                    $result->MoveNext();
                    continue;
                }
            }
            $result->MoveNext();
        }
        $this->proccessQueue();
        return true;
    }

    private function proccessQueue()
    {
    }

    private function unasign(ReviewAssignment $reviewAssignment)
    {
        $reviewRoundId = $reviewAssignment->getReviewRoundId();
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
        $reviewRound = $reviewRoundDao->getById($reviewRoundId);

        $reviewRoundDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
        $submission = $reviewRoundDao->getById($reviewAssignment->getSubmissionId());

        $userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
        $user = $userDao->getById($this->args[1]);
        Registry::set('user', $user);

        import('lib.pkp.controllers.grid.users.reviewer.form.UnassignReviewerForm');
        $unassignReviewerForm = new UnassignReviewerForm($reviewAssignment, $reviewRound, $submission);

        import('lib.pkp.classes.mail.SubmissionMailTemplate');
        $template = new SubmissionMailTemplate($submission, $unassignReviewerForm->getEmailKey());
        if ($template) {
            $reviewer = $userDao->getById($reviewAssignment->getReviewerId());

            $template->assignParams(array(
                'reviewerName' => $reviewer->getFullName(),
                'signatureFullName' => $user->getFullname(),
            ));
            $template->replaceParams();

            $unassignReviewerForm->setData('personalMessage', $template->getBody());
        }

        $unassignReviewerForm->setData('reviewerId', $reviewAssignment->getReviewerId());
        $unassignReviewerForm->execute();
    }
}
