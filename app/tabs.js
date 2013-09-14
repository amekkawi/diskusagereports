/*
 * Disk Usage Reports
 * http://diskusagereports.com/
 *
 * Copyright (c) 2013 André Mekkawi <diskusage@andremekkawi.com>
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */
define([
	'underscore'
], function(_){

	var tabs = [
		{ lang: 'tab_dirs', long: 'dirs', short: 'd', defaults: { column: 2 } },
		{ lang: 'tab_files', long: 'files', short: 'f', defaults: { column: 2 } },
		{ lang: 'tab_modified', long: 'modified', short: 'm', defaults: { column: 2 } },
		{ lang: 'tab_sizes', long: 'sizes', short: 's', defaults: { column: 2 } },
		{ lang: 'tab_ext', long: 'ext', short: 'e', defaults: { column: 2 } },
		{ lang: 'tab_top', long: 'top', short: 't', defaults: { column: 3 } }
	];

	var ret = {
		list: tabs,
		lookup: {}
	};

	_.each(tabs, function(tab) {
		ret.lookup[tab.short]
			= ret.lookup[tab.long]
			= tab;
	});

	return ret;

});
