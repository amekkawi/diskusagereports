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
			var model = this.model,
				attributes = model && model.attributes,
				isValid = model && model.isValid() || false;

			return {
				lang: lang,
				name: isValid ? attributes.name : '',
				parents: isValid ? attributes.parents : [],
				size: {
					total: isValid ? attributes.size.total : [],
					direct: isValid ? attributes.size.direct : []
				},
				files: {
					total: isValid ? attributes.files.total : [],
					direct: isValid ? attributes.files.direct : []
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