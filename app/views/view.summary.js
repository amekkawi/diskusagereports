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
			return {
				lang: lang,
				path: [
					{ name: 'Utilities', hash: 'd41d8cd98f00b204e9800998ecf8427e' },
					{ name: 'Spoon' }
				],
				size: { total: 0, direct: 0 },
				files: { total: 0, direct: 0 }
			};
		},

		addListeners: function() {
			if (this.model)
				this.listenTo(this.model, "change:hash", this.render);

			return this;
		}
	});

});