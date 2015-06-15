define([
	'underscore',
	'marionette',
	'i18n!nls/report'
], function(_, Marionette, Lang) {
	return Marionette.ItemView.extend({

		className: 'du-summary-dirs',
		template: _.template(Lang.total_dirs),

		serializeData: function() {
			var model = this.model;
			return {
				total: _.escape(Lang.formatNumber(model.get('dirCount'))),
				direct: _.escape(Lang.formatNumber(model.get('directDirCount'))),
				sub: _.escape(Lang.formatNumber(model.get('subDirCount'))),
				totalRaw: model.get('dirCount'),
				directRaw: model.get('directDirCount'),
				subRaw: model.get('subDirCount')
			};
		}
	});
});
