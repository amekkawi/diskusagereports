/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'backbone',
	'layout',
	'underscore',
	'views/view.tree',
	'views/view.tree-resizer',
	'views/layout.directory'
], function(Backbone, Layout, _, template, TreeView, TreeResizerView, DirectoryLayout){

	var treeView = new TreeView(),
		treeResizerView = new TreeResizerView(),
		directoryLayout = new DirectoryLayout();

	return Layout.extend({

		tagName: 'div',
		className: 'du-report-body',

		views: {
			'': [
				treeView,
				treeResizerView,
				directoryLayout
			]
		},

		resize: function(maxWidth, maxHeight) {
			if (!this.$el.is(':visible'))
				return;

			maxWidth = maxWidth || this._lastMaxWidth || this.$el.width();
			maxHeight = maxHeight || this._lastMaxHeight || this.$el.height();

			this._lastMaxWidth = maxWidth;
			this._lastMaxHeight = maxHeight;

			var $el = this.$el,
				diff = $el.outerHeight(true) - $el.height(),
				innerHeight = maxHeight - diff;

			$el.height(innerHeight);

			this.getViews().each(function(view){
				view.resize(maxWidth, innerHeight);
			});
		},

		addListeners: function() {
			this.getViews().each(function(view){
				view.addListeners();
			});
			return this;
		}
	});

});