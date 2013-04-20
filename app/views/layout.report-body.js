/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'backbone',
	'layout',
	'underscore',
	'views/view.message',
	'views/view.tree',
	'views/view.tree-resizer',
	'views/layout.directory'
], function(Backbone, Layout, _, MessageView, TreeView, TreeResizerView, DirectoryLayout){

	return Layout.extend({

		tagName: 'div',
		className: 'du-report-body',

		_models: null,

		initialize: function(options) {
			var models = this._models = options && options.models || {};

			this.setViews({
				'': [
					new MessageView({ model: models.report }),
					new TreeView(),
					new TreeResizerView(),
					new DirectoryLayout({ models: models })
				]
			});
		},

		resize: function(maxWidth, maxHeight) {
			var $el = this.$el;

			if (!$el.is(':visible'))
				return;

			maxWidth = maxWidth || this._lastMaxWidth || $el.width();
			maxHeight = maxHeight || this._lastMaxHeight || $el.height();

			this._lastMaxWidth = maxWidth;
			this._lastMaxHeight = maxHeight;

			var diff = $el.outerHeight(true) - $el.height(),
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