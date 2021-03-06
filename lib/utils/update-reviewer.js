/**
 * @constructor
 *
 * @extends $.pkp.controllers.grid.users.reviewer.form.EditReviewFormHandler
 *
 * @param {jQueryObject} $form the wrapped HTML form element.
 * @param {Object} options form options.
 */

/**
 * Trigger a public event.
 *
 * Public events will bubble outside the widget and will
 * also be forwarded through the event bridge if one has
 * been configured.
 *
 * @private
 * @param {string} eventName The event to be triggered.
 * @param {Array=} opt_data Additional event data.
 */
$.pkp.classes.Handler.prototype.triggerPublicEvent_ =
		function(eventName, opt_data) {

	// Publish the event.
	var $handledElement = this.getHtmlElement();
	$handledElement.parent().trigger(eventName, opt_data);

	// If we have an event bridge configured then re-trigger
	// the event on the target object.
	if (this.eventBridge_) {
		$('[id^="' + this.eventBridge_ + '"]').trigger(eventName, opt_data);
	}
};

/**
 * A generic event dispatcher that will be bound to
 * all handler events. See bind() above.
 *
 * @this {HTMLElement}
 * @param {jQuery.Event} event The jQuery event object.
 * @return {boolean} Return value to be passed back
 *  to jQuery.
 */
$.pkp.classes.Handler.prototype.handleEvent = function(event) {
	var $callingElement, handler, boundEvents, args, returnValue, i, l;

	// This handler is always called out of the
	// handler context.
	$callingElement = $(this);

	// Identify the targeted handler.
	handler = $.pkp.classes.Handler.getHandler($callingElement);

	// Make sure that we really got the right element.
	if ($callingElement[0] !== handler.getHtmlElement.call(handler)[0]) {
		throw new Error(['An invalid handler is bound to the calling ',
			'element of an event!'].join(''));
	}

	// Retrieve the event handlers for the given event type.
	boundEvents = handler.eventBindings_[event.type];
	if (boundEvents === undefined) {
		// We have no handler for this event but we also
		// don't allow bubbling of events outside of the
		// GUI widget!
		return false;
	}

	// Call all event handlers.
	args = $.makeArray(arguments);
	returnValue = true;
	args.unshift(this);
	for (i = 0, l = boundEvents.length; i < l; i++) {
		// Invoke the event handler in the context
		// of the handler object.
		if (boundEvents[i].apply(handler, args) === false) {
			// False overrides true.
			returnValue = false;
		}

		// Stop immediately if one of the handlers requests this.
		if (event.isImmediatePropagationStopped()) {
			break;
		}
	}

	// We do not allow bubbling of events outside of the GUI widget!
	event.stopPropagation();

	// Return the event handler status.
	return returnValue;
};

/**
 * Handle the changed data event.
 * @private
 *
 * @param {jQueryObject} callingElement The calling html element.
 * @param {Event} event The event object (dataChanged).
 * @param {Object} eventData Event data.
 */
$.pkp.controllers.linkAction.LinkActionHandler.prototype.
		dataChangedHandler_ = function(callingElement, event, eventData) {

	if (this.getHtmlElement().parents('.pkp_controllers_grid').length === 0) {
		// We might want to redirect this data changed event to a grid.
		// Trigger another event so parent widgets can handle this
		// redirection.
		this.trigger('redirectDataChangedToGrid', [eventData]);
	}
	this.trigger('notifyUser', [this.getHtmlElement()]);
};

/**
 * Internal callback called after form validation to handle the
 * response to a form submission.
 *
 * You can override this handler if you want to do custom handling
 * of a form response.
 *
 * @param {HTMLElement} formElement The wrapped HTML form.
 * @param {Object} jsonData The data returned from the server.
 * @return {boolean} The response status.
 */
$.pkp.controllers.form.AjaxFormHandler.prototype.handleResponse =
		function(formElement, jsonData) {
	//eslint-disable-next-line
	var $form, formSubmittedEvent, processedJsonData;

	processedJsonData = this.handleJson(jsonData);
	if (processedJsonData !== false) {
		if (processedJsonData.content === '') {
			// Notify any nested formWidgets of form submitted event.
			formSubmittedEvent = new $.Event('formSubmitted');
			$(this.getHtmlElement()).find('.formWidget').trigger(formSubmittedEvent);

			// Trigger the "form submitted" event.
			this.trigger('formSubmitted');

			// Fire off any other optional events.
			this.publishChangeEvents();
			// re-enable the form control if it was disabled previously.
			if (this.disableControlsOnSubmit) {
				this.enableFormControls();
			}
		} else {
			if (/** @type {{reloadContainer: Object}} */ (
					processedJsonData).reloadContainer !== undefined) {
				this.trigger('dataChanged');
				this.trigger('containerReloadRequested', [processedJsonData]);
				return processedJsonData.status;
			}

			// Redisplay the form.
			this.replaceWith(processedJsonData.content);
		}
	} else {
		// data was false -- assume errors, re-enable form controls.
		this.enableFormControls();
	}

	// Trigger the notify user event, passing this
	// html element as data.
	this.trigger('notifyUser', [this.getHtmlElement()]);

	// Hide the form spinner.
	this.hideSpinner();

	return processedJsonData.status;
};

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
		this.triggerPublicEvent_(eventName, opt_data);
	}
};

