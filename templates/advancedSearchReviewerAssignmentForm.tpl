
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler for second form.
		$('#advancedSearchReviewerForm').pkpHandler('$.pkp.controllers.grid.users.reviewer.form.AddReviewerFormHandler');
	{rdelim});
</script>

{* The form that will create the review assignment.  A reviewer ID must be loaded in here via the grid above. *}
<form class="pkp_form" id="advancedSearchReviewerForm" method="post" action="{url op="updateReviewer"}" >
	{csrf}
	{fbvElement type="hidden" id="reviewerId" value=$reviewerId}

	{include file="../plugins/generic/cspSubmission/templates/reviewerFormFooter.tpl"}

	{fbvFormButtons submitText="editor.submission.addReviewer"}
</form>
