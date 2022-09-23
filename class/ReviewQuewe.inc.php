<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');

class ReviewQuewe extends ScheduledTask
{
    /** @var array */
    protected $args = [];
    /** @var UserDAO */
    private $userDao;
    /** @var User */
    private $user;
    /**@var $reviewAssignmentDao ReviewAssignmentDAO */
    private $reviewAssignmentDao;
    public function __construct($args)
    {
        $this->args = array_combine(
            [
                'incrementDays',
                'idSupportUser',
                'maxAssigned',
                'reviewMethod'
            ],
            $args
        );
        parent::__construct($args);
        $this->reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');

        $this->userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
        $this->user = $this->userDao->getById($this->args['idSupportUser']);
        Registry::set('user', $this->user);

    }

    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName()
    {
        return __('plugins.generic.cspSubmission.class.ReviewQuewe');
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
            $reviewAssignment = $this->reviewAssignmentDao->_fromRow($result->current());
            if (!$reviewAssignment->getDateConfirmed()) {
                $incrementedResponseDue = strtotime(
                    $reviewAssignment->getDateResponseDue() . ' + ' . $this->args['incrementDays'] . ' days'
                );
                if ($incrementedResponseDue < time()) {
                    $this->unasign($reviewAssignment);
                    $result->MoveNext();
                    continue;
                }
            } else {
                $incrementedDateDue = strtotime(
                    $reviewAssignment->getDateDue() . ' + ' . $this->args['incrementDays'] . ' days'
                );
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
        import('lib.pkp.classes.core.PKPApplication');
        $application = Application::get();
        $request = $application->getRequest();
        $request->setDispatcher($application->getDispatcher());
        $router = $request->getRouter();
        $router->_contextPaths = [];
        $journalDao = DAORegistry::getDAO('JournalDAO'); /* @var $journalDao JournalDAO */
        $journals = $journalDao->getAll(true);
        while ($journal = $journals->next()) {
            $_SERVER['PATH_INFO'] = $journal->getPath();
            $context = $router->getContext($request, 1, true);

            $assignedReviewers = $this->assignedReviewers();
            $queue = $this->getQueue();
            foreach ($queue as $reviewRoundId => $queueOfRound) {
                if (isset($assignedReviewers[$reviewRoundId])) {
                    $assignedInRound = $assignedReviewers[$reviewRoundId];
                } else {
                    $assignedInRound = ['total' => 0];
                }
                if ($assignedInRound['total'] < $this->args['maxAssigned'] && count($queueOfRound)) {
                    $reviewer = $queueOfRound[0];
                    $this->addReviewer($reviewer['user_id'], $reviewer['review_round_id'], $context);
                    $this->removeFromQueue($reviewer['user_id'], $reviewRoundId);
                }
            }
        }
    }

    private function addReviewer(int $reviewerId, int $reviewRoundId, Journal $context)
    {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
        $reviewRound = $reviewRoundDao->getById($reviewRoundId);

        $submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
        $submission = $submissionDao->getById($reviewRound->getSubmissionId());

        import('lib.pkp.controllers.grid.users.reviewer.form.ReviewerForm');
        $reviewerForm = new ReviewerForm($submission, $reviewRound);
        $reviewerForm->setData('reviewerId', $reviewerId);
        $reviewerForm->setData('reviewMethod', constant($this->args['reviewMethod']));
        $reviewerForm->setData('template', $reviewerForm->_getMailTemplateKey($context));

        $numWeeks = (int) $context->getData('numWeeksPerReview');
        if ($numWeeks<=0) $numWeeks=4;
        $reviewerForm->setData('reviewDueDate', strtotime('+' . $numWeeks . ' week'));

        $numWeeks = (int) $context->getData('numWeeksPerResponse');
        if ($numWeeks<=0) $numWeeks=3;
        $reviewerForm->setData('responseDueDate', strtotime('+' . $numWeeks . ' week'));

        $reviewerForm->execute();
    }

    private function removeFromQueue($userId, $reviewRoundId)
    {
        $this->reviewAssignmentDao->update(
            'DELETE FROM csp_reviewer_queue WHERE user_id = ? AND review_round_id = ?',
            [
                'user_id' => $userId,
                'review_round_id' => $reviewRoundId
            ]
        );
    }

    private function getQueue()
    {
        $result = $this->reviewAssignmentDao->retrieve(
            <<<SQL
            SELECT *
              FROM csp_reviewer_queue
             ORDER BY created_at
            SQL
        );
        $return = [];
        while (!$result->EOF) {
            $row = $result->current();
            $return[$row['review_round_id']][] = $row;
            $result->MoveNext();
        }
        return $return;
    }

    /**
     * Get available reviewers
     *
     * @return array
     */
    public function assignedReviewers()
    {
        $result = $this->reviewAssignmentDao->retrieve(
            <<<SQL
            SELECT count(*) as total,
                   review_round_id
              FROM review_assignments
             WHERE declined = 0
               AND cancelled = 0
               AND date_completed IS NULL
             GROUP BY review_round_id
            SQL
        );
        $return = [];
        while (!$result->EOF) {
            $row = $result->current();
            $return[$row['review_round_id']][] = $row;
            $result->MoveNext();
        }
        return $return;
    }

    private function unasign(ReviewAssignment $reviewAssignment)
    {
        $reviewRoundId = $reviewAssignment->getReviewRoundId();
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
        $reviewRound = $reviewRoundDao->getById($reviewRoundId);

        $reviewRoundDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
        $submission = $reviewRoundDao->getById($reviewAssignment->getSubmissionId());

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
