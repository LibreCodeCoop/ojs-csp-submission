<?php
import('plugins.generic.cspSubmission.class.AbstractPlugin');

class SchemaGetAuthorCsp extends AbstractPlugin
{
	public function getAuthor($arguments){
		$arguments[0]->properties->affiliation2 = new stdClass();
		$arguments[0]->properties->affiliation2->type = 'string';
		$arguments[0]->properties->affiliation2->description = "Author's second affiliation";
		$arguments[0]->properties->affiliation2->multilingual = true;
		$arguments[0]->properties->affiliation2->apiSummary =  true;

		$arguments[0]->properties->authorContribution = new stdClass();
		$arguments[0]->properties->authorContribution->type = 'string';
		$arguments[0]->properties->authorContribution->description = "Author's contribution in the publication.";
		$arguments[0]->properties->authorContribution->multilingual = true;
		$arguments[0]->properties->authorContribution->apiSummary =  true;
	}
}
