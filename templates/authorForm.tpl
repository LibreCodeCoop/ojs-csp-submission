<script>
	$(function() {ldelim}
		$('#editAuthor').pkpHandler(
			'$.pkp.controllers.form.AjaxFormHandler'
		);
		//$('#editAuthor .section .checkbox_and_radiobutton').css('display','none');
	{rdelim});
</script>
<form class="pkp_form search" id="editAuthor" method="post">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="authorFormNotification"}

	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="publicationId" value="{$publicationId|escape}" />
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
