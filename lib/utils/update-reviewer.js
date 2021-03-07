/**
 * This function should be used to let the element emit events
 * that bubble outside the widget and are published over the
 * event bridge.
 *
 * @protected
 * @param {string} eventName The event to be triggered.
 * @param {Array=} opt_data Additional event data.
 */
$.pkp.classes.Handler.prototype.trigger =
		function(eventName, opt_data) {

	if (opt_data === undefined) {
		opt_data = null;
	}

	// Trigger the event on the handled element.
	var $handledElement = this.getHtmlElement();
	$handledElement.triggerHandler(eventName, opt_data);

	// Trigger the event publicly if it's not
	// published anyway.
	if (!this.publishedEvents_[eventName]) {
		if (eventName === 'dataChanged' && opt_data.length > 1) {
			for (let i = 0; i < opt_data.length; i++) {
				this.triggerPublicEvent_(eventName, opt_data[i]);
			}
		} else {
			this.triggerPublicEvent_(eventName, opt_data);
		}
	}
};

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
