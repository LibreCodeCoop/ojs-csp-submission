<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');
import('lib.pkp.classes.mail.MailTemplate');
import('plugins.generic.cspSubmission.class.NotifyScheduleTaskConstants');

class NotifyEditorInactivity extends ScheduledTask
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
        return __('plugins.generic.cspSubmission.class.NotifyEditorInactivity');
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

    private function sendReminderToAssociateEditor($journal, string $mailKey, User $user, $submissionId)
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_APP_COMMON);

        $mail = new MailTemplate(
            $mailKey,
            $journal->getPrimaryLocale(),
            $journal, false
        );

        $mail->setReplyTo(null);
        $mail->addRecipient($user->getEmail(), $user->getFullName());
        $mail->setSubject($mail->getSubject());
        $mail->setBody($mail->getBody());
        $mail->sendWithParams(['submissionId' => $submissionId]);

        $notification = NotifyScheduleTaskConstants::PRIMEIRO_AVISO_ASSOCIADO_EMAIL_KEY === $mailKey ?
            NotifyScheduleTaskConstants::CSP_STATUS_NOTIFICATION_PRIMEIRO_AVISO :
            NotifyScheduleTaskConstants::CSP_STATUS_NOTIFICATION_SEGUNDO_AVISO;

        $this->userDao->retrieve(
            'INSERT INTO csp_status_notification 
                (user_id, status, submission_id, notification, created_at) VALUES(?,?,?,?,?)
            ON DUPLICATE KEY UPDATE notification = ?, updated_at = ?',
            [
                $user->getId(),
                NotifyScheduleTaskConstants::STATUS_AVA_EDITOR_ASSOCIADO,
                $submissionId,
                $notification,
                (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                $notification,
                (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]
        );
    }

    private function sendReminderToJournalEditor($journal, $editors, $notifiedUsers)
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_APP_COMMON);

        $mail = new MailTemplate(
            NotifyScheduleTaskConstants::AVISO_EDITOR_CHEFE_MAIL_KEY,
            $journal->getPrimaryLocale(),
            $journal,
            false
        );
        foreach ($editors as $editor) {
            $mail->addRecipient($editor['email']);
        }

        $mail->setReplyTo(null);
        $mail->setSubject($mail->getSubject());
        $mail->setBody($mail->getBody());
        $mail->sendWithParams(['editorsWithSubmissions' => implode('<br>', $notifiedUsers)]);
    }

    private function sendJournalReminders($journal)
    {
        $today = (new DateTimeImmutable())->modify('00:00');

        $lastWeek = $today->modify('- 1 week')->modify('- 1 day');
        $twoWeeksAgo = $today->modify('- 2 week')->modify('- 1 day');

        $result = $this->userDao->retrieve(
            'SELECT sc.submission_id, sc.date_status, sa.user_id, nsc.notification FROM csp_status AS sc 
            INNER JOIN stage_assignments AS sa
                ON sa.submission_id = sc.submission_id 
            AND sa.user_group_id = ?
            LEFT JOIN csp_status_notification AS nsc 
                ON nsc.submission_id = sc.submission_id 
                AND nsc.status = sc.status
                AND nsc.user_id = sa.user_id
            WHERE sc.status = ?
                AND sc.date_status <= ?
                AND (nsc.notification IS NULL OR nsc.notification != ?)',
            [
                NotifyScheduleTaskConstants::EDITOR_ASSOCIADO_USER_GROUP,
                NotifyScheduleTaskConstants::STATUS_AVA_EDITOR_ASSOCIADO,
                $lastWeek->format('Y-m-d H:i:s'),
                NotifyScheduleTaskConstants::CSP_STATUS_NOTIFICATION_SEGUNDO_AVISO,
            ]
        );

        $notifiedUsers = [];
        foreach ($result as $item) {
            $userId = $item['user_id'];

            $user = $this->userDao->getById($userId, false);
            if (!$user instanceof User) {
                continue;
            }

            if ($item['date_status'] > $twoWeeksAgo->format('Y-m-d H:i:s') &&
                NotifyScheduleTaskConstants::CSP_STATUS_NOTIFICATION_PRIMEIRO_AVISO === $item['notification']
            ) {
                continue;
            }

            if ($item['date_status'] > $twoWeeksAgo->format('Y-m-d H:i:s')) {
                $this->sendReminderToAssociateEditor(
                    $journal,
                    NotifyScheduleTaskConstants::PRIMEIRO_AVISO_ASSOCIADO_EMAIL_KEY,
                    $user,
                    $item['submission_id']
                );

                continue;
            }

            $this->sendReminderToAssociateEditor(
                $journal,
                NotifyScheduleTaskConstants::SEGUNDO_AVISO_ASSOCIADO_EMAIL_KEY,
                $user,
                $item['submission_id']
            );

            if (!in_array($userId, array_keys($notifiedUsers), false)) {
                $notifiedUsers[$userId] = strtr(
                    ':fullName (:email): :submission',
                    [
                        ':fullName' => $user->getFullName(),
                        ':email' => $user->getEmail(),
                        ':submission' => $item['submission_id'],
                    ]
                );

                continue;
            }

            $notifiedUsers[$userId] .= ', '.$item['submission_id'];
        }

        if (empty($notifiedUsers)) {
            return;
        }

        $editors = $this->userDao->retrieve(
            'SELECT u.user_id, u.email FROM users AS u 
            INNER JOIN user_user_groups AS g
            ON u.user_id = g.user_id 
            AND g.user_group_id = ?
            WHERE u.disabled = 0',
            [
                NotifyScheduleTaskConstants::EDITOR_CHEFE_USER_GROUP,
            ]
        );

        $this->sendReminderToJournalEditor($journal, $editors, $notifiedUsers);
    }
}
