<script>
	$(function() {ldelim}
		$('#editAuthor').pkpHandler(
			'$.pkp.controllers.form.AjaxFormHandler'
		);
	{rdelim});
</script>

<form class="pkp_form" id="editAuthor" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.cspSubmission.controllers.grid.AddAuthorHandler" op="searchAuthor"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="authorFormNotification"}

	{assign var="uuid" value=""|uniqid|escape}
	<div id="my-submission-list-handler-{$uuid}">
		<script type="text/javascript">
			pkp.registry.init('my-submission-list-handler-{$uuid}', 'CoautorListPanel', {$myQueueListData});
		</script>
	</div>

	{fbvFormButtons id="step2Buttons" submitText="navigation.nextStep"}
</form>
