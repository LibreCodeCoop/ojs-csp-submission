{if $consideracoesEticas == 1}
	{$consideracoesEticasSim="checked"}
{/if}
{if $consideracoesEticas == 0}
	{$consideracoesEticasNao="checked"}
{/if}
{fbvFormSection for="consideracoesEticas" title="plugins.generic.CspSubmission.submission.consideracoesEticas" list="true" required=true}
	{fbvElement type="radio" name="consideracoesEticas" id="consideracoesEticasSim" value="1" checked=$consideracoesEticasSim label="plugins.generic.CspSubmission.submission.consideracoesEticas.checkbox.sim"}
	{fbvElement type="radio" name="consideracoesEticas" id="consideracoesEticasNao" value="0" checked=$consideracoesEticasNao label="plugins.generic.CspSubmission.submission.consideracoesEticas.checkbox.nao"}
{/fbvFormSection}