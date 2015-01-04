define([
	'underscore',
	'marionette',
	'i18n!nls/report',
	'tpl!views/directory/TabFiles.html',
	'tpl!views/util/ProgressBar.html',
	'tpl!views/util/SortLink.html',
	'tpl!views/util/SortDropdown.html',
	'tpl!views/util/Pagination.html'
], function(_, Marionette, Lang, Template, TemplateProgressBar, TemplateSortLink, TemplateSortDropdown, TemplatePagination) {
	'use strict';

	return Marionette.ItemView.extend({

		className: 'du-tab-files',

		template: function(args) {
			if (args.files === 'loading')
				return '<div class="du-loading" style="height: 24px; margin-top: 16px;"></div>';

			else if (args.files === 'NOT_FOUND')
				return '<div class="du-mesage-error"><span class="glyphicon glyphicon-exclamation-sign"></span> ' + Lang.message_not_found + '</div>';

			else if (args.files === 'NO_DATA')
				return '<div class="du-mesage-info"><span class="glyphicon glyphicon-info-sign"></span> ' + Lang.message_no_data + '</div>';

			return Template.apply(this, arguments);
		},

		constructor: function(options) {
			if (options)
				_.extend(this, _.pick(options, [ 'app', 'files', 'route' ]));

			Marionette.ItemView.apply(this, arguments);
		},

		initialize: function() {
			Marionette.ItemView.prototype.initialize.apply(this, arguments);

			var _this = this;
			var dir = this.model;

			var app = this.app;
			var route = this.route || app.getRoute();

			this.files = 'loading';

			app.request('GetFiles', dir, route.sort.files, route.page)
				.done(function(data) {
					_this.files = data;

					if (_this._isRendered)
						_this.render();
				})
				.fail(function(reason) {
					_this.files = reason;

					if (_this._isRendered)
						_this.render();
				});
		},

		serializeData: function() {
			var app = this.app;
			var settings = app.settings;
			var route = this.route || app.getRoute();

			var dir = this.model;
			var maxPage = Math.ceil(dir.get('directFileCount') / settings.get('perPage'));

			return _.defaults({
				hash: dir.id,
				files: this.files,
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
