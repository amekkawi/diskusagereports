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
	'text!templates/view.summary.html',
	'i18n!nls/report'
], function(Backbone, Layout, _, template, lang){

	return Layout.extend({

		template: _.template(template),
		el: false,

		serialize: function() {
			var isValid = this.model && this.model.isValid || false;
			return {
				lang: lang,
				name: isValid ? this.model.attributes.name : '',
				parents: isValid ? this.model.attributes.parents : [],
				size: {
					total: isValid ? this.model.attributes.size.total : [],
					direct: isValid ? this.model.attributes.size.direct : []
				},
				files: {
					total: isValid ? this.model.attributes.files.total : [],
					direct: isValid ? this.model.attributes.files.direct : []
				}
			};
		},

		addListeners: function() {
			if (this.model)
				this.listenTo(this.model, "change:hash", this.render);

			return this;
		}
	});

});