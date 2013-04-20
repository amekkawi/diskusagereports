/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'backbone'
], function(Backbone){

	return Backbone.Model.extend({

		suffix: null,

		initialize: function(attributes, options) {
			if (_.isString(options.suffix))
				this.suffix = [ options.suffix ];

			else if (!_.isArray(options.suffix) || options.suffix.length < 1 || !_.isString(options.suffix[0]))
				this.suffix = [ '' ];

			else
				this.suffix = options.suffix;
		},

		url: function() {
			return this.urlRoot + this.id + (this.suffix && this.suffix[0] || '')
		}
	});

});