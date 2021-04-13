import CoautorListPanel from '@csp/components/CoautorListPanel/CoautorListPanel.vue';
import SelectReviewerListPanel from '@csp/components/ListPanel/users/SelectReviewerListPanel.vue';
import SubmissionsListPanel from '@csp/components/ListPanel/submissions/SubmissionsListPanel.vue';
import SubmissionsListItem from '@csp/components/ListPanel/submissions/SubmissionsListItem.vue';
// Add to controllers used by OJS
window.pkp.controllers.Container.components.CoautorListPanel = CoautorListPanel;
window.pkp.controllers.Container.components.SelectReviewerListPanel = SelectReviewerListPanel;
window.pkp.controllers.Container.components.SubmissionsListPanel = SubmissionsListPanel;
window.pkp.controllers.Container.components.SubmissionsListItem = SubmissionsListItem;