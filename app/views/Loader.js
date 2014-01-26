define([
	'underscore',
	'marionette'
], function(_, Marionette) {
	return Marionette.ItemView.extend({
		className: 'loader hide alert',
		template: '#LoaderTemplate',
		serializeData: function() {
			var error = this._error;
			this.$el
				.toggleClass('alert-info', !error)
				.toggleClass('alert-danger', !!error);

			return {
				error: error,
				url: this.model.url()
			};
		},
		initialize: function() {
			this.listenTo(this.model, 'error', function(model, xhr, options) {
				clearTimeout(this._displayTimeout);
				this.$el.removeClass('hide');
				this._error = {
					status: xhr.status,
					statusText: xhr.statusText
				};
				this.render();
			});
		},
		onShow: function() {
			var self = this;
			this._displayTimeout = _.delay(function() {
				self.$el.removeClass('hide');
			}, 500);
		},
		onClose: function() {
			clearTimeout(this._displayTimeout);
		}
	});
});
