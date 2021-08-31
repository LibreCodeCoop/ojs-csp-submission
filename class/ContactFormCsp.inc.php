<?php
import('plugins.generic.cspSubmission.class.AbstractPlugin');

class ContactFormCsp extends AbstractPlugin
{
	public function display($args){
		$args[0]->_data["affiliation2"] = $args[0]->_user->_data["affiliation2"];
		$args[0]->_data["city"] = $args[0]->_user->_data["city"];
		$args[0]->_data["state"] = $args[0]->_user->_data["state"];
		$args[0]->_data["zipCode"] = $args[0]->_user->_data["zipCode"];
	}
	
	
	public function readuservars($args)
	{
		$args[1][] = 'affiliation2';
		$args[1][] = 'city';
		$args[1][] = 'state';
		$args[1][] = 'zipCode';
	}
	
	public function execute($args)
	{
		$form = &$args[0];
		
		$editUser = $form->_user;
		$editUser->setData('affiliation2', $form->getData('affiliation2'));
		$editUser->setData('city', $form->getData('city'));
		$editUser->setData('state', $form->getData('state'));
		$editUser->setData('zipCode', $form->getData('zipCode'));
	}
}
