{**
 * templates/submission/submissionMetadataFormTitleFields.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Submission's metadata form title fields. To be included in any form that wants to handle
 * submission metadata.
 *}

{fbvFormSection for="title" label="plugins.generic.CspSubmission.ShortTitle" description="plugins.generic.CspSubmission.ShortTitle.Description" required=true}
	{fbvElement type="text" multilingual=true name="title" id="title" value=$title readonly=$readOnly maxlength="70" required=true}
{/fbvFormSection}

{fbvFormSection label="plugins.generic.CspSubmission.Title" for="subtitle"}
	{fbvElement type="text" multilingual=true name="subtitle" id="subtitle" value=$subtitle readonly=$readOnly}
{/fbvFormSection}

{fbvFormSection label="plugins.generic.CspSubmission.DOI" for="DOI"}
	{fbvElement type="text" name="DOI" id="DOI" value=$DOI readonly=$readOnly}
{/fbvFormSection}


{fbvFormSection title="common.abstract" for="abstract" required=$abstractsRequired}
	{if $wordCount}
		<p class="pkp_help">{translate key="submission.abstract.wordCount.description" wordCount=$wordCount}
	{/if}
	{fbvElement type="textarea" multilingual=true name="abstract" id="abstract" value=$abstract rich="extended" readonly=$readOnly wordCount=$wordCount}
{/fbvFormSection}
