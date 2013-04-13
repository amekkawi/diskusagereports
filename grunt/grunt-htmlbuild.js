"use strict";
module.exports = function(grunt) {
	var _ = grunt.util._,
		util = require('util'),
		path = require('path');

	var HtmlBuildBlockParser = function(origHtml, options) {
		this.origHtml = origHtml;
		this.options = options;
		this._blockRE = new RegExp('(^[ \t]+)?<!--[ ]*' + options.tagName + ':([^ \\-]+)(?:[ ]+(.+?))?[ ]*-->(([^\\0]|\\0)+?)<!--[ ]*end' + options.tagName + '[ ]*-->', 'gm');
	};

	_.extend(HtmlBuildBlockParser.prototype, {

		parsedHtml: '',

		forEachBlock: function(fn, context) {
			if (!_.isFunction(fn))
				return;

			var match,
				lastIndex = 0

			while(!_.isNull(match = this._blockRE.exec(this.origHtml))) {
				var indent = match[1] || '',
					type = match[2],
					dest = match[3],
					args = {},
					contents = match[4];

				if (_.isString(dest)) {
					if (dest.substr(0, 1) == '{') {
						args = dest;
						dest = null;
					}
					else {
						var argMatch = dest.match(/^([^ ]+)(?:[ ]+(.+))?$/);
						if (argMatch) {
							dest = argMatch[1];

							if (_.isString(argMatch[2]))
								args = argMatch[2];
						}
						else {
							dest = null;
						}
					}
				}

				if (_.isString(dest))
					dest = path.join(this.options.baseUrl, dest);

				if (_.isString(args))
					args = eval('(' + args + ')');

				this.parsedHtml += this.origHtml.substring(lastIndex, match.index) + indent;
				lastIndex = this._blockRE.lastIndex;

				fn.call(context, type, dest, args, contents, match[0]);

				if (_.isFunction(this.typeParser[type])) {
					var replace = this.typeParser[type].call(this, type, dest, args, contents, indent);
					if (_.isString(replace))
						this.parsedHtml += replace;
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

		typeParser: {
			js: function(type, dest, args, contents, indent) {
				var tagRE = /<script( .+)><\/script>/g,
					srcRE = / src="([^"]+)"/,
					requirejsRE = / data-main="([^"]+)"/,
					requirejsDestRE = / data-dest="([^"]+)"/,
					concat = grunt.config(args && args.concat || 'concat'),
					uglify = grunt.config(args && args.uglify || 'uglify'),
					target = args && args.target || this.options.target,
					concatSources = null,
					outTags = [];

				var match;
				while(!_.isNull(match = tagRE.exec(contents))) {
					var srcMatch = match[1].match(srcRE),
						requirejs = match[1].match(requirejsRE),
						requirejsDest = match[1].match(requirejsDestRE);

					// Concat to dest and uglify it.
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
								outTags.push('<script src="' + dest + '"></script>');
							}

							grunt.log.writeln("Adding concat: " + grunt.log.wordlist([srcMatch[1], dest], { separator: ' -> ' }));
							concatSources.push(srcMatch[1]);
						}
					}

					if (requirejs) {
						var requireDest = path.join(this.options.baseUrl, (requirejsDest ? requirejsDest[1] : requirejs[1]));
						grunt.log.writeln("Adding requirejs: " + grunt.log.wordlist([requirejs[1], requireDest], { separator: ' -> ' }));
						outTags.push('<script src="' + requireDest + '"></script>');
					}
				}

				if (concatSources) {
					grunt.log.writeln("Adding uglify: " + grunt.log.wordlist([dest]));
					this._initSrcTarget(uglify, target).push(dest);
				}

				grunt.log.subhead("Config is now:")
				grunt.log.writeln('concat\n' + util.inspect(concat, false, 4, true));
				grunt.log.writeln('uglify\n' + util.inspect(uglify, false, 4, true));

				grunt.config(args && args.concat || 'concat', concat);
				grunt.config(args && args.uglify || 'uglify', uglify);

				var ret = '';
				_.each(outTags, function(val){
					if (ret.length > 0)
						ret += '\n' + indent;

					ret += val;
				});
				return ret;
			}
		}
	});

	grunt.registerMultiTask('htmlbuild', 'Variation of usemin.', function() {
		var options = this.options({
			tagName: 'htmlbuild',
			baseDir: '',
			target: 'htmlbuild'
		});

		this.files.forEach(function(file) {
			if (file.src.length != 1)
				grunt.fail.warn('Must specify only one source per dest.');

			var filepath = file.src[0];

			if (grunt.file.isFile(filepath)) {
				grunt.log.subhead('Processing: ' + filepath);

				try {
					var parser = new HtmlBuildBlockParser(grunt.file.read(filepath), options);

					parser.forEachBlock(function(type, dest, args) {
						grunt.log.subhead("Block found: " + grunt.log.wordlist([
							"type:" + type,
							"dest:" + dest,
							"args:" + util.inspect(args, false, 4, true).replace(/[\n\r]+/, ' ')
						]));
					});

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
		});
	});
};