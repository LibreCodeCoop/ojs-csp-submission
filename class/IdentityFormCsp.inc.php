<?php
import('plugins.generic.cspSubmission.class.AbstractPlugin');

class IdentityFormCsp extends AbstractPlugin
{
	public function display($args)
	{
		$args[0]->_data["gender"] = $args[0]->_user->_data["gender"];

		$args[0]->_data["genders"] = array('F' => 'Feminino', 'M' => 'Masculino');
	}


	public function readuservars($args)
	{
		$args[1][] = 'gender';
	}

	public function execute($args)
	{
		$form = &$args[0];

		$editUser = $form->_user;
		$editUser->setData('gender', $form->getData('gender'));
	}
}
