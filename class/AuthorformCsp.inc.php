<?php
import('plugins.generic.cspSubmission.class.AbstractPlugin');

class AuthorformCsp extends AbstractPlugin
{
	public function initData($args)
	{
		$locale = AppLocale::getLocale();
		$request = \Application::get()->getRequest();
		$type = $request->getUserVar('type');
		$form = $args[0];
		if ($type == 'ojs') {
			$userId = $request->getUserVar('userId');
			$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
			$user = $userDao->getById($userId);
			$form->setData('givenName', [$locale => $user->getlocalizedGivenName()]);
			$form->setData('familyName', [$locale => $user->getlocalizedfamilyName()]);
			$form->setData('affiliation', [$locale =>  $user->getlocalizedAffiliation()]);
			$form->setData('email',  $user->getEmail());
			$form->setData('orcid',  $user->getOrcid());
			$form->setData('country',  $user->getCountry());
		}elseif ($type == 'csp') {
			$campo = "id".$request->getUserVar('tabela');
			$tabela = "csp.".$request->getUserVar('tabela');
			$userDao = DAORegistry::getDAO('UserDAO');
			$userCsp = $userDao->retrieve(
				<<<QUERY
					SELECT
						SUBSTRING_INDEX(SUBSTRING_INDEX(nome, ' ', 1), ' ', -1) as given_name,
						TRIM( SUBSTR(nome, LOCATE(' ', nome)) ) family_name,
						email,
						orcid,
						CASE WHEN ISNULL(instituicao1) THEN instituicao2 ELSE instituicao1 END affiliation
					FROM
						$tabela
					WHERE
						$campo = ?
				QUERY,
				[(int) $request->getUserVar('userId')]
			)->current();
			$form->setData('givenName', [$locale => $userCsp->given_name]);
			$form->setData('familyName', [$locale => $userCsp->family_name]);
			$form->setData('affiliation', [$locale => $userCsp->affiliation]);
			$form->setData('email', $userCsp->email);
			$form->setData('orcid', $userCsp->orcid);
		}elseif($form->getAuthor() != null){
			$form->setTemplate($this->plugin->getTemplateResource('authorFormAdd.tpl'));
			$author = $form->_author;
			$form->setData('authorContribution', $author->getData('authorContribution'));
			$form->setData('affiliation2', $author->getData('affiliation2'));
		}else{
			return;
		}
		$args[0] = $form;
	}

	function readUserVars($params) {
		$userVars =& $params[1];
		$userVars[] = 'authorContribution';
		$userVars[] = 'affiliation2';
		return false;
	}

	function execute($params) {
		$form =& $params[0];
		$author = $form->_author;
		$author->setData('authorContribution', $form->getData('authorContribution'));
		$author->setData('affiliation2', $form->getData('affiliation2'));
		return false;
	}
}
