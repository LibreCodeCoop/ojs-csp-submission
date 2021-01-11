
<script>
    $(function() {ldelim}
			$( ".file_extension" ).each(function( index, element ) {
				var className = $(this).attr('class');
				if($("#initiateReview").length) { // Ao enviar para avaliação
					var index = className.indexOf( "pdf" );
					var text = $( this ).text();
					var substring = text.substring(0, text.indexOf("-"));

					if(index == -1){
						$('#select-'+substring).removeAttr('checked')
					}
				}
				if($("#promote input[name=decision]").val() && $("#promote input[name=decision]").val() == 7){ // Ao enviar para editoração
					var index = className.indexOf( "jpeg" );
					var text = $( this ).text();
					var substring = text.substring(0, text.indexOf("-"));

					if(index != -1){
						$('#select-'+substring).prop('checked', true);
					}

				}
				$(this).parent().css('display', 'none');
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
