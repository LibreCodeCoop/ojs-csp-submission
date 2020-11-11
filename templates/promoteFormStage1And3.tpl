{**
 * templates/controllers/modals/editorDecision/form/promoteForm.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Form used to send reviews to author
 *
 *}

<script type="text/javascript">
	$(function() {ldelim}
		$('#promote').pkpHandler(
			'$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler',
			{ldelim}
				peerReviewUrl: {$peerReviewUrl|json_encode}
			{rdelim}
		);
		$('#promoteForm-complete-btn').css('display','inline');
	{rdelim});
</script>

<form class="pkp_form" id="promote" method="post" action="{url op=$saveFormOperation}" >
	{csrf}
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="decision" value="{$decision|escape}" />
	<input type="hidden" name="reviewRoundId" value="{$reviewRoundId|escape}" />

	<div >
		{capture assign="stageName"}{translate key=$decisionData.toStage}{/capture}
		<p>{translate key="editor.submission.decision.selectFiles" stageName=$stageName}</p>
		{* Show a different grid depending on whether we're in review or before the review stage *}
		{if $stageId == $smarty.const.WORKFLOW_STAGE_ID_SUBMISSION}
			{capture assign=filesToPromoteGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.submission.SelectableSubmissionDetailsFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId escape=false}{/capture}
		{elseif $reviewRoundId}
			{** a set $reviewRoundId var implies we are INTERNAL_REVIEW or EXTERNAL_REVIEW **}
			{capture assign=filesToPromoteGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.SelectableReviewRevisionsGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
		{elseif $stageId == $smarty.const.WORKFLOW_STAGE_ID_EDITING}
			{capture assign=filesToPromoteGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.copyedit.SelectableCopyeditFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId escape=false}{/capture}
			{capture assign=draftFilesToPromoteGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.final.SelectableFinalDraftFilesGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId escape=false}{/capture}
			{load_url_in_div id="draftFilesToPromoteGridUrl" url=$draftFilesToPromoteGridUrl}
		{/if}
		{load_url_in_div id="filesToPromoteGrid" url=$filesToPromoteGridUrl}
	</div>

	{fbvFormSection class="formButtons form_buttons"}
		{fbvElement type="submit" class="submitFormButton pkp_button_primary" id="promoteForm-complete-btn" label="editor.submissionReview.recordDecision"}
		{assign var=cancelButtonId value="cancelFormButton"|concat:"-"|uniqid}
		<a href="#" id="{$cancelButtonId}" class="cancelButton">{translate key="common.cancel"}</a>
		<span class="pkp_spinner"></span>
	{/fbvFormSection}
</form>
