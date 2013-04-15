"use strict";
module.exports = function(grunt) {
	var _ = grunt.util._,
		util = require('util'),
		path = require('path');

	var BlockParser = function(origHtml, task, options) {
		this.origHtml = origHtml;
		this.task = task;
		this.options = options;
		this._blockRE =
			'(?:'

				// Indent whitespace
				+ '(^[ \t]+)?'

				// Begin tag
				+ '<!--[ \t]*' + options.tagName

					// Type
					+ ':([^ \\-]+)'

					// Args
					+ '(?:[ ]+(.+?))?'

					// Optional self-closing.
					+ '[ ]*(/)?-->'

			+ ')|('

				// End Tag
				+ '<!--[ \t]*end' + options.tagName + '[ \t]*-->'

			+ ')';
		this._blockREFlags = 'mg';
	};

	_.extend(BlockParser.prototype, {

		parse: function() {
			var re = new RegExp(this._blockRE, this._blockREFlags),
				contents = '',
				lastIndex = 0,
				match;

			while (!_.isNull(match = re.exec(this.origHtml))) {
				var indent = match[1] || '';
				contents += this.origHtml.substring(lastIndex, match.index)
					+ indent
					+ this._beginBlock(re, match);

				lastIndex = re.lastIndex;
			}

			return contents + this.origHtml.substring(lastIndex);
		},

		_beginBlock: function(re, match) {
			var contents = '',
				indent = match[1] || '',
				type = match[2],
				args = match[3],
				lastIndex = re.lastIndex;

			if (_.isString(match[4])) {
				grunt.event.emit(this.task.name + '.blocksingle', { type: type, args: args, beginTag: match[0] });
			}
			else {
				grunt.event.emit(this.task.name + '.blockbegin', { type: type, args: args, beginTag: match[0] });

				var nextMatch;
				while (!_.isNull(nextMatch = re.exec(this.origHtml)) && !_.isString(nextMatch[5])) {
					contents += this.origHtml.substring(lastIndex, nextMatch.index) + indent;

					var subBlock = this._beginBlock(re, nextMatch);
					if (subBlock === false)
						return false;

					// Append the result of the sub block.
					contents += subBlock;

					// Reset the last index to where the sub block left off.
					lastIndex = re.lastIndex;
				}

				if (_.isNull(nextMatch)) {
					grunt.fail.warn('Missing end tag for block. ' + match[0]);
					return false;
				}

				// Append any contents remaining after sub blocks.
				// If there were no sub blocks, then this will be the
				// contents since this block's begin tag.
				contents += this.origHtml.substring(lastIndex, nextMatch.index);
			}

			var parsed = this._runParser(indent, type, args, contents);
			if (!_.isString(match[4]))
				grunt.event.emit(this.task.name + '.blockend', { type: type, args: args, contents: contents, beginTag: match[0] });

			return parsed;
		},

		_runParser: function(indent, type, args, contents) {
			var parser = null;

			// User-defined type parser.
			if (this.options.typeParser && _.isFunction(this.options.typeParser[type]))
				parser = this.options.typeParser[type];

			// Built-in type parsers.
			else if (this.typeParser && _.isFunction(this.typeParser[type]))
				parser = this.typeParser[type];

			if (parser) {
				var replace = parser.apply(this, [ { type: type, args: args, contents: contents, indent: indent } ]);

				if (_.isString(replace))
					return replace;

				else if (_.isArray(replace))
					return replace.join('\n' + indent);
			}

			return '';
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

		getTags: function(elementName, html) {
			var tagRE = new RegExp('<' + elementName + '( .+?)/?>', 'ig'),
				attrRE = / ([a-z0-9_\-]+)="([^"]+)"/ig,
				tags = [],
				tagMatch, attrMatch;

			while (!_.isNull(tagMatch = tagRE.exec(html))) {
				var tag = {
					_html: tagMatch[0]
				};

				while (!_.isNull(attrMatch = attrRE.exec(tagMatch[1]))) {
					tag[attrMatch[1]] = attrMatch[2];
				}

				tags.push(tag);
			}

			return tags;
		},

		typeParser: {
			requirejs: function(opts) {
				var syntax = 'Syntax: <data-main> [<dest> [<target>]]',
					args = opts.args;

				if (!_.isString(args))
					grunt.fail.warn('Missing arguments. ' + syntax);

				var target = this.options.target,
					outTags = [],
					splitArgs = args.split(/[ \t]+/);

				// Parse Arguments

				if (splitArgs.length < 1)
					grunt.fail.warn('Missing arguments. ' + syntax);

				var main = splitArgs.shift();
				grunt.event.emit(this.task.name + '.notice', { verbose: true, message: "Set main to " + main });

				var dest = { short: main + '.js', full: path.join(this.options.baseUrl, main + '.js') };
				if (splitArgs.length) {
					dest = { short: splitArgs[0], full: path.join(this.options.baseUrl, splitArgs[0]) };
					grunt.event.emit(this.task.name + '.notice', { verbose: true, message: "Set destination to " + dest.full });
					splitArgs.shift();
				}

				if (splitArgs.length) {
					target = splitArgs.shift();
					grunt.event.emit(this.task.name + '.notice', { verbose: true, message: "Set target to " + target });
				}

				// Process tags.

				outTags.push('<script src="' + dest.short + '"></script>');
				grunt.event.emit(this.task.name + '.notice', { verbose: true, message: "Added tag: " + outTags[outTags.length - 1] });

				grunt.event.emit(this.task.name + '.requirejs', {
					target: target,
					src: main + '.js',
					dest: dest.full,
					options: {
						baseUrl: path.dirname(main),
						name: path.basename(main),
						out: dest.full,
						mainConfigFile: main + '.js'
					}
				});

				grunt.event.emit(this.task.name + '.uglify', { target: target, src: dest.full, dest: dest.full });

				return outTags;
			},

			js: function(opts) {
				var syntax = 'Syntax: <dest>',
					args = opts.args;

				if (!_.isString(args))
					grunt.fail.warn('Missing arguments. ' + syntax);

				var contents = opts.contents,
					target = this.options.target,
					outTags = [],
					splitArgs = args.split(/[ \t]+/);

				if (splitArgs.length < 1)
					grunt.fail.warn('Missing arguments. ' + syntax);

				var dest = { short: splitArgs[0], full: path.join(this.options.baseUrl, splitArgs[0]) };
				grunt.event.emit(this.task.name + '.notice', { verbose: true, message: "Set destination to " + dest.full });

				_.each(this.getTags('script', contents), function(tag){
					grunt.event.emit(this.task.name + '.notice', { verbose: true, message: "Parsing tag: " + tag._html });

					if (!_.isString(tag.src)) {
						grunt.fail.warn("Tag missing src attribute: " + tag._html);
						return false;
					}

					if (!grunt.file.isFile(tag.src)) {
						grunt.fail.warn("Cannot find file for src: " + tag._html);
						return false;
					}

					if (!outTags.length) {
						outTags.push('<script src="' + dest.short + '"></script>');
						grunt.event.emit(this.task.name + '.notice', { verbose: true, message: "Added tag: " + outTags[outTags.length - 1] });
					}

					grunt.event.emit(this.task.name + '.uglify', { target: target, src: tag.src, dest: dest.full });

					if (_.isString(tag['data-main'])) {
						var requireDest = { short: _.isString(tag['data-dest']) ? tag['data-dest'] : tag['data-main'] };
						requireDest.full = path.join(this.options.baseUrl, requireDest.short);

						var requireTarget = tag['data-target'] ? tag['data-target'] : target;

						outTags.push('<script src="' + requireDest.short + '"></script>');
						grunt.event.emit(this.task.name + '.notice', { verbose: true, message: "Added tag: " + outTags[outTags.length - 1] });

						grunt.event.emit(this.task.name + '.requirejs', {
							target: requireTarget,
							src: tag['data-main'] + '.js',
							dest: requireDest.full,
							options: {
								baseUrl: path.dirname(tag['data-main']),
								name: path.basename(tag['data-main']),
								out: requireDest.full,
								mainConfigFile: tag['data-main'] + '.js'
							}
						});

						grunt.event.emit(this.task.name + '.uglify', { target: target, src: requireDest.full, dest: requireDest.full });
					}

				}, this);

				return outTags;
			},

			less: function(opts) {
				var syntax = 'Syntax: <dest>',
					args = opts.args;

				if (!_.isString(args))
					grunt.fail.warn('Missing arguments. ' + syntax);

				var contents = opts.contents,
					target = this.options.target,
					outTags = [],
					splitArgs = args.split(/[ \t]+/);

				// Parse Arguments

				if (splitArgs.length < 1)
					grunt.fail.warn('Missing arguments. ' + syntax);

				var dest = { short: splitArgs[0], full: path.join(this.options.baseUrl, splitArgs[0]) };
				grunt.event.emit(this.task.name + '.notice', { verbose: true, message: "Set destination to " + dest.full });
				splitArgs.shift();

				_.each(this.getTags('link', contents), function(tag){
					grunt.event.emit(this.task.name + '.notice', { verbose: true, message: "Parsing tag: " + tag._html });

					if (!_.isString(tag.href)) {
						grunt.fail.warn("Tag missing href attribute: " + tag._html);
						return false;
					}

					if (!_.isString(tag.rel)) {
						grunt.fail.warn("Tag missing rel attribute: " + tag._html);
						return false;
					}

					if (!_.isString(tag.type)) {
						grunt.fail.warn("Tag missing type attribute: " + tag._html);
						return false;
					}

					if (tag.type != "text/css") {
						grunt.fail.warn("Tag's type attribute is not 'text/css': " + tag._html);
						return false;
					}

					if (!grunt.file.isFile(tag.href)) {
						grunt.fail.warn("Cannot find file for href: " + tag._html);
						return false;
					}

					if (!outTags.length) {
						outTags.push('<link rel="stylesheet" type="text/css" href="' + dest.short + '">');
						grunt.event.emit(this.task.name + '.notice', { verbose: true, message: "Added tag: " + outTags[outTags.length - 1] });
					}

					grunt.event.emit(this.task.name + '.less', { target: target, src: tag.href, dest: dest.full });
				}, this);

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

		var initFilesDest = function(config, target, dest) {
			if (!config.hasOwnProperty(target))
				config[target] = {};

			if (!config[target].hasOwnProperty('files'))
				config[target].files = [];

			var entry = { dest: dest, src: [] };
			config[target].files.push(entry);

			return entry.src;
		};

		this.files.forEach(function(file) {
			if (file.src.length != 1)
				grunt.fail.warn('Must specify only one source per dest.');

			var filepath = file.src[0];

			if (grunt.file.isFile(filepath)) {
				grunt.log.subhead('Processing: ' + filepath);

				try {
					var parser = new BlockParser(grunt.file.read(filepath), this, options),
						configChanged = {},
						concat = grunt.config(options.concat || 'concat'),
						uglify = grunt.config(options.uglify || 'uglify'),
						requirejs = grunt.config(options.requirejs || 'requirejs'),
						less = grunt.config(options.less || 'less'),
						concatDests,
						uglifyDests,
						lessDests;

					grunt.event.on(this.name + '.blocksingle', function(ev) {
						grunt.log.subhead("Build command found: " + grunt.log.wordlist([
							ev.type + ' ' + (ev.args || '')
						]));

						concatDests = {};
						uglifyDests = {};
						lessDests = {};
					});

					grunt.event.on(this.name + '.blockbegin', function(ev) {
						grunt.log.subhead("Block begin: " + grunt.log.wordlist([
							ev.type + ' ' + (ev.args || '')
						]));

						concatDests = {};
						uglifyDests = {};
						lessDests = {};
					});

					grunt.event.on(this.name + '.blockend', function(ev) {
						grunt.log.writeln("Block end: " + grunt.log.wordlist([
							ev.type + ' ' + (ev.args || '')
						]));
					});

					grunt.event.on(this.name + '.notice', function(ev) {
						grunt[ev.verbose ? 'verbose' : 'log'].writeln(ev.message);
					});

					grunt.event.on(this.name + '.concat', function(ev) {
						grunt.log.writeln("Added concat:" + ev.target + " " + grunt.log.wordlist([ ev.src, ev.dest ], { separator: ' -> ' }));
						configChanged['concat'] = concat;

						if (!concatDests[ev.target])
							concatDests[ev.target] = {};

						if (!concatDests[ev.target][ev.dest])
							concatDests[ev.target][ev.dest] = initFilesDest(concat, ev.target, ev.dest);

						concatDests[ev.target][ev.dest].push(ev.src);
					});

					grunt.event.on(this.name + '.uglify', function(ev) {
						grunt.log.writeln("Added uglify:" + ev.target + " " + grunt.log.wordlist([ ev.src, ev.dest ], { separator: ' -> ' }));
						configChanged['uglify'] = uglify;

						if (!uglifyDests[ev.target])
							uglifyDests[ev.target] = {};

						if (!uglifyDests[ev.target][ev.dest])
							uglifyDests[ev.target][ev.dest] = initFilesDest(uglify, ev.target, ev.dest);

						uglifyDests[ev.target][ev.dest].push(ev.src);
					});

					grunt.event.on(this.name + '.less', function(ev) {
						grunt.log.writeln("Added less:" + ev.target + " " + grunt.log.wordlist([ ev.src, ev.dest ], { separator: ' -> ' }));
						configChanged['less'] = less;

						if (!lessDests[ev.target])
							lessDests[ev.target] = {};

						if (!lessDests[ev.target][ev.dest])
							lessDests[ev.target][ev.dest] = initFilesDest(less, ev.target, ev.dest);

						lessDests[ev.target][ev.dest].push(ev.src);
					});

					grunt.event.on(this.name + '.requirejs', function(ev) {
						grunt.log.writeln("Added requirejs:" + ev.target + " " + grunt.log.wordlist([ ev.src, ev.dest ], { separator: ' -> ' }));
						configChanged['requirejs'] = requirejs;

						if (requirejs.hasOwnProperty(ev.target))
							grunt.fail.warn("A requirejs config already exists for the target: " + ev.target + '.');

						requirejs[ev.target] = _.extend(
							{ options: ev.options },
							requirejs[ev.target]
						);

					});

					var parsedHtml = parser.parse();
					grunt.verbose.writeln("Run complete");

					if (configChanged) {
						grunt.log.subhead("Config is now:")
						_.each(configChanged, function(val, key) {
							grunt.config(key, val);
							grunt.log.writeln('\n' + key + ':\n' + util.inspect(val, false, 4, true));
						});
					}

					try {
						grunt.file.write(file.dest, parsedHtml);
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