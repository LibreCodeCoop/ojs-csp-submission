<script>
	$(function() {ldelim}
		$('#editAuthor').pkpHandler(
			'$.pkp.controllers.form.AjaxFormHandler'
		);
	{rdelim});
</script>
<link rel="stylesheet" href="/ojs/plugins/generic/cspSubmission/styles/build.css?v=3.1.2.4" type="text/css" />
<script src="/ojs/plugins/generic/cspSubmission/js/build.js?v=3.1.2.4" type="text/javascript"></script>

<form class="pkp_form" id="editAuthor" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.cspSubmission.controllers.grid.AddAuthorHandler" op="searchAuthor"}">
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
