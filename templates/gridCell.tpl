
 <script>
	$(function() {ldelim}
		// Attach the form handler.
		$( ".file_extension" ).each(function( index, element ) {
			
			var className = $(this).attr('class');
			var index = className.indexOf( "pdf" );			
			var text = $( this ).text();
			var substring = text.substring(0, text.indexOf("-"));				

			if(index == -1){				 
				 				 
				$('#select-'+substring).removeAttr('checked')
				
			}					
		});

	{rdelim});

</script>
{if $id}
	{assign var=cellId value="cell-"|concat:$id}
{else}
	{assign var=cellId value=""}
{/if}
<span {if $cellId}id="{$cellId|escape}" {/if}class="gridCellContainer">
	{include file="controllers/grid/gridCellContents.tpl"}
</span>

