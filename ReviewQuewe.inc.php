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
        import('lib.pkp.classes.core.PKPApplication');
        // $router = new PKPRouter();
        // $router->setApplication(PKPApplication::get());
        $request = Application::get()->getRequest();
        $router = $request->getRouter();
        $router->_contextPaths = [];
        $journalDao = DAORegistry::getDAO('JournalDAO'); /* @var $journalDao JournalDAO */
        $journals = $journalDao->getAll(true);
        while ($journal = $journals->next()) {
            $_SERVER['PATH_INFO'] = $journal->getPath();
            // Initialize context by path, don't remove
            $router->getContext($request, 1, true);

            $assignedReviewers = $this->assignedReviewers();
            $queue = $this->getQueue();
            foreach ($queue as $queueReviewRound) {
                $queueOfRound = $queue[$queueReviewRound['review_round_id']];
                if (isset($assignedReviewers[$queueOfRound['review_round_id']])) {
                    $assignedInRound = $assignedReviewers[$queueOfRound['review_round_id']];
                } else {
                    $assignedInRound = ['total' => 0];
                }
                if ($assignedInRound['total'] < $this->args[2] && count($queueOfRound)) {
                    $reviewer = $queueOfRound[0];
                    $this->addReviewer($reviewer['user_id'], $reviewer['review_round_id']);
                    $this->removeFromQueue($queueOfRound['user_id'], $queueOfRound['review_round_id']);
                }
            }
        }
    }

    private function addReviewer($reviewerId, $reviewRoundId)
    {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
        $reviewRound = $reviewRoundDao->getById($reviewRoundId);

        $submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
        $submission = $submissionDao->getById($reviewRound->getSubmissionId());

        import('lib.pkp.controllers.grid.users.reviewer.form.ReviewerForm');
        $reviewerForm = new ReviewerForm($submission, $reviewRound);
        $reviewerForm->setData('reviewerId', $reviewerId);
        $reviewerForm->setData('reviewDueDate', '?');
        $reviewerForm->setData('responseDueDate', '?');

        $reviewerForm->execute();
    }

    private function removeFromQueue($userId, $reviewRoundId)
    {
        $this->reviewAssignmentDao->update(
            'DELETE FROM reviewer_queue WHERE user_id = ? AND review_round_id = ?',
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
              FROM reviewer_queue
             ORDER BY created_at
            SQL
        );
        $return = [];
        foreach($result as $row) {
            $return[$row['review_round_id']][] = $row;
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
        foreach($result as $row) {
            $return[$row['review_round_id']] = $row;
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
