define([
	'backbone',
	'underscore'
], function(Backbone, _){

	return Backbone.Model.extend({

		tabToShort: null,
		tabToLong: null,

		initialize: function() {
			this.tabToShort = {
				dirs: 'd',
				files: 'f',
				modified: 'm',
				sizes: 's',
				ext: 'e',
				top: 't'
			};

			this.tabToLong = _.invert(this.tabToShort);
		},

		defaults: {
			hash: null,
			tab: 'dirs',
			page: 1
		}
	});

});