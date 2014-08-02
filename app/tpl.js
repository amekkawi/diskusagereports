define([
	'underscore'
], function (_) {
	return {
		load: function (name, parentRequire, onload, config) {
			parentRequire(['text!' + name], function(raw) {
				onload(_.template(raw, null, { variable: 'args' }));
			});
		}
	};
});
