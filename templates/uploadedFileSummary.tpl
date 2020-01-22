{**
 * templates/controllers/wizard/fileUpload/form/uploadedFileSummary.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Summary of the file name, type, size and dimensions.
 *
 * @uses $submissionFile SubmissionFile|SubmissionArtworkFile|SupplementaryFile The file.
 *}
<div class="pkp_uploadedFile_summary">

	<div class="filename" data-pkp-editable="true">
		{* <div class="display" data-pkp-editable-view="display">
			<span data-pkp-editable-displays="name">
				{$submissionFile->getLocalizedName()|escape}
			</span>
			<a href="#" class="pkpEditableToggle edit">{translate key="common.edit"}</a>
		</div> *}
		{* <div class="input" data-pkp-editable-view="input"> *}
			{fbvFormSection label="plugins.generic.CspSubmission.File.Title" description="plugins.generic.CspSubmission.File.Description" required=true}
				{fbvElement type="text" id="name" multilingual=true maxlength="255" required=true}
			{/fbvFormSection}	

		{* </div> *}
	</div>
</div>
