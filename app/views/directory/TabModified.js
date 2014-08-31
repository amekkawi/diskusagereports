define([
	'underscore',
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/TabModified.html',
	'tpl!views/util/ProgressBar.html',
	'tpl!views/util/SortLink.html',
	'tpl!views/util/SortDropdown.html'
], function(_, Marionette, Lang, Template, TemplateProgressBar, TemplateSortLink, TemplateSortDropdown) {
	'use strict';

	return Marionette.ItemView.extend({

		className: 'du-tab-modified',
		template: Template,

		constructor: function(options) {
			if (options)
				_.extend(this, _.pick(options, [ 'app', 'modified', 'route' ]));

			Marionette.ItemView.apply(this, arguments);
		},

		initialize: function() {
			Marionette.ItemView.prototype.initialize.apply(this, arguments);

			var _this = this;
			var dir = this.model;

			var app = this.app;
			var route = this.route || app.getRoute();
			app.request('GetGroupModified', dir, route.sort.modified, route.page)
				.done(function(data) {
					_this.modified = data;

					if (_this._isRendered)
						_this.render();
				})
				.fail(function() {
					// TODO: Show error message.
					console.log('GetGroupModified fail', arguments);
				});
		},

		serializeData: function() {
			var app = this.app;
			var settings = app.settings;
			var dir = this.model;

			return _.defaults({
				modified: this.modified,
				modifiedGroups: settings.get('modified'),
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
