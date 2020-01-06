<style type="text/css">
#ConflitoInteresseQual{
	display: none;
}

</style>
<script type="text/javascript">
$("#ConflitoInteresse-yes").change(function() {
  $("#ConflitoInteresseQual").css('display','block');
});

</script>

{fbvFormArea id="cspSubmission"}
	{fbvFormSection label="plugins.generic.CspSubmission.ConflitoInteresse" list=true required="1" validation="required" class="localizable  required" aria-required="true"}
		{fbvElement type="radio" id="ConflitoInteresse-yes" name="ConflitoInteresse" value="yes" checked=$ConflitoInteresse|compare:"yes" label="plugins.generic.CspSubmission.ConflitoInteresse.yes"}
		{fbvElement type="radio" id="ConflitoInteresse-no" name="ConflitoInteresse" value="no" checked=$ConflitoInteresse|compare:"no" label="plugins.generic.CspSubmission.ConflitoInteresse.no"}
	{/fbvFormSection}


	{fbvFormSection label="plugins.generic.CspSubmission.ConflitoInteresseQual" id="ConflitoInteresseQual"}
		{fbvElement type="text" name="ConflitoInteresseQual" id="ConflitoInteresseQual" value=$ConflitoInteresseQual maxlength="255"}
	{/fbvFormSection}
	
{/fbvFormArea}