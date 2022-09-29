{**
 * templates/dashboard/index.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Dashboard index.
 *}
 

{extends file="layouts/backend.tpl"}

{assign var="uuid" value=""|uniqid|escape}

{block name="page"}
<div id="dashboard-{$uuid}">
	{if $substage}
		<tabs :track-history="true">
			<tab id="myQueue" label="{translate key="dashboard.myQueue"}" :badge="components.{$smarty.const.SUBMISSIONS_LIST_MY_QUEUE}.itemsMax">
				{help file="submissions" class="pkp_help_tab"}
				<submissions-list-panel v-bind="components.{$smarty.const.SUBMISSIONS_LIST_MY_QUEUE}"@set="set"/>
			</tab>
		</tabs>
	{else}
		<tabs :track-history="true">
			<tab label="{translate key="dashboard.myQueue"}">
			<div class="pkp_form">
				<fieldset id="userFormCompactLeft">
					<div class="section ">
					<label> Papel</label>
						<div class="inline pkp_helpers_half">
							<div class="pkpFormField__control">
								<select name="requestRoleAbbrev" class="pkpFormField__input pkpFormField--select__input"
								onchange="document.location.href='?requestRoleAbbrev='+this.value">
									<option value="" selected="selected">Escolha um papel</option>
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
				{foreach from=$stages key=stage item=array_substages}
					<fieldset class="pkpFormField pkpFormField--options">
						<legend class="pkpFormField--options__legend">
							{$stage}
						</legend>
						{$array_intersect = array_intersect_key($array_sort, $array_substages)}
						{$array_merge = array_merge($array_intersect, $array_substages)}
						{foreach from=$array_merge key=substage_key item=substages_status}
							{foreach from=$substages_status key=status item=substage}
								<div class="pkpFormField__control">
									<label class="pkpFormField--options__option">
											<a href="?substage={$substage_key}&status={$status}">{$substage}</a>
									</label>
								</div>
							{/foreach}
						{/foreach}
					</fieldset>
				{/foreach}
			</tab>
		</tabs>
	{/if}
</div>
{/block}
