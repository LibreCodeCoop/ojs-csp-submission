 {if !$hasParticipants}
		{translate key="submission.query.noParticipantOptions"}
 {else}

<script type="text/javascript">

	// Attach the handler.
	$(function() {ldelim}
		$('#queryForm').pkpHandler(
			'$.pkp.controllers.form.CancelActionAjaxFormHandler',
			{ldelim}
				cancelUrl: {if $isNew}'{url|escape:javascript op="deleteQuery" queryId=$queryId csrfToken=$csrfToken params=$actionArgs escape=false}'{else}null{/if}
			{rdelim}
		);
	{rdelim});


	$(function() {ldelim}
		// Attach the form handler.
		$('#addParticipantForm').pkpHandler('$.pkp.controllers.grid.users.stageParticipant.form.StageParticipantNotifyHandler',
			{ldelim}
				//possibleRecommendOnlyUserGroupIds: {$possibleRecommendOnlyUserGroupIds|@json_encode},
				recommendOnlyUserGroupIds: {$recommendOnlyUserGroupIds|@json_encode},
				blindReviewerIds: {$blindReviewerIds|@json_encode},
				blindReviewerWarning: {$blindReviewerWarning|@json_encode},
				blindReviewerWarningOk: {$blindReviewerWarningOk|@json_encode},
				templateUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT component='grid.users.stageParticipant.StageParticipantGridHandler' op='fetchTemplateBody' stageId=$stageId submissionId=$submissionId escape=false}
			{rdelim}
		);
	{rdelim});


	$('#subject').on('change', function() {
		
		var subject = this.value;		
		var message = {$message};

		tinyMCE.get($('textarea[id^="comment"]').attr('id')).setContent(message[subject])
	});

</script>

	<form class="pkp_form" id="queryForm" method="post" action="{url op="updateQuery" queryId=$queryId params=$actionArgs}">
		{csrf}

		{include file="controllers/notification/inPlaceNotification.tpl" notificationId="queryFormNotification"}

		{if $queryParticipantsListData}
			{fbvFormSection}
				{assign var="uuid" value=""|uniqid|escape}
				<div id="queryParticipants-{$uuid}">
					<list-panel
						v-bind="components.queryParticipants"
						@set="set"
					/>
				</div>
				<script type="text/javascript">
					pkp.registry.init('queryParticipants-{$uuid}', 'Container', {$queryParticipantsListData|json_encode});
				</script>
			{/fbvFormSection}
		{/if}

		{fbvFormArea id="queryContentsArea"}
			{fbvFormSection title="common.subject" for="subject" required="true"}
				{fbvElement type="select" id="subject" from=$templates translate=false}
			{/fbvFormSection}

			{fbvFormSection title="stageParticipants.notify.message" for="comment" required="true"}
				{fbvElement type="textarea" id="comment" rich=true value=$comment required="true"}
			{/fbvFormSection}
		{/fbvFormArea}

		{fbvFormArea id="queryNoteFilesArea"}
			{capture assign=queryNoteFilesGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.query.QueryNoteFilesGridHandler" op="fetchGrid" params=$actionArgs queryId=$queryId noteId=$noteId escape=false}{/capture}
			{load_url_in_div id="queryNoteFilesGrid" url=$queryNoteFilesGridUrl}
		{/fbvFormArea}

		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

		{fbvFormButtons id="addQueryButton"}

	</form>
{/if}
