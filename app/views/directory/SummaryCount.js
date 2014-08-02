define([
	'underscore',
	'marionette',
	'i18n!nls/report'
], function(_, Marionette, Lang) {
	return Marionette.ItemView.extend({

		className: 'du-summary-count',
		template: _.template(Lang.total_files),

		serializeData: function() {
			var model = this.model;
			return {
				total: _.escape(Lang.formatNumber(model.get('fileCount'))),
				direct: _.escape(Lang.formatNumber(model.get('directFileCount'))),
				sub: _.escape(Lang.formatNumber(model.get('subFileCount'))),
				totalRaw: model.get('fileCount'),
				directRaw: model.get('directFileCount'),
				subRaw: model.get('subFileCount')
			};
		}
	});
});
