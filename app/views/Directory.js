define([
	'marionette',

	'bootstrap/dropdown'
], function(Marionette) {
	return Marionette.Layout.extend({
		template: '#DirectoryTemplate',
		onRender: function() {
			this.$el.find('.dropdown-menu').dropdown();
		}
	});
});
