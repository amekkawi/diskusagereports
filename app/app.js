define([
	'module',
	'underscore',
	'router',
	'model.settings',
	'model.report'
],
function(module, _, Router, ModelSettings, ModelReport) {
	return {
		version: '@@SourceVersion',

		config: _.extend(
			module.config() || {},
			window && window['reportConfig'] || {}
		),

		router: new Router(),

		models: {
			settings: new ModelSettings(),
			report: new ModelReport()
		}
	};
});