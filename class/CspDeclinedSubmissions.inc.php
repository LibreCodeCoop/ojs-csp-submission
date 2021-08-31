<?php

class CspDeclinedSubmissions
{
    /** @var SubmissionDAO */
    private $submissionDAO;

    public function __construct()
    {
        $this->submissionDAO = DAORegistry::getDAO('SubmissionDAO');
    }

    public function saveDeclinedSubmission(int $submissionId, SubmissionMailTemplate $args)
    {
        $serializedData = serialize($args->getAllData());
        $this->submissionDAO->retrieve(
            'INSERT INTO csp_declined_submission_email
                (submission_id, data, sended, created_at)
                VALUES(?, ?, ?, ?)',
            [
                $submissionId,
                $serializedData,
                false,
                (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );
    }
}
