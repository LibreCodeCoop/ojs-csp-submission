{**
 * templates/controllers/wizard/fileUpload/form/submissionArtworkFileMetadataForm.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Artwork file metadata form.
 *
 * Parameters:
 *  $submissionFile: The submission artwork file.
 *  $stageId: The workflow stage id from which the upload
 *   wizard was called.
 *  $showButtons: True iff form buttons should be presented.
 *}
{assign var=metadataFormId value="metadataForm"|uniqid}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#{$metadataFormId}').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="{$metadataFormId}" action="{url component="api.file.ManageFileApiHandler" op="saveMetadata" submissionId=$submissionFile->getData('submissionId') stageId=$stageId reviewRoundId=$reviewRoundId fileStage=$submissionFile->getFileStage() fileId=$submissionFile->getFileId() escape=false}" method="post">
	{csrf}

	{* Editable metadata *}
	{fbvFormArea id="fileMetaData"}

		{* File detail summary *}
		{fbvFormSection class="req" label="plugins.generic.CspSubmission.Metadados.Notification"}{/fbvFormSection}
		{fbvFormSection label="plugins.generic.CspSubmission.File.Title" description="plugins.generic.CspSubmission.File.Description" required=true}
			{fbvElement type="text" id="name" multilingual=true maxlength="255" required=true}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.CspSubmission.artworkFile.Title" inline=true size=$fbvStyles.size.MEDIUM required="true"}
			{fbvElement type="textarea" id="artworkCaption" height=$fbvStyles.height.SHORT value=$submissionFile->getCaption() required="true"}
		{/fbvFormSection}
		{fbvFormSection title="grid.artworkFile.credit" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="textarea" id="artworkCredit" height=$fbvStyles.height.SHORT value=$submissionFile->getCredit()}
		{/fbvFormSection}
		{fbvFormSection title="grid.artworkFile.copyrightOwner" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="textarea" id="artworkCopyrightOwner" height=$fbvStyles.height.SHORT value=$submissionFile->getCopyrightOwner()}
		{/fbvFormSection}
		{fbvFormSection title="grid.artworkFile.permissionTerms" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="textarea" id="artworkPermissionTerms" height=$fbvStyles.height.SHORT value=$submissionFile->getPermissionTerms()}
		{/fbvFormSection}
	{/fbvFormArea}

	{if $submissionFile->supportsDependentFiles()}
		{capture assign=dependentFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.dependent.DependentFilesGridHandler" op="fetchGrid" submissionId=$submissionFile->getData('submissionId') fileId=$submissionFile->getFileId() stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
		{load_url_in_div id="dependentFilesGridDiv" url=$dependentFilesGridUrl}
	{/if}

	{if $showButtons}
		{fbvElement type="hidden" id="showButtons" value=$showButtons}
		{fbvFormButtons submitText="common.save"}
	{/if}
</form>
