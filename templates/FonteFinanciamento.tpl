<style type="text/css">
#FonteFinanciamentoQual{
	display: none;
}

</style>
<script type="text/javascript">
$("#FonteFinanciamento-yes").change(function() {
  $("#FonteFinanciamentoQual").show("slow");
});

$("#FonteFinanciamento-no").change(function() {
  $("#FonteFinanciamentoQual").hide("slow");
});
</script>

{fbvFormArea id="cspSubmission"}
	{fbvFormSection label="plugins.generic.CspSubmission.FonteFinanciamento" list=true required="1" validation="required" class="localizable  required"}
		{fbvElement type="radio" id="FonteFinanciamento-yes" name="FonteFinanciamento" value="yes" checked=($FonteFinanciamento|compare:"yes") label="plugins.generic.CspSubmission.FonteFinanciamento.yes"}
		{fbvElement type="radio" id="FonteFinanciamento-no" name="FonteFinanciamento" value="no" checked=($FonteFinanciamento|compare:"no")|compare:"" label="plugins.generic.CspSubmission.FonteFinanciamento.no"}
	{/fbvFormSection}


	{fbvFormSection label="plugins.generic.CspSubmission.FonteFinanciamentoQual" id="FonteFinanciamentoQual"}
		{fbvElement type="text" name="FonteFinanciamentoQual" id="FonteFinanciamentoQual" value=$FonteFinanciamentoQual maxlength="255"}
	{/fbvFormSection}
	
{/fbvFormArea}