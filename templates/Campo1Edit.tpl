{**
 * plugins/generic/cspSubmission/Campo1Edit.tpl
 *
 * Copyright (c) 2014-2019 LyseonTech
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Edit cspSubmission Campo1 
 *
 *}
{fbvFormArea id="cspSubmission"}
	{fbvFormSection label="plugins.generic.CspSubmission.Campo1" for="source" description="plugins.generic.CspSubmission.Campo1.description"}
		{fbvElement type="text" name="Campo1" id="Campo1" value=$Campo1 maxlength="255" readonly=$readOnly}
	{/fbvFormSection}
{/fbvFormArea}
