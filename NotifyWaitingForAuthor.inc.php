<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');
import('lib.pkp.classes.mail.MailTemplate');
import('plugins.generic.cspSubmission.NotifyScheduleTaskConstants');

class NotifyWaitingForAuthor extends ScheduledTask
{
    /** @var JournalDAO */
    private $journalDao;
    /** @var UserDAO */
    private $userDao;

    public function __construct($args)
    {
        parent::__construct($args);

        $this->journalDao = DAORegistry::getDAO('JournalDAO');
        $this->userDao = DAORegistry::getDAO('UserDAO');
    }

    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName()
    {
        return __('plugins.generic.cspSubmission.NotifyWaitingForAuthor');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    protected function executeActions()
    {
        try {
            $journals = $this->journalDao->getAll(true);

            while ($journal = $journals->next()) {
                $this->sendJournalReminders($journal);
            }
        } catch (\Throwable $exception) {
            $this->addExecutionLogEntry($exception->getMessage(), SCHEDULED_TASK_MESSAGE_TYPE_ERROR);

            return false;
        }

        return true;
    }

    private function sendReminderToAuthor($journal, User $user, $submissionId, $notification)
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_APP_COMMON);

        $mail = new MailTemplate(
            NotifyScheduleTaskConstants::AVISO_AUTOR_EMAIL_KEY,
            $journal->getPrimaryLocale(),
            $journal,
            false
        );

        $mail->setReplyTo(null);
        $mail->addRecipient($user->getEmail(), $user->getFullName());
        $mail->setSubject($mail->getSubject());
        $mail->setBody($mail->getBody());
        $mail->sendWithParams(['submissionId' => $submissionId]);

        $this->userDao->retrieve(
            'INSERT INTO notification_status_csp 
                (user_id, status, submission_id, notification, created_at) VALUES(?,?,?,?,?)
            ON DUPLICATE KEY UPDATE notification = ?, updated_at = ?',
            [
                $user->getId(),
                NotifyScheduleTaskConstants::STATUS_AVA_AGUARDANDO_AUTOR,
                $submissionId,
                $notification,
                (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                $notification,
                (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]
        );
    }

    private function sendJournalReminders($journal)
    {
        $today = (new DateTimeImmutable())->modify('00:00');

        $aMonthAgo = $today->modify('- 1 month')->modify('- 1 day');
        $aMonthAndAWeekAgo = $aMonthAgo->modify('- 1 week');

        $result = $this->userDao->retrieve(
            'SELECT sc.submission_id, sc.date_status, sa.user_id, nsc.notification FROM status_csp AS sc 
            INNER JOIN stage_assignments AS sa
                ON sa.submission_id = sc.submission_id 
            AND sa.user_group_id = ?
            LEFT JOIN notification_status_csp AS nsc 
                ON nsc.submission_id = sc.submission_id 
                AND nsc.status = sc.status
                AND nsc.user_id = sa.user_id
            WHERE sc.status = ?
                AND sc.date_status <= ?
                AND (nsc.notification IS NULL OR nsc.notification != ?)',
            [
                NotifyScheduleTaskConstants::AUTOR_USER_GROUP,
                NotifyScheduleTaskConstants::STATUS_AVA_AGUARDANDO_AUTOR,
                $aMonthAgo->format('Y-m-d H:i:s'),
                NotifyScheduleTaskConstants::NOTIFICATION_STATUS_CSP_SEGUNDO_AVISO,
            ]
        );

        foreach ($result as $item) {
            $userId = $item['user_id'];

            $user = $this->userDao->getById($userId, false);
            if (!$user instanceof User) {
                continue;
            }

            if ($item['date_status'] > $aMonthAndAWeekAgo->format('Y-m-d H:i:s')
                && NotifyScheduleTaskConstants::NOTIFICATION_STATUS_CSP_PRIMEIRO_AVISO === $item['notification']
            ) {
                continue;
            }

            if ($item['date_status'] > $aMonthAndAWeekAgo->format('Y-m-d H:i:s')) {
                $this->sendReminderToAuthor(
                    $journal,
                    $user,
                    $item['submission_id'],
                    NotifyScheduleTaskConstants::NOTIFICATION_STATUS_CSP_PRIMEIRO_AVISO,
                );

                continue;
            }

            $this->sendReminderToAuthor(
                $journal,
                $user,
                $item['submission_id'],
                NotifyScheduleTaskConstants::NOTIFICATION_STATUS_CSP_SEGUNDO_AVISO,
            );
        }
    }
}
