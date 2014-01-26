define([
	'underscore',
	'models/Model'
], function(_, Model) {
	return Model.extend({
		url: function() {
			if (!this.urlRoot)
				this.urlRoot = this.settings.urlRoot;
			return Model.prototype.url.apply(this, arguments) + this.settings.suffix;
		},
		parse: function(response) {
			this.table = response;
			return {};
		},
		get: function(hash) {
			// Make sure it is a string.
			hash += '';

			var attrs = this.attributes,
				existing = attrs[hash],
				table = this.table;

			// Use cached result.
			if (existing === null || existing != null)
				return existing;

			var index = null;
			_.find(table || [], function(val, i) {
				if (val[0]+'' <= hash && val[1]+'' >= hash) {
					index = i;
					return true;
				}
			}, this);

			return attrs[hash] = (index == null ? null : table[index][2]);
		}
	});
});
