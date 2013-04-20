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
	'text!templates/layout.directory.html',
	'views/view.summary',
	'views/view.tabs'
], function(Backbone, Layout, _, template, SummaryView, TabsView){
	return Layout.extend({

		template: _.template(template),
		tagName: 'div',
		className: 'du-directory du-loading',

		_models: null,

		initialize: function(options) {
			this._models = options && options.models || {};

			this.setViews({
				'.du-directory-head': [
					new SummaryView({ model: this._models.directory }),
					new TabsView({ model: this._models.report })
				]
			});
		},

		resize: function(maxWidth, maxHeight) {
			if (!this.$el.is(':visible'))
				return;

			var $el = this.$el,
				diff = $el.outerHeight(true) - $el.height(),
				innerHeight = maxHeight - diff,

				$body = $el.find('>.du-directory-body'),
				bodyTop = $body.position().top,
				bodyDiff = $body.outerHeight(true) - $body.height();

			$el.find('>.du-directory-body').height(innerHeight - bodyDiff - bodyTop);
		},

		addListeners: function() {
			this.getViews().each(function(view){
				view.addListeners();
			});
			return this;
		}
	});

});