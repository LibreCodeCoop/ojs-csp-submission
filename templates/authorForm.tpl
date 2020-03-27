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
	<div id="coautor-list-panel-{$uuid}">
		<coautor-list-panel
			v-bind="components.CoautorListPanel"
			@set="set"
		/>
	</div>
	<script type="text/javascript">
		pkp.registry.init('coautor-list-panel-{$uuid}', 'Container', {$containerData|json_encode});
	</script>
	<div class="section formButtons form_buttons ">
		<a id="cancelFormButton-{$uuid|escape}" class="cancelButton">{translate key="common.cancel"}</a>
	</div>
</form>
