<?php
import('plugins.generic.cspSubmission.class.AbstractPlugin');

class RegistrationFormCsp extends AbstractPlugin
{

	public function constructor($args)
	{
		$form =& $args[0];
		$form->addCheck(new FormValidator($form, 'orcid', 'required', 'user.profile.form.orcidRequired'));
	}
	

	public function readuservars($args)
	{
		$args[1][] = 'url';
		$args[1][] = 'gender';
		$args[1][] = 'phone';
		$args[1][] = 'affiliation2';
		$args[1][] = 'mailingAddress';
		$args[1][] = 'city';
		$args[1][] = 'state';
		$args[1][] = 'zipCode';
		$args[1][] = 'orcid';
	}


	public function execute($args)
	{
		$form = &$args[0];

		$newUser = $form->user;
		$newUser->setData('url', $form->getData('url'));
		$newUser->setData('gender', $form->getData('gender'));
		$newUser->setData('phone', $form->getData('phone'));
		$newUser->setData('affiliation2', $form->getData('affiliation2'));
		$newUser->setData('mailingAddress', $form->getData('mailingAddress'));
		$newUser->setData('city', $form->getData('city'));
		$newUser->setData('state', $form->getData('state'));
		$newUser->setData('zipCode', $form->getData('zipCode'));
		$newUser->setData('orcid', $form->getData('orcid'));
	}
}
