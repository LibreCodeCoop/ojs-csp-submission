{**
 * templates/dashboard/index.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Dashboard index.
 *}
 

{include file="common/header.tpl" pageTitle=""}

{assign var="uuid" value=""|uniqid|escape}

<div id="dashboard-{$uuid}">
	{if $substage or !$hasAccess or $requestRoleAbbrev == "AU"}
		<tabs>
			<tab id="myQueue" label="{translate key="dashboard.myQueue"}" :badge="components.{$smarty.const.SUBMISSIONS_LIST_MY_QUEUE}.itemsMax">
				{help file="submissions" class="pkp_help_tab"}
				<submissions-list-panel v-bind="components.{$smarty.const.SUBMISSIONS_LIST_MY_QUEUE}"@set="set"/>
			</tab>
			{if array_intersect(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER), (array)$userRoles)}
				<tab id="active" label="{translate key="common.queue.long.active"}" :badge="components.{$smarty.const.SUBMISSIONS_LIST_ACTIVE}.itemsMax">
					{help file="submissions" section="active" class="pkp_help_tab"}
					<submissions-list-panel v-bind="components.{$smarty.const.SUBMISSIONS_LIST_ACTIVE}" @set="set"/>
				</tab>
			{/if}
			<tab id="archive" label="{translate key="navigation.archives"}" :badge="components.{$smarty.const.SUBMISSIONS_LIST_ARCHIVE}.itemsMax">
				{help file="submissions" section="archives" class="pkp_help_tab"}
				<submissions-list-panel v-bind="components.{$smarty.const.SUBMISSIONS_LIST_ARCHIVE}" @set="set"/>
			</tab>
		</tabs>
	{else}
		<tabs>
			<tab label="{translate key="dashboard.myQueue"}">
			<div class="pkp_form">
				<fieldset id="userFormCompactLeft">
					<div class="section ">
					<label> Papel</label>
						<div class="inline pkp_helpers_half">
							<div class="pkpFormField__control">
								<select name="requestRoleAbbrev" class="pkpFormField__input pkpFormField--select__input"
								onchange="document.location.href='?requestRoleAbbrev='+this.value">
									{foreach from=$userGroupsAbbrev key=key item=name }
										<option value="{$name}" {if $requestRoleAbbrev == $name} selected="selected"{/if}>{$name}</option>
									{/foreach}
								</select>
							</div>
						</div>
						<div class="inline pkp_helpers_half" style="text-align:right">
							<a href="submission/wizard" class="pkpButton">{translate key="plugins.generic.CspSubmission.newSubmission"}</a>
						</div>
					</div>
			</fieldset>
			</div>
				{foreach from=$stages key=stage item=array_status}
					<fieldset class="pkpFormField pkpFormField--options">
						<legend class="pkpFormField--options__legend">
							{$stage}
						</legend>
						{foreach from=$array_status key=key item=list_status}
						<div class="pkpFormField__control">
							<label class="pkpFormField--options__option">
									<a href="?substage={$key}">{$list_status}</a>
							</label>
						</div>
						{/foreach}
					</fieldset>
				{/foreach}
			</tab>
		</tabs>
	{/if}
</div>
<script type="text/javascript">
	pkp.registry.init('dashboard-{$uuid}', 'Container', {$containerData|json_encode});
</script>

{include file="common/footer.tpl"}
