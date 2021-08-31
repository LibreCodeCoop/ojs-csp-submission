<?php
import('plugins.generic.cspSubmission.class.AbstractPlugin');

class UserdaoCsp extends AbstractPlugin
{

	public function getAdditionalFieldNames($args)
	{
		$args[1][] = 'gender';
		$args[1][] = 'affiliation2';
		$args[1][] = 'city';
		$args[1][] = 'state';
		$args[1][] = 'zipCode';
	}
}
