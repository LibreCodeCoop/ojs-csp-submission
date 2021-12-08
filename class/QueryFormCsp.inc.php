<?php
import('plugins.generic.cspSubmission.class.AbstractPlugin');

class QueryFormCsp extends AbstractPlugin {

	public function readUservars($args){
		$request = \Application::get()->getRequest();
		$submissionDAO = Application::getSubmissionDAO();
		$submission = $submissionDAO->getById($request->getUserVar('submissionId'));
		$publication = $submission->getCurrentPublication();
		$authorDao = DAORegistry::getDAO('AuthorDAO');	
		$userDao = DAORegistry::getDAO('UserDAO');
		$author = $userDao->getUserByEmail($authorDao->getById($publication->getData('primaryContactId'))->getData('email'));

		// Submissão rejeitada e autor envia mensagem
		if($submission->_data["status"] == 4 && in_array($author->_data["id"], $request->_requestVars["users"])){
			$userDao->retrieve(
				'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
				array((string)'fin_consulta_editor_chefe', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$request->getUserVar('submissionId'))
			);
		}
		// Submissão na pré-avaliação e há troca de mensagens com o autor
		if(in_array($author->_data["id"], $request->_requestVars["users"]) && $request->getUserVar('stageId') == 1 && $submission->_data["status"] <> 4){
			$userDao->retrieve(
				'UPDATE csp_status SET status = ?, date_status = ? WHERE submission_id = ?',
				array((string)'pre_pendencia_tecnica', (string)(new DateTimeImmutable())->format('Y-m-d H:i:s'), (int)$request->getUserVar('submissionId'))
			);
		}
	}
}