{**
 * templates/reviewer/review/step1.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Show the review step 1 page
 *
 *}
<script type="text/javascript">
function getUrlVars() {
    var vars = {};
    var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
        vars[key] = value;
    });
    return vars;
}

	$(function() {ldelim}
	
		// Attach the form handler.
		$('#reviewStep1Form').pkpHandler(
			'$.pkp.controllers.form.AjaxFormHandler'
		);
		
		var accept = getUrlVars()['accept'];
		var submissionId = getUrlVars()['submissionId'];
		
		if (accept == "yes"){
			$("#reviewStep1Form").submit();
		}else if (accept == "no"){
			
			$("#declineReviewForm").submit();
			//$(location).attr("href", "http://localhost/ojs/index.php/csp/reviewer/showDeclineReview/"+submissionId); 
		}
	{rdelim});
</script>

<form class="pkp_form" id="reviewStep1Form" method="post" action="{url page="reviewer" op="saveStep" path=$submission->getId() step="1" escape=false}">
{csrf}
{include file="controllers/notification/inPlaceNotification.tpl" notificationId="reviewStep1FormNotification"}

{fbvFormArea id="reviewStep1"}
	{fbvFormSection label="submission.title"}
		{$submission->getLocalizedTitle()|strip_unsafe_html}
	{/fbvFormSection}
	{fbvFormSection label=$descriptionFieldKey}
		{$submission->getLocalizedAbstract()|strip_unsafe_html}
	{/fbvFormSection}

	{fbvFormSection label="editor.submissionReview.reviewType"}
		{$reviewMethod|escape}
	{/fbvFormSection}
	
	{if !$restrictReviewerFileAccess}
	{capture assign=reviewFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.review.ReviewerReviewFilesGridHandler" op="fetchGrid" submissionId=$submission->getId() stageId=$reviewAssignment->getStageId() reviewRoundId=$reviewRoundId reviewAssignmentId=$reviewAssignment->getId() escape=false}{/capture}
	{load_url_in_div id="reviewFilesStep1" url=$reviewFilesGridUrl}
	{/if}	

	<div class="pkp_linkActions">
		{include file="linkAction/linkAction.tpl" action=$viewMetadataAction contextId="reviewStep1Form"}
	</div>
	<br /><br />
	{fbvFormSection title="reviewer.submission.reviewSchedule"}
		{fbvElement type="text" id="dateNotified" label="reviewer.submission.reviewRequestDate" value=$submission->getDateNotified()|date_format:$dateFormatShort readonly=true inline=true size=$fbvStyles.size.SMALL}
		{fbvElement type="text" id="responseDue" label="reviewer.submission.responseDueDate" value=$submission->getDateResponseDue()|date_format:$dateFormatShort readonly=true inline=true size=$fbvStyles.size.SMALL}
		{fbvElement type="text" id="dateDue" label="reviewer.submission.reviewDueDate" value=$submission->getDateDue()|date_format:$dateFormatShort readonly=true inline=true size=$fbvStyles.size.SMALL}
	{/fbvFormSection}
	{if $competingInterestsAction}
		{fbvFormSection label="reviewer.submission.competingInterests" description="reviewer.submission.enterCompetingInterests"}
			<div class="pkp_linkActions">
				{include file="linkAction/linkAction.tpl" action=$competingInterestsAction contextId="reviewStep1"}
			</div>
		{/fbvFormSection}
	{/if}

	{if $competingInterestsText != null}
		{assign var="hasCI" value=true}
		{assign var="noCI" value=false}
	{else}
		{assign var="hasCI" value=false}
		{assign var="noCI" value=true}
	{/if}
	{if $hasCI || $currentContext->getSetting('reviewerCompetingInterestsRequired')}
		{fbvFormSection list=true}
			{fbvElement type="radio" value="noCompetingInterests" id="noCompetingInterests" name="competingInterestOption" checked=$noCI label="reviewer.submission.noCompetingInterests" disabled=$reviewIsComplete}
			<br /><br />
			{fbvElement type="radio" value="hasCompetingInterests" id="hasCompetingInterests" name="competingInterestOption" checked=$hasCI label="reviewer.submission.hasCompetingInterests" disabled=$reviewIsComplete}
		{/fbvFormSection}

		{fbvFormSection}
			{fbvElement type="textarea" name="competingInterestsText" id="competingInterestsText" value=$competingInterestsText size=$fbvStyles.size.MEDIUM disabled=$reviewIsComplete rich=true}
		{/fbvFormSection}
	{/if}

	{if !$reviewAssignment->getDateConfirmed() && $currentContext->getSetting('privacyStatement')}
		{fbvFormSection list=true}
			{capture assign="privacyUrl"}{url router=$smarty.const.ROUTE_PAGE page="about" op="privacy"}{/capture}
			{capture assign="privacyLabel"}{translate key="user.register.form.privacyConsent" privacyUrl=$privacyUrl}{/capture}
			{fbvElement type="checkbox" id="privacyConsent" required=true value=1 label=$privacyLabel translate=false checked=true}
		{/fbvFormSection}
	{/if}

	{if $reviewAssignment->getDateConfirmed()}
		{fbvFormButtons hideCancel=true submitText="common.saveAndContinue" submitDisabled=$reviewIsComplete}
	{elseif !$reviewAssignment->getDateConfirmed()}
		{fbvFormButtons submitText="reviewer.submission.acceptReview" cancelText="reviewer.submission.declineReview" cancelAction=$declineReviewAction submitDisabled=$reviewIsComplete}
	{/if}
{/fbvFormArea}
</form>
