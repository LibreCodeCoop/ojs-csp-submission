
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
					const figuras = ["jpeg", "png", "tiff", "bmp"];

					var index = className.indexOf( "jpeg" );
					var text = $( this ).text();
					var substring = text.substring(0, text.indexOf("-"));

					if (figuras.some(v => className.includes(v))) {
						$('#select-'+substring).prop('checked', true);
					}

				}
				$(this).parent().css('display', 'none');
			});

			$('.pkp_linkaction_thankReviewer').css('display', 'none');// Remove o link para agradecer ao avaliador
    {rdelim});
</script>
{if $id}
	{assign var=cellId value="cell-"|concat:$id}
{else}
	{assign var=cellId value=""}
{/if}
<span {if $cellId}id="{$cellId|escape}" {/if}class="gridCellContainer">
	{include file="../plugins/generic/cspSubmission/templates/gridCellContents.tpl"}
</span>
