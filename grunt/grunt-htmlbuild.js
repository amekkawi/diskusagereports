"use strict";
module.exports = function(grunt) {
	var _ = grunt.util._,
		util = require('util'),
		path = require('path');

	var BlockParser = function(origHtml, task, options) {
		this.origHtml = origHtml;
		this.task = task;
		this.options = options;
		this._blockRE = new RegExp(

			// Indent whitespace
			'(^[ \t]+)?'

			// Begin tag
			+ '<!--[ ]*' + options.tagName

				// Type
				+ ':([^ \\-]+)'

				// Args
				+ '(?:[ ]+(.+?))?'

				+ '[ ]*-->'

			// Contents
			+ '(([^\\0]|\\0)+?)'

			// End Tag
			+ '<!--[ ]*end' + options.tagName + '[ ]*-->'

		, 'gm');
	};

	_.extend(BlockParser.prototype, {

		parsedHtml: '',

		run: function() {
			var match,
				lastIndex = 0;

			while(!_.isNull(match = this._blockRE.exec(this.origHtml))) {
				var indent = match[1] || '',
					type = match[2],
					args = match[3],
					contents = match[4];

				// Append the HTML before the matched block to the output HTML.
				this.parsedHtml += this.origHtml.substring(lastIndex, match.index) + indent;
				lastIndex = this._blockRE.lastIndex;

				grunt.event.emit(this.task.name + '.blockfound', { type: type, args: args, contents: contents, contentsFull: match[0] });

				var parser = null;

				// User-defined type parser.
				if (_.isFunction(this.options.typeParser[type]))
					parser = this.options.typeParser[type];

				// Built-in type parsers.
				else if (_.isFunction(this.typeParser[type]))
					parser = this.typeParser[type];

				if (parser) {
					var replace = parser.apply(this, [ { type: type, args: args, contents: contents, indent: indent } ]);

					if (_.isString(replace))
						this.parsedHtml += replace;

					else if (_.isArray(replace))
						this.parsedHtml += replace.join('\n' + indent);
				}
			}

			this.parsedHtml += this.origHtml.substr(lastIndex);
		},

		_initFilesTarget: function(config, target, dest) {
			if (!config.hasOwnProperty(target))
				config[target] = {};

			if (!config[target].hasOwnProperty('files'))
				config[target].files = {};

			if (!config[target].files.hasOwnProperty(dest))
				config[target].files[dest] = [];

			return config[target].files[dest];
		},

		_initSrcTarget: function(config, target) {
			if (!config.hasOwnProperty(target))
				config[target] = {};

			if (!config[target].hasOwnProperty('src'))
				config[target].src = [];

			return config[target].src;
		},

		splitArgs: function(args, limit) {
			if (!_.isString(args))
				return args;

			if (limit == 1)
				return [ args ];

			var re = /^([^ \t]+)(?:[ \t]+(.+)])?$/,
				ret = [],
				match;

			if (!_.isFinite(limit))
				limit = -1;

			while (_.isString(args) && !_.isNull(match = args.match(re))) {
				ret.push(match[1]);
				args = match[2];

				if (limit > 0)
					limit--;

				if (limit == 0) {
					ret.push(args);
					return ret;
				}
			}

			return ret;
		},

		typeParser: {
			js: function(opts) {
				var args = opts.args,
					contents = opts.contents,
					dest = null,
					tagRE = /<script( .+)><\/script>/g,
					srcRE = / src="([^"]+)"/,
					requirejsRE = / data-main="([^"]+)"/,
					requirejsDestRE = / data-dest="([^"]+)"/,
					concat = grunt.config(this.options.concat || 'concat'),
					uglify = grunt.config(this.options.uglify || 'uglify'),
					target = this.options.target,
					concatSources = null,
					outTags = [];

				if (_.isString(args)) {
					var splitArgs = args.split(/[ \t]+/);
					if (splitArgs.length > 0) {
						dest = path.join(this.options.baseUrl, splitArgs[0]);
						grunt.verbose.writeln("Set destination to " + dest);
					}
				}

				var match;
				while(!_.isNull(match = tagRE.exec(contents))) {
					grunt.verbose.writeln("Parsing tag: " + match[0]);

					var srcMatch = match[1].match(srcRE),
						requirejs = match[1].match(requirejsRE),
						requirejsDest = match[1].match(requirejsDestRE);

					// Concat scripts to dest.
					if (dest) {
						if (_.isNull(srcMatch)) {
							grunt.fail.warn("Tag missing src attribute: " + match[0]);
						}
						else if (srcMatch[1].match(/^(\/|(\w+:\/\/))/i)) {
							grunt.log.writeln("Skipping root or absolute URL: " + match[1]);
						}
						else {
							if (_.isNull(concatSources)) {
								concatSources = this._initFilesTarget(concat, target, dest);

								// The <script> tag for dest.
								outTags.push('<script src="' + dest + '"></script>');
								grunt.verbose.writeln("Added tag: " + outTags[outTags.length - 1]);
							}

							grunt.log.writeln("Adding concat: " + grunt.log.wordlist([srcMatch[1], dest], { separator: ' -> ' }));
							concatSources.push(srcMatch[1]);
						}
					}

					// Include an extra <script> tag for requirejs scripts specified in 'data-main'.
					if (requirejs) {
						var requireDest = path.join(this.options.baseUrl, (requirejsDest ? requirejsDest[1] : requirejs[1]));
						grunt.log.writeln("Adding requirejs: " + grunt.log.wordlist([requirejs[1], requireDest], { separator: ' -> ' }));
						outTags.push('<script src="' + requireDest + '"></script>');
						grunt.verbose.writeln("Added tag: " + outTags[outTags.length - 1]);
					}
				}

				// Uglify dest if it will be created.
				if (concatSources) {
					grunt.log.writeln("Adding uglify: " + grunt.log.wordlist([dest]));
					this._initSrcTarget(uglify, target).push(dest);
				}

				grunt.event.emit(this.task.name + '.configchanged', { name: 'concat', config: concat });
				grunt.event.emit(this.task.name + '.configchanged', { name: 'uglify', config: uglify });

				grunt.config(this.options.concat || 'concat', concat);
				grunt.config(this.options.uglify || 'uglify', uglify);

				return outTags;
			}
		}
	});

	grunt.registerMultiTask('htmlbuild', 'Variation of usemin.', function() {
		var options = this.options({
			tagName: 'htmlbuild',
			baseDir: '',
			target: 'htmlbuild',
			typeParser: {}
		});

		this.files.forEach(function(file) {
			if (file.src.length != 1)
				grunt.fail.warn('Must specify only one source per dest.');

			var filepath = file.src[0];

			if (grunt.file.isFile(filepath)) {
				grunt.log.subhead('Processing: ' + filepath);

				try {
					var parser = new BlockParser(grunt.file.read(filepath), this, options),
						configChanged = null;

					grunt.event.on(this.name + '.blockfound', function(ev) {
						grunt.log.subhead("Block found: " + grunt.log.wordlist([
							ev.type + ' ' + ev.args
						]));
					});

					grunt.event.on(this.name + '.configchanged', function(ev) {
						grunt.verbose.writeln("Config changed: " + ev.name);
						if (_.isNull(configChanged))
							configChanged = {};

						configChanged[ev.name] = ev.config;
					});

					parser.run();
					grunt.verbose.writeln("Run complete");

					if (configChanged) {
						grunt.log.subhead("Config is now:")
						_.each(configChanged, function(val, key) {
							grunt.log.writeln(key + ':\n' + util.inspect(val, false, 4, true));
						});
					}

					try {
						grunt.file.write(file.dest, parser.parsedHtml);
					}
					catch (e) {
						grunt.verbose.error(e);
						grunt.fail.warn('Failed to write dest file: ' + file.dest);
					}

				} catch (e) {
					grunt.verbose.error(e);
					grunt.fail.warn('Failed to read file: ' + filepath);
				}
			}
		}, this);
	});
};