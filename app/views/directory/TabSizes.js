define([
	'underscore',
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/TabSizes.html',
	'tpl!views/util/ProgressBar.html',
	'tpl!views/util/SortLink.html',
	'tpl!views/util/SortDropdown.html'
], function(_, Marionette, Lang, Template, TemplateProgressBar, TemplateSortLink, TemplateSortDropdown) {
	'use strict';

	return Marionette.ItemView.extend({

		className: 'du-tab-sizes',
		template: Template,

		constructor: function(options) {
			if (options)
				_.extend(this, _.pick(options, [ 'app', 'sizes', 'route' ]));

			Marionette.ItemView.apply(this, arguments);
		},

		initialize: function() {
			Marionette.ItemView.prototype.initialize.apply(this, arguments);

			var _this = this;
			var dir = this.model;

			var app = this.app;
			var route = this.route || app.getRoute();
			app.request('GetGroupSizes', dir, route.sort.sizes, route.page)
				.done(function(data) {
					_this.sizes = data;

					if (_this._isRendered)
						_this.render();
				})
				.fail(function() {
					// TODO: Show error message.
					console.log('GetGroupSizes fail', arguments);
				});
		},

		serializeData: function() {
			var app = this.app;
			var settings = app.settings;
			var dir = this.model;

			return _.defaults({
				sizes: this.sizes,
				sizeGroups: settings.get('sizes'),
				hash: dir.id,
				app: app,
				route: app.getRoute(),
				Lang: Lang,
				progressBar: TemplateProgressBar,
				sortLink: TemplateSortLink,
				sortDropdown: TemplateSortDropdown
			}, Marionette.ItemView.prototype.serializeData.apply(this, arguments));
		}
	});
});
