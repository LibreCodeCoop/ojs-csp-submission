<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');
import('lib.pkp.classes.mail.MailTemplate');

class NotifyEditorInactivity extends ScheduledTask
{
    const EDITOR_CHEFE_USER_GROUP = 3;
    const EDITOR_ASSOCIADO_USER_GROUP = 5;

    const STATUS_AVA_EDITOR_ASSOCIADO = 'ava_com_editor_associado';

    const PRIMEIRO_AVISO_ASSOCIADOO_EMAIL_KEY = 'AVISO_INATIVIDADE_EDITOR_ASSOCIADO_PRIMEIRO';
    const SEGUNDO_AVISO_ASSOCIADO_EMAIL_KEY = 'AVISO_INATIVIDADE_EDITOR_ASSOCIADO_SEGUNDO';
    const AVISO_EDITOR_CHEFE_MAIL_KEY = 'AVISO_INATIVIDADE_EDITOR_CHEFE';

    const NOTIFICATION_STATUS_CSP_PRIMEIRO_AVISO = 1;
    const NOTIFICATION_STATUS_CSP_SEGUNDO_AVISO = 2;

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
        return __('plugins.generic.cspSubmission.NotifyEditorInactivity');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    protected function executeActions()
    {
        $journals = $this->journalDao->getAll(true);

        while ($journal = $journals->next()) {
            $this->sendJournalReminders($journal);
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

        $this->userDao->retrieve(
            'UPDATE status_csp SET notification = ? WHERE submission_id = ?',
            [
                self::PRIMEIRO_AVISO_ASSOCIADOO_EMAIL_KEY === $mailKey ?
                    self::NOTIFICATION_STATUS_CSP_PRIMEIRO_AVISO :
                    self::NOTIFICATION_STATUS_CSP_SEGUNDO_AVISO,
                $submissionId,
            ]
        );
    }

    private function sendReminderToJournalEditor($journal, $editors, $notifiedUsers)
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_APP_COMMON);

        $mail = new MailTemplate(
            self::AVISO_EDITOR_CHEFE_MAIL_KEY,
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
            'SELECT sc.submission_id, sc.date_status, sa.user_id FROM status_csp AS sc 
            INNER JOIN stage_assignments AS sa
            ON sa.submission_id = sc.submission_id 
            AND sa.user_group_id = ?
            WHERE sc.status = ? 
            AND sc.date_status <= ?
            AND sc.notification != ?',
            [
                self::EDITOR_ASSOCIADO_USER_GROUP,
                self::STATUS_AVA_EDITOR_ASSOCIADO,
                $lastWeek->format('Y-m-d H:i:s'),
                self::NOTIFICATION_STATUS_CSP_SEGUNDO_AVISO,
            ]
        );

        $notifiedUsers = [];
        foreach ($result as $item) {
            $userId = $item['user_id'];

            $user = $this->userDao->getById($userId, false);
            if (!$user instanceof User) {
                continue;
            }

            if ($item['date_status'] > $twoWeeksAgo->format('Y-m-d H:i:s')) {
                $this->sendReminderToAssociateEditor(
                    $journal,
                    self::PRIMEIRO_AVISO_ASSOCIADOO_EMAIL_KEY,
                    $user,
                    $item['submission_id']
                );

                continue;
            }

            $this->sendReminderToAssociateEditor(
                $journal,
                self::SEGUNDO_AVISO_ASSOCIADO_EMAIL_KEY,
                $user,
                $item['submission_id']
            );

            if (!in_array($userId, array_keys($notifiedUsers))) {
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
                self::EDITOR_CHEFE_USER_GROUP,
            ]
        );

        $this->sendReminderToJournalEditor($journal, $editors, $notifiedUsers);
    }
}
