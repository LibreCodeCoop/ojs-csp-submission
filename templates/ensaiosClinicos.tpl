<script type="text/javascript">
	$( "#ensaiosClinicosSim" ).click(function() {
		$("#ensaiosClinicosText").css('display','block');
	});
	$( "#ensaiosClinicosNao" ).click(function() {
		$("#ensaiosClinicosText").css('display','none');
	});
</script>

{if $ensaiosClinicos == 1}
	{$ensaiosClinicosSim="checked"}
{/if}
{if $ensaiosClinicos == 0}
	{$ensaiosClinicosNao="checked"}
{/if}
{fbvFormSection for="ensaiosClinicos" title="plugins.generic.CspSubmission.submission.ensaiosClinicos" list="true" required=true}
	{fbvElement type="radio" name="ensaiosClinicos" id="ensaiosClinicosSim" value="1" checked=$ensaiosClinicosSim label="plugins.generic.CspSubmission.submission.ensaiosClinicos.checkbox.sim"}
	<div {if $ensaiosClinicosSim <> 'checked'} style='display:none' {/if} id="ensaiosClinicosText">
		{fbvElement type="text" label="plugins.generic.CspSubmission.submission.ensaiosClinicos.numRegistro" name="numRegistro" id="numRegistro" value=$numRegistro size=$fbvStyles.size.SMALL multilingual=true required=$numRegistroRequired}
		{fbvElement type="text" label="plugins.generic.CspSubmission.submission.ensaiosClinicos.orgao" name="orgao" id="orgao" value=$orgao size=$fbvStyles.size.SMALL multilingual=true required=$orgaoRequired}
	</div>
	{fbvElement type="radio" name="ensaiosClinicos" id="ensaiosClinicosNao" value="0" checked=$ensaiosClinicosNao label="plugins.generic.CspSubmission.submission.ensaiosClinicos.checkbox.nao"}
{/fbvFormSection}