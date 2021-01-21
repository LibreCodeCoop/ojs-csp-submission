<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');
import('lib.pkp.classes.mail.SubmissionMailTemplate');

class SendDeclinedSubmissionEmail extends ScheduledTask
{
    /** @var SubmissionDAO */
    private $submissionDAO;

    public function __construct($args)
    {
        parent::__construct($args);
        $this->submissionDAO = DAORegistry::getDAO('SubmissionDAO');
    }

    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName()
    {
        return __('plugins.generic.cspSubmission.SendDeclinedSubmissionEmail');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    protected function executeActions()
    {
        try {
            $this->sendDeclinedSubmissions();
        } catch (\Throwable $exception) {
            $this->addExecutionLogEntry($exception->getMessage(), SCHEDULED_TASK_MESSAGE_TYPE_ERROR);

            return false;
        }

        return true;
    }

    private function sendEmail($submission, $data)
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_APP_COMMON);

        $this->addExecutionLogEntry("construindo email", SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        $email = new SubmissionMailTemplate($submission, "EDITOR_DECISION_DECLINE", null, null, null, false);
        $unserializedData = unserialize($data);
        $email->setAllData($unserializedData);

        $authors = $submission->getAuthors(true);
        foreach ($authors as $author) {
            $email->addRecipient($author->getEmail(), $author->getFullName());
        }

        DAORegistry::getDAO('SubmissionEmailLogDAO'); // Load constants
        $email->setEventType(SUBMISSION_EMAIL_EDITOR_NOTIFY_AUTHOR);

        $email->setFrom("noreply@fiocruz.br", "Cadernos de Saúde Pública");
        $email->setReplyTo("noreply@fiocruz.br", "Cadernos de Saúde Pública");

        $this->addExecutionLogEntry("enviando email", SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        $email->send();
    }

    private function sendDeclinedSubmissions()
    {
        $result = $this->submissionDAO->retrieve(
            'SELECT 
                dse.id,
                dse.submission_id,
                dse.data,
                dse.sended,
                dse.created_at
             FROM declined_submission_email AS dse 
            WHERE dse.sended = FALSE',
        );

        $this->addExecutionLogEntry(count($result) . " emails pendentes encontrados", SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        foreach ($result as $item) {
            $this->addExecutionLogEntry("buscando submission ${$item['submission_id']}", SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

            $submission = $this->submissionDAO->getById($item['submission_id']);

            $this->sendEmail($submission, $item['data']);

            $result = $this->submissionDAO->retrieve(
                'UPDATE declined_submission_email SET sended = ?, updated_at = ? WHERE id = ?',
                [true, (new DateTimeImmutable())->format('Y-m-d H:i:s'), $item['id']]
            );
        }
    }
}
