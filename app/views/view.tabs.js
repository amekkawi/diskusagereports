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

		serialize: function() {
			return {
				lang: lang,
				tabs: this.model && this.model.tabs || [],
				selected: this.model && this.model.attributes.tab || null
			};
		},

		addListeners: function() {
			if (this.model)
				this.listenTo(this.model, "change:tab", this.render);
			return this;
		}
	});

});