/**
 * Create a closure that calls the callback in the
 * context of the handler object.
 *
 * NB: Always make sure that the callback is properly
 * unbound and freed for garbage collection. Otherwise
 * you might create a memory leak. If you want to bind
 * an event to the HTMLElement handled by this handler
 * then always use the above bind() method instead which
 * is safer.
 *
 * @param {Function} callback The callback to be wrapped.
 * @param {Object=} opt_context Specifies the object which
 *  |this| should point to when the function is run.
 *  If the value is not given, the context will default
 *  to the handler object.
 * @return {Function} The wrapped callback.
 */
$.pkp.classes.Handler.prototype.callbackWrapper =
		function(callback, opt_context) {

	$.pkp.classes.Handler.checkContext_(this);

	// Create a closure that calls the event handler
	// in the right context.
	if (!opt_context) {
		opt_context = this;
	}
	return function() {
		var args;
		args = $.makeArray(arguments);
		args.unshift(this);
		return callback.apply(opt_context, args);
	};
};

/**
 * This function should be used to pre-process a JSON response
 * from the server.
 *
 * @param {Object} jsonData The returned server response data.
 * @return {Object|boolean} The returned server response data or
 *  false if an error occurred.
 */
$.pkp.classes.Handler.prototype.handleJson = function(jsonData) {
	if (!jsonData) {
		throw new Error('Server error: Server returned no or invalid data!');
	}

	if (jsonData.status === true) {
		// Trigger events passed from the server
		//eslint-disable-next-line
		_.each((/** @type {{ events: Object }} */ (jsonData)).events,
				function(event) {
					/** @type {{isGlobalEvent: boolean}} */
					//eslint-disable-next-line
					var eventData = _.has(event, 'data') ? event.data : null;
					//eslint-disable-next-line
					if (!_.isNull(eventData) && eventData.isGlobalEvent) {
						eventData.handler = this;
						pkp.eventBus.$emit(event.name, eventData);
					} else {
						this.trigger(event.name, eventData);
					}
				}, this);
		return jsonData;
	} else {
		// If we got an error message then display it.
		if (jsonData.content) {
			alert(jsonData.content);
		}
		return false;
	}
};

/**
 * Close the modal when a form submission is complete
 * @param {Object} callingContext The calling element or object.
 * @param {Event} event The triggering event (e.g. a click on
 *  a button.
 * @protected
 */
$.pkp.controllers.modal.AjaxModalHandler.prototype.formSubmitted =
		function(callingContext, event) {

	this.getHtmlElement().parent().trigger('notifyUser');
	this.modalClose();
};


/**
 * Callback to insert, remove or replace a row after an
 * element has been inserted, update or deleted.
 *
 * @protected
 *
 * @param {Object} ajaxContext The AJAX request context.
 * @param {Object} jsonData A parsed JSON response object.
 * @return {boolean|undefined} Return false when no replace action is taken.
 */
$.pkp.controllers.grid.GridHandler.prototype.replaceElementResponseHandler =
		function(ajaxContext, jsonData) {
	var elementId, $element, handledJsonData, castJsonData, $responseElement,
			$responseRow, $responseControlRow, $responseRows, $responseRowsControls,
			index, limit;

	handledJsonData = this.handleJson(jsonData);
	if (handledJsonData !== false) {
		if (handledJsonData.elementNotFound) {
			// The server reported that this element no
			// longer exists in the database so let's
			// delete it.
			elementId = handledJsonData.elementNotFound;
			$element = this.getRowByDataId(elementId);

			// Sometimes we get a delete event before the
			// element has actually been inserted (e.g. when deleting
			// elements due to a cancel action or similar).
			if ($element.length > 0) {
				this.deleteElement($element);
			}
		} else {
			// The server returned mark-up to replace
			// or insert the row.
			$responseElement = $(handledJsonData.content);
			if ($responseElement.filter("tr:not('.row_controls')").length > 1) {
				$responseRows = $responseElement.filter('tr.gridRow');
				$responseRowsControls = $responseElement.filter('tr.row_controls');
				for (index = 0, limit = $responseRows.length; index < limit; index++) {
					$responseRow = $($responseRows[index]);
					$responseControlRow = this.getControlRowByGridRow($responseRow,
							$responseRowsControls);
					this.insertOrReplaceElement($responseRow.add($responseControlRow));
				}
			} else {
				this.insertOrReplaceElement(handledJsonData.content);
			}

			castJsonData = /** @type {{sequenceMap: Array}} */ (handledJsonData);
			this.resequenceRows(castJsonData.sequenceMap);
		}
	}

	this.callFeaturesHook('replaceElementResponseHandler', handledJsonData);
};

