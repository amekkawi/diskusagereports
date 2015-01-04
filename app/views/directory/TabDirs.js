define([
	'underscore',
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/TabDirs.html',
	'tpl!views/util/ProgressBar.html',
	'tpl!views/util/SortLink.html',
	'tpl!views/util/SortDropdown.html',
	'tpl!views/util/Pagination.html'
], function(_, Marionette, Lang, Template, TemplateProgressBar, TemplateSortLink, TemplateSortDropdown, TemplatePagination) {
	'use strict';

	return Marionette.ItemView.extend({

		className: 'du-tab-dirs',

		template: function(args) {
			if (args.dirs === 'loading')
				return '<div class="du-loading" style="height: 24px; margin-top: 16px;"></div>';

			else if (args.dirs === 'NOT_FOUND')
				return '<div class="du-mesage-error"><span class="glyphicon glyphicon-exclamation-sign"></span> ' + Lang.message_not_found + '</div>';

			else if (args.dirs === 'NO_DATA')
				return '<div class="du-mesage-info"><span class="glyphicon glyphicon-info-sign"></span> ' + Lang.message_no_data + '</div>';

			return Template.apply(this, arguments);
		},

		constructor: function(options) {
			if (options)
				_.extend(this, _.pick(options, [ 'app', 'dirs', 'route' ]));

			Marionette.ItemView.apply(this, arguments);
		},

		initialize: function() {
			Marionette.ItemView.prototype.initialize.apply(this, arguments);

			var _this = this;
			var dir = this.model;

			var app = this.app;
			var route = this.route || app.getRoute();

			this.dirs = 'loading';

			app.request('GetSubDirs', dir, route.sort.dirs, route.page)
				.done(function(subDirs) {
					_this.dirs = subDirs;

					if (_this._isRendered)
						_this.render();
				})
				.fail(function(reason) {
					_this.dirs = reason;

					if (_this._isRendered)
						_this.render();
				});
		},

		serializeData: function() {
			var app = this.app;
			var settings = app.settings;
			var route = this.route || app.getRoute();

			var dir = this.model;
			var maxPage = Math.ceil(dir.get('directSubDirCount') / settings.get('perPage'));

			return _.defaults({
				hash: dir.id,
				dirs: this.dirs,
				app: app,
				route: app.getRoute(),
				settings: settings.attributes,
				Lang: Lang,
				page: route.page,
				maxPage: maxPage,
				progressBar: TemplateProgressBar,
				sortLink: TemplateSortLink,
				sortDropdown: TemplateSortDropdown,
				pagination: TemplatePagination
			}, Marionette.ItemView.prototype.serializeData.apply(this, arguments));
		}
	});
});
