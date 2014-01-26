define([
	'underscore',
	'jquery',
	'marionette',
	'models/Settings',
	'models/DirLookup',
	'models/Dir',
	'views/Loader',
	'views/Title',
	'views/Error',
	'views/AjaxError',
	'views/Directory'
], function(_, $, Marionette, ModelSettings, ModelDirLookup, ModelDir, ViewLoader, ViewTitle, ViewError, ViewAjaxError, ViewDirectory) {
	return Marionette.Application.extend({
		constructor: function(options) {
			Backbone.Marionette.Application.apply(this, arguments);

			this.addRegions({
				title: '#AppContainer .du-title .container',
				body: '#AppContainer .du-body'
			});

			this.addInitializer(function(options) {
				console.log('init', options);

				var self = this,
					suffixList = options.suffix.slice(0);

				// Settings model
				var settings = this.settings = new ModelSettings({}, {
					urlRoot: options.url.replace(/\/+$/, ''),
					id: 'settings',
					suffix: suffixList.shift()
				});

				var dirLookup = self.dirLookup = new ModelDirLookup({}, {
					settings: settings,
					id: 'dirmap_lookup'
				});

				this.title.show(new ViewTitle({ model: settings }));

				this.listenTo(settings, {
					sync: function(model, resp, options) {
						console.log('sync settings', model.attributes);
						dirLookup.fetch();
					},
					error: function(model, xhr, options) {
						if (suffixList.length) {
							settings.suffix = suffixList.shift();
							console.log('Trying next suffix: ' + settings.suffix);
							settings.fetch();
						}
						else {
							console.log('showing error', suffixList);
							self.body.show(
								new ViewAjaxError({
									name: 'main report file',
									xhr: xhr,
									url: model.url()
								})
							);
						}
					}
				});

				this.listenTo(dirLookup, {
					sync: function(model, resp, options) {
						console.log('sync dirLookup');
						self.body.close();
						Backbone.history.start();
					},
					error: function(model, xhr, options) {
						self.body.show(
							new ViewAjaxError({
								name: 'directory lookup file',
								xhr: xhr,
								url: model.url()
							})
						);
					}
				});

				this.vent.on('route:directory', function(hash) {
					hash = hash || settings.get('root');
					console.log('app loadDirectory', arguments);
					this.getDirModel(hash || settings.get('root'))
						.done(function(model) {
							console.log('loadDirectory success', model.attributes);
							self.body.show(
								new ViewDirectory({
									model: model
								})
							);
						})
						.fail(function(model, error) {
							if (error === 'abort') return;
							console.log('loadDirectory failed', error);
							self.body.show(
								new ViewError({
									message: 'Directory not found: ' + hash
								})
							);
						});
				}, this);

				this.body.show(
					new ViewLoader({
						model: settings
					})
				);

				settings.fetch();
			});
		},

		getDirModel: function(hash) {
			this.xhr && this.xhr.abort();

			var settings = this.settings,
				deferred = $.Deferred();

			if (!settings.has('root'))
				return deferred.reject('NO_SETTINGS');

			var dirMapIndex = this.dirLookup.get(hash);
			if (dirMapIndex == null)
				return deferred.reject('NOT_FOUND');

			var model = new ModelDir({
				hash: hash
			}, {
				id: 'dirmap_' + dirMapIndex,
				settings: settings
			});

			this.xhr = model.fetch({
				success: function(model) {
					//console.log('dirModel fetch success', model.attributes);
					if (model.has('name'))
						deferred.resolve(model);
					else
						deferred.reject('NOT_FOUND');
				},
				error: function(model, xhr) {
					var error = xhr.readyState < 4 ? 'ABORT' : 'NOT_FOUND';
					//console.log('dirModel fetch error', model.get('hash'), error, xhr);
					deferred.reject(error);
				}
			});

			return deferred;
		}
	});
});
