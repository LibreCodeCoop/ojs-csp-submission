{**
 * templates/controllers/grid/gridCellContents.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * a regular grid cell's contents
 *}

{* Preserve the value in case it's used externally *}
{assign var=_label value=$label}

{* Prepare multilingual content for display *}
{if $column->hasFlag('multilingual')}
	{* Multilingual column *}
	{if isset($_label.$currentLocale)}
		{assign var=_label value=$_label.$currentLocale}
	{else}
		{assign var=_primaryLocale value=AppLocale::getPrimaryLocale()}
		{assign var=_label value=$_label.$_primaryLocale}
	{/if}
{/if}

{* Handle escaping as needed *}
{if $column->hasFlag('anyhtml')}
	{* Any HTML is allowed; do not escape anything *}
{elseif $column->hasFlag('html')}
	{* Limited HTML is allowed *}
	{assign var=_label value=$_label|strip_unsafe_html}
{else}
	{* No HTML allowed; escape the label. *}
	{assign var=_label value=$_label|escape}
{/if}

{if $_label != ''}
	<span class="label{if count($actions) gt 0} before_actions{/if}">
		{if $column->hasFlag('maxLength')}
			{assign var="maxLength" value=$column->getFlag('maxLength')}
			{$_label|truncate:$maxLength}
		{else}		
		{*Se existir comentário, exibe comentário, se não, exibe o tipo de arquivo*}
			{if substr($cellId, -4) == "type"}
				{if $comentario}
					{$comentario}
				{else}
					{$_label}
				{/if}
			{else}
				{$_label}
			{/if}
		{/if}
	</span>
{/if}



{if count($actions) gt 0}
	{foreach from=$actions item=action}
		{include file="linkAction/linkAction.tpl" action=$action contextId=$cellId anyhtml=$column->hasFlag('anyhtml')}
	{/foreach}
{/if}
