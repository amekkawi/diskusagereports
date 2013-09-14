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
	'text!templates/view.table.dirs.html',
	'i18n!nls/report',
	'tabs'
], function(Backbone, Layout, _, template, lang, Tabs){

	return Layout.extend({

		template: _.template(template),
		tagName: 'div',
		className: 'du-table-dirs',

		initialize: function(options) {
			this._models = options && options.models || {};
			this.model = this._models.directory;

			this._pageMax = options && options.pageMax;
		},

		serialize: function() {
			var attributes = this.model.attributes,
				report = this._models.report;

			return {
				lang: lang,
				tab: Tabs.lookup[report.attributes.tab],
				files: []
			};
		},

		addListeners: function() {
			this.listenTo(this.model, "change:files", this.render);
			return this;
		}
	});

});
