{if $cspPublication->getData('dataAvailabilityRadios')}
    {include file="/submission/review-publication-field.tpl" prop="dataAvailabilityRadios" name="{translate key="plugins.generic.CspSubmission.dataAvailabilityRadios.label"}" type="html"}
{/if}
{if $cspPublication->getData('monografDissertTese')}
    {include file="/submission/review-publication-field.tpl" prop="monografDissertTese" name="{translate key="plugins.generic.CspSubmission.monografDissertTese.options"}" type="html"}
{/if}
<div class="submissionWizard__reviewPanel__item">
    <h4 class="submissionWizard__reviewPanel__item__header">
        {translate key="plugins.generic.CspSubmission.agradecimentos"}
    </h4>
    <div
        v-if="submission.agradecimentos"
        class="submissionWizard__reviewPanel__item__value"
        v-strip-unsafe-html="submission.agradecimentos"
    ></div>
    <div v-else class="submissionWizard__reviewPanel__item__value">
        {translate key="common.none"}
    </div>
</div>
