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
			{
				report: window && window.location
					&& _.isString(window.location.search) && window.location.search.substring(1) || ''
			},
			window && window['reportConfig'] || {}
		),

		router: new Router(),

		models: {
			settings: new ModelSettings(),
			report: new ModelReport()
		}
	};
});