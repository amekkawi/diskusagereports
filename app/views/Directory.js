define([
	'marionette',
	'text!templates/Directory.html',

	'bootstrap/dropdown'
], function(Marionette, Template) {
	return Marionette.Layout.extend({
		template: _.template(Template),
		onRender: function() {
			this.$el.find('.dropdown-menu').dropdown();
		}
	});
});
