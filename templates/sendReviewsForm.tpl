{**
 * templates/controllers/modals/editorDecision/form/sendReviewsForm.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Form used to send reviews to author
 *
 * @uses $revisionsEmail string Email body for requesting revisions that don't
 *  require another round of review.
 * @uses $resubmitEmail string Email body for asking the author to resubmit for
 *  another round of review.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		$('#sendReviews').pkpHandler(
			'$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler',
			{ldelim}
				{if $revisionsEmail}
					revisionsEmail: {$revisionsEmail|json_encode},
				{/if}
				{if $resubmitEmail}
					resubmitEmail: {$resubmitEmail|json_encode},
				{/if}
				peerReviewUrl: {$peerReviewUrl|json_encode}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="sendReviews" method="post" action="{url op=$saveFormOperation}" >
	{csrf}
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="stageId" value="{$stageId|escape}" />
	<input type="hidden" name="reviewRoundId" value="{$reviewRoundId|escape}" />
		<input type="hidden" name="decision" value="{$decision|escape}" />
		<input type="hidden" name="skipEmail" value="0" />
	
	<div id="sendReviews-emailContent">
		{* Message to author textarea *}
		{fbvFormSection for="personalMessage"}
			{fbvElement type="textarea" name="personalMessage" id="personalMessage" value=$personalMessage rich=true variables=$allowedVariables variablesType=$allowedVariablesType}
		{/fbvFormSection}

		{* Button to add reviews to the email automatically
		{if $reviewsAvailable}
			{fbvFormSection}
				<a id="importPeerReviews" href="#" class="pkp_button">
					<span class="fa fa-plus" aria-hidden="true"></span>
					{translate key="submission.comments.addReviews"}
				</a>
			{/fbvFormSection}
		{/if}
		*}
	</div>

	{** Some decisions can be made before review is initiated (i.e. no attachments). **}
	{if $reviewRoundId}
		<div id="attachments" style="margin-top: 30px;">
			{capture assign=reviewAttachmentsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.attachment.EditorSelectableReviewAttachmentsGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
			{load_url_in_div id="reviewAttachmentsGridContainer" url=$reviewAttachmentsGridUrl}
		</div>
	{/if}

	{fbvFormButtons submitText="editor.submissionReview.recordDecision"}
</form>
