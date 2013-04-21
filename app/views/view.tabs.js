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
	'text!templates/view.tabs.html',
	'i18n!nls/report'
], function(Backbone, Layout, _, template, lang){

	return Layout.extend({

		template: _.template(template),
		el: false,

		initialize: function(options) {
			this._models = options && options.models || {};
			this.model = this._models.report;
		},

		serialize: function() {
			var model = this.model,
				attributes = model.attributes;

			return {
				lang: lang,
				hash: attributes.hash,
				tabs: model.tabs,
				selected: attributes.tab
			};
		},

		addListeners: function() {
			if (this.model)
				this.listenTo(this.model, "change", this.render);

			return this;
		}
	});

});