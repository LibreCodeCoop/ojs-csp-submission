{**
 * templates/submission/form/complete.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The submission process has been completed; notify the author.
 *}
 
<script>
	$(function() {ldelim}
		$("#submitTabs ul li a").removeAttr("id");
		$("#submitTabs ul li a").removeClass("ui-tabs-anchor");
	{rdelim});
</script>

<h2>{translate key="submission.submit.submissionComplete"}</h2>
<p>{translate key="submission.submit.submissionCompleteThanks" contextName=$context->getLocalizedName()}</p>
<div class="separator"></div>
<h3>{translate key="submission.submit.whatNext"}</h3>
<p>{translate key="submission.submit.whatNext.description"}</p>
<p>{translate key="submission.submit.whatNext.forNow"}</p>

<ul class="plain">
	<li><a href={$reviewSubmissionUrl}>{translate key="submission.submit.whatNext.review"}</a></li>
	<li><a href={url page="submission" op="wizard"}>{translate key="submission.submit.whatNext.create"}</a></li>
	<li><a href={url page="submissions" anchor="submissions"}>{translate key="submission.submit.whatNext.return"}</a></li>
</ul>
