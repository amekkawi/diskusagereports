define([
	'underscore',
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/TabTop.html',
	'tpl!views/util/ProgressBar.html',
	'tpl!views/util/SortLink.html',
	'tpl!views/util/SortDropdown.html'
], function(_, Marionette, Lang, Template, TemplateProgressBar, TemplateSortLink, TemplateSortDropdown) {
	'use strict';

	return Marionette.ItemView.extend({

		className: 'du-tab-top',
		template: Template,

		constructor: function(options) {
			if (options)
				_.extend(this, _.pick(options, [ 'app', 'top', 'route' ]));

			Marionette.ItemView.apply(this, arguments);
		},

		initialize: function() {
			Marionette.ItemView.prototype.initialize.apply(this, arguments);

			var _this = this;
			var dir = this.model;

			var app = this.app;
			var route = this.route || app.getRoute();
			app.request('GetGroupTop', dir, route.sort.top, route.page)
				.done(function(data) {
					_this.top = data;

					if (_this._isRendered)
						_this.render();
				})
				.fail(function() {
					// TODO: Show error message.
					console.log('GetGroupTop fail', arguments);
				});
		},

		serializeData: function() {
			var app = this.app;
			var dir = this.model;

			return _.defaults({
				top: this.top,
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
