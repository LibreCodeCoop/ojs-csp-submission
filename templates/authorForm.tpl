<script>
	$(function() {ldelim}
		$('#editAuthor').pkpHandler(
			'$.pkp.controllers.form.AjaxFormHandler'
		);
	{rdelim});
</script>
<form class="pkp_form" id="editAuthor" method="post">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="authorFormNotification"}

	{assign var="uuid" value=""|uniqid|escape}
	<div id="my-submission-list-handler-{$uuid}">
		<script type="text/javascript">
			pkp.registry.init('my-submission-list-handler-{$uuid}', 'CoautorListPanel', {$myQueueListData});
		</script>
	</div>

	<div class="section formButtons form_buttons ">
		<a href="#" id="cancelFormButton-{$uuid|escape}" class="cancelButton">{translate key="common.cancel"}</a>
	</div>
</form>
