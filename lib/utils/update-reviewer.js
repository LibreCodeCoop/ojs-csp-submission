$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler.prototype.updateReviewerSelection = function(
    sourceComponent,
    selectedReviewers
) {
    var id = '',
        name = '';

    if (!selectedReviewers.length) {
        this.selectedReviewer = null;
        id = name = '';
    } else {
        // Only supports a single reviewer select at a time fo rnow
        // this.selectedReviewer = selectedReviewers[0];
        //eslint-disable-next-line
        let ids = [];
        let names = [];
        this.selectedReviewer = JSON.stringify(selectedReviewers);

        for (let i = 0; i < selectedReviewers.length; i++) {
            ids.push(selectedReviewers[i].id);
            names.push(selectedReviewers[i].fullName);
        }

        id = JSON.stringify(ids);
        name = names.join(', ');
    }

    $('#reviewerId', this.getHtmlElement()).val(id);
    $('[id^="selectedReviewerName"]', this.getHtmlElement()).html(name);
};