/**
 * Refresh either a single row of the grid or the whole grid.
 *
 * @protected
 *
 * @param {HTMLElement} sourceElement The element that
 *  issued the event.
 * @param {Event} event The triggering event.
 * @param {number|Object=} opt_elementId The id of a data element that was
 *  updated, added or deleted. If not given then the whole grid
 *  will be refreshed.
 *  @param {Boolean=} opt_fetchedAlready Flag that subclasses can send
 *  telling that a fetch operation was already handled there.
 */
$.pkp.controllers.grid.GridHandler.prototype.refreshGridHandler =
		function(sourceElement, event, opt_elementId, opt_fetchedAlready) {
	var params;

	this.callFeaturesHook('refreshGrid', opt_elementId);

	params = this.getFetchExtraParams();


	// Check if subclasses already handled the fetch of new elements.
	if (!opt_fetchedAlready) {
		if (opt_elementId) {
			if (opt_elementId ==
					$.pkp.controllers.grid.GridHandler.FETCH_ALL_ROWS_ID) {
				$.get(this.fetchRowsUrl, params,
						this.callbackWrapper(this.replaceElementResponseHandler), 'json');
			} else {
				params.rowId = opt_elementId;
				// Retrieve a single row from the server.
				$.get(this.fetchRowUrl, params,
						this.callbackWrapper(this.replaceElementResponseHandler), 'json');
			}
		} else {
			// Retrieve the whole grid from the server.
			$.get(this.fetchGridUrl_, params,
					this.callbackWrapper(this.replaceGridResponseHandler_), 'json');
		}
	}

	// Let the calling context (page?) know that the grids are being redrawn.
	this.trigger('gridRefreshRequested');
	this.publishChangeEvents();
};

/**
 * Callback to replace a grid's content.
 *
 * @private
 *
 * @param {Object} ajaxContext The AJAX request context.
 * @param {Object} jsonData A parsed JSON response object.
 */
$.pkp.controllers.grid.GridHandler.prototype.replaceGridResponseHandler_ =
		function(ajaxContext, jsonData) {
	var handledJsonData, $grid, $gridParent, $newGrid,
			isFilterVisible;

	handledJsonData = this.handleJson(jsonData);
	if (handledJsonData !== false) {
		// Get the grid that we're updating
		$grid = this.getHtmlElement();
		$gridParent = $grid.parent();

		isFilterVisible = $grid.find('.filter').is(':visible');

		// Replace the grid content
		this.replaceWith(handledJsonData.content);

		// Update the html element of this handler.
		$newGrid = $('div[id^="' + this.getGridIdPrefix() + '"]', $gridParent);
		this.setHtmlElement($newGrid);

		// Refresh row action event binding.
		this.activateRowActions_();

		if (isFilterVisible) {
			// Open search control again.
			$newGrid.find('.pkp_linkaction_search').click();
		}
	}
};


/**
 * Inserts or replaces a grid element.
 * @param {string|jQueryObject} elementContent The new mark-up of the element.
 * @param {boolean=} opt_prepend Prepend the new row instead of append it?
 */
$.pkp.controllers.grid.GridHandler.prototype.insertOrReplaceElement =
		function(elementContent, opt_prepend) {
	var $newElement, newElementId, $grid, $existingElement;
	// Parse the HTML returned from the server.
	$newElement = $(elementContent);
	newElementId = $newElement.attr('id');

	// Does the element exist already?
	$grid = this.getHtmlElement();
	$existingElement = newElementId ?
			$grid.find('#' +
			$.pkp.classes.Helper.escapeJQuerySelector(
			/** @type {string} */ (newElementId))
			) :
			null;

	if ($existingElement !== null && $existingElement.length > 1) {
		throw new Error('There were ' + $existingElement.length +
				' rather than 0 or 1 elements to be replaced!');
	}

	if (!this.hasSameNumOfColumns($newElement)) {
		// Redraw the whole grid so new columns
		// get added/removed to match element.
		$.get(this.fetchGridUrl_, null,
				this.callbackWrapper(this.replaceGridResponseHandler_), 'json');
	} else {
		if ($existingElement !== null && $existingElement.length === 1) {
			// Update element.
			this.replaceElement($existingElement, $newElement);
		} else {
			// Insert row.
			this.addElement($newElement, null, opt_prepend);
		}

		// Refresh row action event binding.
		this.activateRowActions_();
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
