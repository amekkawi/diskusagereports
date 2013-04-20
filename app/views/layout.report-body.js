/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'app',
	'backbone',
	'layout',
	'underscore',
	'views/view.message',
	'views/view.tree',
	'views/view.tree-resizer',
	'views/layout.directory'
], function(app, Backbone, Layout, _, MessageView, TreeView, TreeResizerView, DirectoryLayout){

	var messageView = new MessageView({ model: app.models.report }),
		treeView = new TreeView(),
		treeResizerView = new TreeResizerView(),
		directoryLayout = new DirectoryLayout();

	return Layout.extend({

		tagName: 'div',
		className: 'du-report-body',

		views: {
			'': [
				treeView,
				treeResizerView,
				messageView,
				directoryLayout
			]
		},

		resize: function(maxWidth, maxHeight) {
			if (!this.$el.is(':visible'))
				return;

			maxWidth = maxWidth || this._lastMaxWidth || this.$el.width();
			maxHeight = maxHeight || this._lastMaxHeight || this.$el.height();

			this._lastMaxWidth = maxWidth;
			this._lastMaxHeight = maxHeight;

			var $el = this.$el,
				diff = $el.outerHeight(true) - $el.height(),
				innerHeight = maxHeight - diff;

			$el.height(innerHeight);

			this.getViews().each(function(view){
				view.resize(maxWidth, innerHeight);
			});
		},

		addListeners: function() {
			if (this.model)
				this.listenTo(this.model, "change:message", this._changeMessage);

			this.getViews().each(function(view){
				view.addListeners();
			});
			return this;
		},

		_changeMessage: function(model, message) {
			var isString = _.isString(message),
				changed = _.isString(model.previous('message')) !== isString;

			if (changed) {
				this.$el[isString ? 'addClass' : 'removeClass']('du-showmessage');
				this.resize();
			}
		}
	});

});