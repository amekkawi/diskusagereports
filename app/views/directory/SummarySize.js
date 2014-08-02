define([
	'underscore',
	'marionette',
	'i18n!nls/report'
], function(_, Marionette, Lang) {
	return Marionette.ItemView.extend({

		className: 'du-summary-size',
		template: _.template(Lang.total_size),

		serializeData: function() {
			var model = this.model;
			return {
				total: _.escape(Lang.formatBytes(model.get('fileSize'))),
				direct: _.escape(Lang.formatBytes(model.get('directFileSize'))),
				sub: _.escape(Lang.formatBytes(model.get('subFileSize'))),
				totalRaw: model.get('fileSize'),
				directRaw: model.get('directFileSize'),
				subRaw: model.get('subFileSize')
			};
		}
	});
});
