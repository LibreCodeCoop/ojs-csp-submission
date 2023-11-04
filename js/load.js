import PkpLoad from '../../../../lib/pkp/js/load.js';

import SubmissionWizardPage from '@csp/components/Conteiner/SubmissionWizardPage.vue';

window.pkp = Object.assign(PkpLoad, {
	controllers: {
		SubmissionWizardPage
	},
});
