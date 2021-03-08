import CoautorListPanel from '@csp/components/CoautorListPanel/CoautorListPanel.vue';
import SelectReviewerListPanel from '@csp/components/ListPanel/users/SelectReviewerListPanel.vue';
// Add to controllers used by OJS
window.pkp.controllers.Container.components.CoautorListPanel = CoautorListPanel;
window.pkp.controllers.Container.components.SelectReviewerListPanel = SelectReviewerListPanel;
