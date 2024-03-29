{**
 * templates/submission/form/step3.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Step 3 of author submission.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the JS form handler.
		$('#submitStep3Form').pkpHandler(
			'$.pkp.pages.submission.SubmissionStep3FormHandler',
			{ldelim}
				chaptersGridContainer: 'chaptersGridContainer',
				authorsGridContainer: 'authorsGridContainer',
			{rdelim});
	{rdelim});
</script>

<form class="pkp_form" id="submitStep3Form" method="post" action="{url op="saveStep" path=$submitStep}">
	<h3 class="req">{translate key=$notification}</h3>
	{csrf}
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="submitStep3FormNotification"}

	<div class="pkp_notification">
		<div id="pkp_notification_upgradeWarning-616ef7591d8e7" class="notifyWarning">
		<span class="title">
			{translate key="common.warning"}
		</span>
		<p class="description">
			{translate key="submission.rights.tip"}
		</p>
		<p class="description">
			{translate key="plugins.generic.CspSubmission.submission.help"}
		</p>
		</div>
	</div>

	{include file="../plugins/generic/cspSubmission/templates/submissionMetadataFormTitleFields.tpl"}

	<!--  Contributors -->
	{capture assign=authorGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.users.author.AuthorGridHandler" op="fetchGrid" submissionId=$submissionId publicationId=$publicationId escape=false}{/capture}
	{load_url_in_div id="authorsGridContainer" url=$authorGridUrl}

	{$additionalContributorsFields}

	{include file="submission/form/categories.tpl"}

	{include file="../plugins/generic/cspSubmission/templates/submissionMetadataFormFields.tpl"}

	{fbvFormButtons id="step3Buttons" submitText="common.saveAndContinue"}
</form>
