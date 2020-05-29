
<script type="text/javascript">
	$(function() {ldelim}
		// Handle moving the reviewer ID from the grid to the second form
		$('#advancedReviewerSearch').pkpHandler('$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler');
	{rdelim});
</script>

<div style="text-align:right">
	<a class="pkp_button "  target="_blank" href="http://www.kfinder.com/member-search/login.cgi?medweb=1&data=qhjnK2a9jJgT28s2GQY8YGwvX8XUOvW8W6pvj85npuq8hq&searchstring={$title}&dbproduct=MEDLINE&searchlogic=fuzzy&getcount=200&relevance=50&segments=4&getchunk=20&concept_mapping=on&wordvars=on&relevance_sort=on">Consultar Kfinder</a>
</div>

<div id="advancedReviewerSearch" class="pkp_form pkp_form_advancedReviewerSearch">

	<div id="searchGridAndButton">

		{assign var="uuid" value=""|uniqid|escape}
		<div id="select-reviewer-{$uuid}">
			<select-reviewer-list-panel
				v-bind="components.selectReviewer"
				@set="set"
			/>
		</div>
		<script type="text/javascript">
			pkp.registry.init('select-reviewer-{$uuid}', 'Container', {$selectReviewerListData|@json_encode});
		</script>

		{** This button will get the reviewer selected in the grid and insert their ID into the form below **}
		{fbvFormSection class="form_buttons"}
			{fbvElement type="button" id="selectReviewerButton" label="editor.submission.selectReviewer"}
			{foreach from=$reviewerActions item=action}
				{if $action->getId() == 'advancedSearch'}
					{continue}
				{/if}
				{include file="linkAction/linkAction.tpl" action=$action contextId="createReviewerForm"}
			{/foreach}
		{/fbvFormSection}
	</div>

	<div id="regularReviewerForm" class="pkp_reviewer_form">
		{** Display the name of the selected reviewer **}
		<div class="selected_reviewer">
			<div class="label">
				{translate key="editor.submission.selectedReviewer"}
			</div>
			<div class="value">
				<span id="selectedReviewerName" class="name"></span>
				<span class="actions">
					{foreach from=$reviewerActions item=action}
						{if $action->getId() == 'advancedSearch'}
							{include file="linkAction/linkAction.tpl" action=$action contextId="createReviewerForm"}
						{/if}
					{/foreach}
				</span>
			</div>
		</div>

		{include file="../plugins/generic/cspSubmission/templates/advancedSearchReviewerAssignmentForm.tpl"}
	</div>
</div>
