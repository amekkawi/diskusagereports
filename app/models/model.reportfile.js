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
			this.suffix = _.isString(options.suffix) ? [ options.suffix ] : _.toArray(options.suffix);
		},

		url: function() {
			return this.urlRoot + this.id + (this.suffix && this.suffix[0] || this.suffix || '')
		}
	});

});