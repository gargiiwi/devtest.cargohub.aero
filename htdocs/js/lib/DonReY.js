/**
 * DonReY.js - A collection of code used to develop the frontend applications using DonReY framework
 * Includes:
 * - a few useful global methods: dd(), array_Compare()
 * - DonReY Singleton (used to organize an application)
 * - donrey.BASE class (for prototypal inheritance)
 * - LAB.js (LABjs :: Loading And Blocking JavaScript) v2.0.3 (c) Kyle Simpson MIT License (dynamic script loader)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @link http://www.donrey.net/Documentation/Core/core.html
 * @license [GPL v3] http://www.gnu.org/licenses/gpl-3.0.html
 * @version 1.151010
 * @package CargoConnect
 * @subpackage JavaScriptCore
 */

/**
 * DonReY Singleton, provides some useful functions
 * Add application code inside this object ( donrey.application_name = {...} )
 */
(function(global){

	// Private
	var
	// Donrey smart-delay system
	delay_keys = {},

	// UniqId generator storage
	USED_IDS = {},

	// resource loader
	loaded_imgs,
	// events tracker
	events = {}, events_completed = 0, events_total = 0,
	events_on_update, events_on_completion,

	/**
	 * Adds a 0 in front of a positive number if less than 10. If the number is negative or greater than 99 it shows 00
	 */
	int_00_99 = function(val)
	{
		if(val < 0 || val > 99)
			return "00";
		if(val < 10)
			return "0" + val;
		else
			return val;
	},

	// all HTML4 entities as defined here: http://www.w3.org/TR/html4/sgml/entities.html
	// added: amp, lt, gt, quot and apos
	html_entity_table = {
		34 : 'quot',
		38 : 'amp',
		39 : 'apos',
		60 : 'lt',
		62 : 'gt',
		160 : 'nbsp',
		161 : 'iexcl',
		162 : 'cent',
		163 : 'pound',
		164 : 'curren',
		165 : 'yen',
		166 : 'brvbar',
		167 : 'sect',
		168 : 'uml',
		169 : 'copy',
		170 : 'ordf',
		171 : 'laquo',
		172 : 'not',
		173 : 'shy',
		174 : 'reg',
		175 : 'macr',
		176 : 'deg',
		177 : 'plusmn',
		178 : 'sup2',
		179 : 'sup3',
		180 : 'acute',
		181 : 'micro',
		182 : 'para',
		183 : 'middot',
		184 : 'cedil',
		185 : 'sup1',
		186 : 'ordm',
		187 : 'raquo',
		188 : 'frac14',
		189 : 'frac12',
		190 : 'frac34',
		191 : 'iquest',
		192 : 'Agrave',
		193 : 'Aacute',
		194 : 'Acirc',
		195 : 'Atilde',
		196 : 'Auml',
		197 : 'Aring',
		198 : 'AElig',
		199 : 'Ccedil',
		200 : 'Egrave',
		201 : 'Eacute',
		202 : 'Ecirc',
		203 : 'Euml',
		204 : 'Igrave',
		205 : 'Iacute',
		206 : 'Icirc',
		207 : 'Iuml',
		208 : 'ETH',
		209 : 'Ntilde',
		210 : 'Ograve',
		211 : 'Oacute',
		212 : 'Ocirc',
		213 : 'Otilde',
		214 : 'Ouml',
		215 : 'times',
		216 : 'Oslash',
		217 : 'Ugrave',
		218 : 'Uacute',
		219 : 'Ucirc',
		220 : 'Uuml',
		221 : 'Yacute',
		222 : 'THORN',
		223 : 'szlig',
		224 : 'agrave',
		225 : 'aacute',
		226 : 'acirc',
		227 : 'atilde',
		228 : 'auml',
		229 : 'aring',
		230 : 'aelig',
		231 : 'ccedil',
		232 : 'egrave',
		233 : 'eacute',
		234 : 'ecirc',
		235 : 'euml',
		236 : 'igrave',
		237 : 'iacute',
		238 : 'icirc',
		239 : 'iuml',
		240 : 'eth',
		241 : 'ntilde',
		242 : 'ograve',
		243 : 'oacute',
		244 : 'ocirc',
		245 : 'otilde',
		246 : 'ouml',
		247 : 'divide',
		248 : 'oslash',
		249 : 'ugrave',
		250 : 'uacute',
		251 : 'ucirc',
		252 : 'uuml',
		253 : 'yacute',
		254 : 'thorn',
		255 : 'yuml',
		402 : 'fnof',
		913 : 'Alpha',
		914 : 'Beta',
		915 : 'Gamma',
		916 : 'Delta',
		917 : 'Epsilon',
		918 : 'Zeta',
		919 : 'Eta',
		920 : 'Theta',
		921 : 'Iota',
		922 : 'Kappa',
		923 : 'Lambda',
		924 : 'Mu',
		925 : 'Nu',
		926 : 'Xi',
		927 : 'Omicron',
		928 : 'Pi',
		929 : 'Rho',
		931 : 'Sigma',
		932 : 'Tau',
		933 : 'Upsilon',
		934 : 'Phi',
		935 : 'Chi',
		936 : 'Psi',
		937 : 'Omega',
		945 : 'alpha',
		946 : 'beta',
		947 : 'gamma',
		948 : 'delta',
		949 : 'epsilon',
		950 : 'zeta',
		951 : 'eta',
		952 : 'theta',
		953 : 'iota',
		954 : 'kappa',
		955 : 'lambda',
		956 : 'mu',
		957 : 'nu',
		958 : 'xi',
		959 : 'omicron',
		960 : 'pi',
		961 : 'rho',
		962 : 'sigmaf',
		963 : 'sigma',
		964 : 'tau',
		965 : 'upsilon',
		966 : 'phi',
		967 : 'chi',
		968 : 'psi',
		969 : 'omega',
		977 : 'thetasym',
		978 : 'upsih',
		982 : 'piv',
		8226 : 'bull',
		8230 : 'hellip',
		8242 : 'prime',
		8243 : 'Prime',
		8254 : 'oline',
		8260 : 'frasl',
		8472 : 'weierp',
		8465 : 'image',
		8476 : 'real',
		8482 : 'trade',
		8501 : 'alefsym',
		8592 : 'larr',
		8593 : 'uarr',
		8594 : 'rarr',
		8595 : 'darr',
		8596 : 'harr',
		8629 : 'crarr',
		8656 : 'lArr',
		8657 : 'uArr',
		8658 : 'rArr',
		8659 : 'dArr',
		8660 : 'hArr',
		8704 : 'forall',
		8706 : 'part',
		8707 : 'exist',
		8709 : 'empty',
		8711 : 'nabla',
		8712 : 'isin',
		8713 : 'notin',
		8715 : 'ni',
		8719 : 'prod',
		8721 : 'sum',
		8722 : 'minus',
		8727 : 'lowast',
		8730 : 'radic',
		8733 : 'prop',
		8734 : 'infin',
		8736 : 'ang',
		8743 : 'and',
		8744 : 'or',
		8745 : 'cap',
		8746 : 'cup',
		8747 : 'int',
		8756 : 'there4',
		8764 : 'sim',
		8773 : 'cong',
		8776 : 'asymp',
		8800 : 'ne',
		8801 : 'equiv',
		8804 : 'le',
		8805 : 'ge',
		8834 : 'sub',
		8835 : 'sup',
		8836 : 'nsub',
		8838 : 'sube',
		8839 : 'supe',
		8853 : 'oplus',
		8855 : 'otimes',
		8869 : 'perp',
		8901 : 'sdot',
		8968 : 'lceil',
		8969 : 'rceil',
		8970 : 'lfloor',
		8971 : 'rfloor',
		9001 : 'lang',
		9002 : 'rang',
		9674 : 'loz',
		9824 : 'spades',
		9827 : 'clubs',
		9829 : 'hearts',
		9830 : 'diams',
		338 : 'OElig',
		339 : 'oelig',
		352 : 'Scaron',
		353 : 'scaron',
		376 : 'Yuml',
		710 : 'circ',
		732 : 'tilde',
		8194 : 'ensp',
		8195 : 'emsp',
		8201 : 'thinsp',
		8204 : 'zwnj',
		8205 : 'zwj',
		8206 : 'lrm',
		8207 : 'rlm',
		8211 : 'ndash',
		8212 : 'mdash',
		8216 : 'lsquo',
		8217 : 'rsquo',
		8218 : 'sbquo',
		8220 : 'ldquo',
		8221 : 'rdquo',
		8222 : 'bdquo',
		8224 : 'dagger',
		8225 : 'Dagger',
		8240 : 'permil',
		8249 : 'lsaquo',
		8250 : 'rsaquo',
		8364 : 'euro'
	}
	;

	/**
	 * Very Simple JavaScript Template System
	 * Added to the String object, so any string can be templated using the "data"
	 *
	 * @author Alin Voiculescu | wavetrex@gmail.com
	 *
	 * @param data The object data that is inserted into the string
	 * @return string
	 */
	String.prototype.present = function (data) {

		/* p = position */
		/* d = depth */
		var p = 0, d = 0, z,
		chr, str = this,

		/**
		 * Searches for a variable in the object "r" by it's name, using a dot.syntax in a string
		 * Example:
		 * r = {
		 *	dot: {
		 *		syntax: "some value"
		 *	}
		 * }
		 * when vn = "dot.syntax", the function will return "some value"
		 */
		srchvar = function(vn, r, wnf) // wnf = when not found (alternative to missing variables)
		{
			if(typeof wnf === 'undefined')
				wnf = '';

			if(!r) // when data is empty, return the alternative
				return wnf;

			var p = vn.split('.'), c=0;
			for(; c<p.length; c++) {
				if(!r) // break when r is missing or empty
					return wnf;

				if(typeof r[p[c]] === "undefined") // break the loop when variable is missing in current container
					return wnf;
				else
					r = r[p[c]];
			}
			return r;
		},

		/**
		 * Scans the {interior} of a pair of brackets, and returns the proper data for that pair
		 */
		parsepair = function (start,stop) {

			var res, j={}, key = null, keyn, varn, ret='', kw='',
			limitkw = function(limit) {
				for(var y in kw) {
					if(y>limit) {
						kw[limit]+=':' + kw[y];
						delete kw[y];
					}
				}
			};

			// special keyword follows
			if(str.charAt(start) == '#') {

				kw = str.substr(start+1,stop-start-1).split(':');

				switch(kw[0]) {
					// "loop" - repeats the content depending on an array of values
					case 'loop':
						limitkw(4); // 0 = "loop", 1 = var, 2 = key-var, 3 = value-var, 4 = subtemplate
						// search the var in kw[1]
						j = srchvar(kw[1],data); // j = data in the found var | variant: '{'+kw[1]+'}' = show marker {} instead of empty ''
						if(!j)
							return '';

						keyn = kw[2]; varn = kw[3];
						for(key in j) {
							data[keyn]=key;
							data[varn]=j[key];
							ret += kw[4].present(data); // recursively call present for loop content
						}
						delete data[keyn], delete data[varn]; // cleanup after exiting the loop
						return ret;
					// "exists" - if variable exists, the inside is displayed, if not it just returns nothing
					case 'exists':
						limitkw(2);
						// search the var in kw[1]
						j = srchvar(kw[1],data);

						if(j) // if succeeds, var exists
							return kw[2].present(data);
						else
							return '';
					// "missing" - the reverse of exists, to display content when a variable is missing and hide content when it exists
					case 'missing':
						limitkw(2);
						// search the var in kw[1]
						j = srchvar(kw[1],data);

						if(j) // if succeeds, var exists
							return '';
						else
							return kw[2].present(data);

	/* // Disabled for now - need some rethinking
					// "section" - saves a fragment of the template for later use
					case 'section':
						limitkw(2);

						key = kw[1];
						storage.tpl_cache[key]=kw[2];

						return kw[2].present(data);
	*/
					// TODO: Other features of the template engine
				}

			} else if(str.charAt(start) == '$') {
				// simple variable replace (with optional default value)
				kw = str.substr(start+1,stop-start-1).split(':');
				limitkw(2);
				if(typeof kw[1] == 'undefined')
					kw[1] = '';

				res = srchvar(kw[0],data,kw[1]);
				return typeof res === 'string' || typeof res === 'number' ? res : kw[1];
			} else if(str.charAt(start) == '!') {
				// comment, ignored
				return '';
			} else {
				// not recognized, display as it is
				return '{'+str.substr(start,stop-start)+'}';
			}
		},

		/**
		 * Searches the paired bracket } for the one at position P
		 */
		searchpair = function () {

			var pf = p, chr, df = d + 1;

			/* keep searching while increasing df by one for each { found, and decreasing for each } found */
			do {
				chr = str.charAt(pf++);
				if (chr == '{') {
					df = df + 1;
				}
				if (chr == '}') {
					df = df - 1;
				}

				if (df == d) {
					break;
				}
			} while(pf<str.length);

			return pf-1;
		},

		ret = '';

		// scans for { character inside string ( this )
		do {
			chr = str.charAt(p++);
			if(chr == '{') {
				z = searchpair ();
				ret += parsepair (p,z);
				p = z+1;
			} else {
				ret += chr;
			}
		} while(p<this.length);

		return ret;
	};

	/**
	 * Truncate a string under a max length while keeping words intact, optionally adding a suffix
	 * @returns string
	 */
	String.prototype.truncate = function(maxLength, useWordBoundary, suffix)
	{
		if(!suffix)
			suffix = '&hellip;';
		var toLong = this.length > maxLength,
			s_ = toLong ? this.substr(0, maxLength-1) : this;
		if(useWordBoundary && toLong)
			s_ = s_.substr(0,s_.lastIndexOf(' '));
		return toLong ? s_ + suffix : s_;
	};

	String.prototype.escapeHtmlEntities = function()
	{
		return this.replace(/[\u00A0-\u2666<>\&]/g, function(c) {
			return '&'+ (html_entity_table[c.charCodeAt(0)] || '#'+c.charCodeAt(0)) + ';';
		});
	};

	/**
	 * Transforms a string so that newlines are converted to <br />'s
	 * @param is_xhtml
	 * @returns string
	 */
	String.prototype.nl2br = function(is_xhtml)
	{
		var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
		return (this.escapeHtmlEntities() + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
	};

	/**
	 * Capitalize first letter of each word in a string
	 * @returns string
	 */
	String.prototype.capitalize = function()
	{
		return this.replace(/(?:^|\s)\S/g, function(f) { return f.toUpperCase(); });
	};

	/**
	 * Some simple global functions, which are not actually part of DonReY
	 * - Injected into the "global" object (usually "window")
	 */

	/**
	 * Debug Dump: browser console call wrapper (for debugging)
	 *
	 * @param o Object to dump
	 * @param cmd Mode (defaults to "log")
	 * @return
	 */
	global.dd = function(o, cmd)
	{
		// defaults to "log"
		if(typeof cmd === "undefined")
			cmd = "log";

		if(donrey.CFG.debug && typeof console !== 'undefined' && console[cmd]) {
			console[cmd](o);
		}
	};

	/**
	 * Clones an object (so modifications won't affect the original)
	 */
	global.clone = function(obj)
	{
		if (obj == null || typeof obj != "object") return obj;
		var copy = obj.constructor(), key = null;
		for (key in obj) {
			if (obj.hasOwnProperty(key)) copy[key] = obj[key];
		}
		return copy;
	};

	/**
	 * Counts elements of an object or array
	 */
	global.sizeof = function(obj)
	{
		if (obj == null || typeof obj != "object")
			return null; // not an object, doesn't have a size
		if (typeof obj.length == 'function')
			return obj.length;
		var c = 0, key = null;
		for (key in obj) {
			if (obj.hasOwnProperty(key))
				c++;
		}
		return c;
	};

	/**
	 * Converts an object to a js array (ignores keys)
	 */
	global.object_to_array = function(obj) {
		var arr = new Array();
		for(var key in obj)
			if (obj.hasOwnProperty(key))
				arr.push(obj[key]);
		return arr;
	};

	/**
	 * Compares two arrays if they are identical
	 */
	global.array_Compare = function(a1,a2)
	{
		var i = 0;
		if (a1.length != a2.length) return false;
		for (i = 0; i < a2.length; i++) {
			if (a1[i].compare) {
				if (!a1[i].compare(a2[i])) return false;
			}
			if (a1[i] !== a2[i]) return false;
		}
		return true;
	};

	/**
	 * PHPJS array_merge()
	 * @see http://phpjs.org/functions/array_merge/
	 *
	 * http://kevin.vanzonneveld.net
	 * original by: Brett Zamir (http://brett-zamir.me)
	 * bugfixed by: Nate
	 * input by: josh
	 * bugfixed by: Brett Zamir (http://brett-zamir.me)
	 * - example 1: arr1 = {"color": "red", 0: 2, 1: 4}
	 * - example 1: arr2 = {0: "a", 1: "b", "color": "green", "shape": "trapezoid", 2: 4}
	 * - example 1: array_merge(arr1, arr2)
	 * -- returns 1: {"color": "green", 0: 2, 1: 4, 2: "a", 3: "b", "shape": "trapezoid", 4: 4}
	 * - example 2: arr1 = []
	 * - example 2: arr2 = {1: "data"}
	 * - example 2: array_merge(arr1, arr2)
	 * -- returns 2: {0: "data"}
	 */
	global.array_Merge = function()
	{
		var args = Array.prototype.slice.call(arguments),
			argl = args.length,	arg,
			retObj = {},
			k = '', argil = 0,
			j = 0, i = 0, ct = 0,
			toStr = Object.prototype.toString,
			retArr = true;

		for (i = 0; i < argl; i++) {
			if (toStr.call(args[i]) !== '[object Array]') {
				retArr = false;
				break;
			}
		}

		if (retArr) {
			retArr = [];
			for (i = 0; i < argl; i++) {
				retArr = retArr.concat(args[i]);
			}
			return retArr;
		}

		for (i = 0, ct = 0; i < argl; i++) {
			arg = args[i];
			if (toStr.call(arg) === '[object Array]') {
				for (j = 0, argil = arg.length; j < argil; j++) {
					retObj[ct++] = arg[j];
				}
			} else {
				for (k in arg) {
					if (arg.hasOwnProperty(k)) {
						if (parseInt(k, 10) + '' === k) {
							retObj[ct++] = arg[k];
						} else {
							retObj[k] = arg[k];
						}
					}
				}
			}
		}
		return retObj;
	};

	/**
	 * PHPJS array_merge_recursive()
	 * @see http://phpjs.org/functions/array_merge_recursive/
	 *
 	 * @ http://kevin.vanzonneveld.net
	 * original by: Subhasis Deb
	 * input by: Brett Zamir (http://brett-zamir.me)
	 * bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
	 * depends on: array_merge
	 * - example 1: arr1 = {'color': {'favourite': 'read'}, 0: 5}
	 * - example 1: arr2 = {0: 10, 'color': {'favorite': 'green', 0: 'blue'}}
	 * - example 1: array_merge_recursive(arr1, arr2)
	 * -- returns 1: {'color': {'favorite': {0: 'red', 1: 'green'}, 0: 'blue'}, 1: 5, 1: 10}
	 */
	global.array_Merge_Recursive = function(arr1, arr2)
	{
		var idx = '';

		if (arr1 && Object.prototype.toString.call(arr1) === '[object Array]' && arr2 && Object.prototype.toString.call(arr2) === '[object Array]') {
			for (idx in arr2) {
				arr1.push(arr2[idx]);
			}
		} else
			if ((arr1 && (arr1 instanceof Object)) && (arr2 && (arr2 instanceof Object))) {
				for (idx in arr2) {
					if (idx in arr1) {
						if (typeof arr1[idx] == 'object' && typeof arr2 == 'object') {
							arr1[idx] = global.array_Merge(arr1[idx], arr2[idx]);
						} else {
							arr1[idx] = arr2[idx];
						}
					} else {
						arr1[idx] = arr2[idx];
					}
				}
			}

		return arr1;
	};

	/**
	 * PHPJS number_format()
	 * @see http://phpjs.org/functions/number_format/
	 *
	 * @example
	 * example 1: number_format(1234.56);
	 *  returns 1: '1,235'
	 * example 2: number_format(1234.56, 2, ',', ' ');
	 *  returns 2: '1 234,56'
	 * example 3: number_format(1234.5678, 2, '.', '');
	 *  returns 3: '1234.57'
	 * example 4: number_format(67, 2, ',', '.');
	 *  returns 4: '67,00'
	 * example 5: number_format(1000);
	 *  returns 5: '1,000'
	 * example 6: number_format(67.311, 2);
	 *  returns 6: '67.31'
	 * example 7: number_format(1000.55, 1);
	 *  returns 7: '1,000.6'
	 * example 8: number_format(67000, 5, ',', '.');
	 *  returns 8: '67.000,00000'
	 * example 9: number_format(0.9, 0);
	 *  returns 9: '1'
	 * example 10: number_format('1.20', 2);
	 *  returns 10: '1.20'
	 * example 11: number_format('1.20', 4);
	 *  returns 11: '1.2000'
	 * example 12: number_format('1.2000', 3);
	 *  returns 12: '1.200'
	 * example 13: number_format('1 000,50', 2, '.', ' ');
	 *  returns 13: '100 050.00'
	 * example 14: number_format(1e-8, 8, '.', '');
	 *  returns 14: '0.00000001'
	 */
	global.number_format = function(number, decimals, dec_point, thousands_sep)
	{
		number = number.toString().replace(/[^0-9+\-Ee.]/g, '');
		var n = !isFinite(+number) ? 0 : +number,
			prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
			sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
			dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
			s = '',
			toFixedFix = function(n, prec) {
				var k = Math.pow(10, prec);
				return (Math.round(n * k) / k).toFixed(prec).toString();
			};
		// Fix for IE parseFloat(0.55).toFixed(0) = 0;
		s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
		if (s[0].length > 3) {
			s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
		}
		if ((s[1] || '').length < prec) {
			s[1] = s[1] || '';
			s[1] += new Array(prec - s[1].length + 1).join('0');
		}
		return s.join(dec);
	};

	/**
	 * UTF-8 Encoding and Decoding (necessary for functions like btoa/atob, or hash functions - which only support 8-bit strings)
	 */
	global.utf8 = {
		encode: function(s) { return unescape( encodeURIComponent( s ) ); },
		decode: function(s) { return decodeURIComponent( escape( s ) ); }
	};

	/**
	 * Emulates the missing btoa / atob functions what do not exist in IE ( base64 encoding / decoding ), and maybe other browsers
	 * @return String
	 */
	var _keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
	global.btoa = global.btoa || function (input)
	{
		var output = "",
			chr1, chr2, chr3, enc1, enc2, enc3, enc4,
			i = 0;
		while (i < input.length) {

			chr1 = input.charCodeAt(i++);
			chr2 = input.charCodeAt(i++);
			chr3 = input.charCodeAt(i++);

			enc1 = chr1 >> 2;
			enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
			enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
			enc4 = chr3 & 63;

			if (isNaN(chr2)) {
				enc3 = enc4 = 64;
			} else if (isNaN(chr3)) {
				enc4 = 64;
			}

			output+= _keyStr.charAt(enc1) + _keyStr.charAt(enc2) +	_keyStr.charAt(enc3) + _keyStr.charAt(enc4);
		}
		return output;
	};

	/**
	 * @return String
	 */
	global.atob = global.atob || function (input)
	{
		var output = "",
			chr1, chr2, chr3,
			enc1, enc2, enc3, enc4,
			i = 0;

		input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");

		while (i < input.length) {
			enc1 = _keyStr.indexOf(input.charAt(i++));
			enc2 = _keyStr.indexOf(input.charAt(i++));
			enc3 = _keyStr.indexOf(input.charAt(i++));
			enc4 = _keyStr.indexOf(input.charAt(i++));

			chr1 = (enc1 << 2) | (enc2 >> 4);
			chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
			chr3 = ((enc3 & 3) << 6) | enc4;

			output = output + String.fromCharCode(chr1);

			if (enc3 != 64) {
				output+= String.fromCharCode(chr2);
			}
			if (enc4 != 64) {
				output+= String.fromCharCode(chr3);
			}
		}
		return output;
	};

	/**
	 * Little regular-expression function that validates an e-mail addresses directly in JS
	 * @param email The e-mail address that needs validation
	 * @param extract If TRUE, this changes the behaviour of the method - Instead of just tesing, it attempts to extract (return) an e-mail address out of the text, if one is detected. The text might contain other characters
	 */
	global.validate_email = function(email, extract)
	{
		if(extract) {
			var parsed = /(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,9}))/.exec(email);
			if(parsed)
				return parsed[0];
			else
				return false;
		} else
			return /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,9}))$/.test(email);
//		return /^([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x22([^\x0d\x22\x5c\x80-\xff]|\x5c[\x00-\x7f])*\x22)(\x2e([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x22([^\x0d\x22\x5c\x80-\xff]|\x5c[\x00-\x7f])*\x22))*\x40([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x5b([^\x0d\x5b-\x5d\x80-\xff]|\x5c[\x00-\x7f])*\x5d)(\x2e([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x5b([^\x0d\x5b-\x5d\x80-\xff]|\x5c[\x00-\x7f])*\x5d))*$/.test(email);
	};

	// Priviledged functions ( Exposed, but can access private )
	global.donrey = {
		/**
		 * DonReY Frontend Initialization
		 */
		init: function()
		{
			if(typeof donrey.CFG.debug === 'undefined')
				donrey.CFG.debug = false;
			if(typeof donrey.CFG.debugLAB === 'undefined')
				donrey.CFG.debugLAB = false;
			donrey.LAB.setGlobalDefaults({
				AlwaysPreserveOrder: donrey.CFG.preserve_order||true,
				Debug: donrey.CFG.debugLAB
			});

			// Setup all default paths
			if(!donrey.CFG.paths)
				donrey.CFG.paths = {};

			if(!donrey.CFG.paths.modules_debug)
				donrey.CFG.paths.modules_debug = "/js/modules/";
			if(!donrey.CFG.paths.modules_min)
				donrey.CFG.paths.modules_min = "/js/modules.min/";
			donrey.CFG.paths.modules = donrey.CFG.minified? donrey.CFG.paths.modules_min: donrey.CFG.paths.modules_debug;

			if(!donrey.CFG.paths.components_debug)
				donrey.CFG.paths.components_debug = "/js/components/";
			if(!donrey.CFG.paths.components_min)
				donrey.CFG.paths.components_min = "/js/components.min/";
			donrey.CFG.paths.components = donrey.CFG.minified? donrey.CFG.paths.components_min: donrey.CFG.paths.components_debug;

			// Done, clear the preloader variable.
			delete preloader;
		},

		/**
		 * Dynamically loads CSS (use only for styles which are not directly visible on page)
		 */
		load_CSS: function(styles_url)
		{
			var x = document.createElement("link"), w = document.getElementsByTagName("head")[0];
			x.setAttribute("rel", "stylesheet");
			x.setAttribute("type", "text/css");
			x.setAttribute("href", styles_url);
			w.appendChild(x);
		},

		/**
		 * Loads one or more reusable compoennts from donrey.CFG.paths.components (.js files)
		 * @param string component_names
		 * @param callback callback Function which is called when the components finished loading (optional)
		 * @param qs query string - Append this after the JS file names
		 */
		load_Components: function(component_names, callback, qs)
		{
			var _basepath = null;
			if(!qs)
				qs = "";
			if (component_names instanceof Array) {
				// in case an empty module list was specified,
				if(component_names.length == 0) {
					if(callback instanceof Function)
						callback();
					return;
				}
				for(var c = 0; c<component_names.length; c++) {
					if(component_names[c].substr(0,4) == 'min:') {
						component_names[c] = component_names[c].substr(4);
						_basepath = donrey.CFG.paths.components_min;
					} else if(component_names[c].substr(0,4) == 'debug:') {
						component_names[c] = component_names[c].substr(6);
						_basepath = donrey.CFG.paths.components_debug;
					} else
						_basepath = donrey.CFG.paths.components;

					donrey.LAB.queueScript(_basepath + component_names[c] + (donrey.CFG.minified? ".min.js": ".js") + qs);
				}
			} else {
				if(component_names.substr(0,4) == 'min:') {
					component_names = component_names.substr(4);
					_basepath = donrey.CFG.paths.components_min;
				} else if(component_names.substr(0,6) == 'debug:') {
					component_names = component_names.substr(6);
					_basepath = donrey.CFG.paths.components_debug;
				} else
					_basepath = donrey.CFG.paths.components;

				donrey.LAB.queueScript(_basepath + component_names + (donrey.CFG.minified? ".min.js": ".js") + qs);
			}
			donrey.LAB.queueWait(callback);
			donrey.LAB.runQueue();
		},

		/**
		 * Loads one or more application modules from donrey.CFG.paths.modules (.js files)
		 *
		 * @param string|Array module_names Name of one module or an array with all modules that should be loaded . If a name is prefixed by "min:", the minified version is loaded instead ( from donrey.CFG.paths.modules_min )
		 * @param callback callback Function which is called when module has finished loading (optional)
		 * @param qs query string - Append this after the JS and CSS file names
		 */
		load_Modules: function(module_names, callback, qs)
		{
			var _basepath = null;
			if(!qs)
				qs = "";

			if (module_names instanceof Array) {
				// in case an empty module list was specified,
				if(module_names.length == 0) {
					if(callback instanceof Function)
						callback();
					return;
				}
				for(var c = 0; c<module_names.length; c++) {
					if(module_names[c].substr(0,4) == 'min:') {
						module_names[c] = module_names[c].substr(4);
						_basepath = donrey.CFG.paths.modules_min;
					} else if(module_names[c].substr(0,4) == 'debug:') {
						module_names[c] = module_names[c].substr(6);
						_basepath = donrey.CFG.paths.modules_debug;
					} else
						_basepath = donrey.CFG.paths.modules;

					donrey.LAB.queueScript(_basepath + module_names[c] + (donrey.CFG.minified? ".min.js": ".js") + qs);
					// css associated with each module (optional, but it will return a 404 error if one of the styles do not exist, as there is no way to disable individual styles)
					if(donrey.CFG.paths.modules_css)
						donrey.load_CSS(donrey.CFG.paths.modules_css + module_names[c] + ".css" + qs);
				}
			} else {
				if(module_names.substr(0,4) == 'min:') {
					module_names = module_names.substr(4);
					_basepath = donrey.CFG.paths.modules_min;
				} else if(module_names.substr(0,6) == 'debug:') {
					module_names = module_names.substr(6);
					_basepath = donrey.CFG.paths.modules_debug;
				} else
					_basepath = donrey.CFG.paths.modules;

				donrey.LAB.queueScript(_basepath + module_names + (donrey.CFG.minified? ".min.js": ".js") + qs);
				if(donrey.CFG.paths.modules_css)
					donrey.load_CSS(donrey.CFG.paths.modules_css + module_names + ".css" + qs);
			}
			donrey.LAB.queueWait(callback);
			donrey.LAB.runQueue();
		},

		/**
		 * Maintains the delay of execution of a function for as long this method keeps getting called
		 * @example An application would need to do an operation a few moments after the mouse stops moving, but never while it's still moving:
		 *   By calling donrey.final_Delay on each mousemove event, this can be achieved
		 *
		 * If final_Delay is called with the same "key" before the timeout has expired,
		 *   the previous timeout is cleared and a new one is created ( delay the func even further )
		 * All subsequent calls to final_Delay might omit the function, delay or arguments - They are stored in the first call
		 *
		 * @param string key An identifier for that particular delay
		 * @param callback func Function to be called when timeout expires
		 * @param delay Delay in miliseconds (parameter for setTimeout)
		 * @param mixed func_arg Argument to be sent to the callback (optional) - usually an array or object
		 */
		final_Delay: function(key,func,delay,func_arg)
		{
			// clear the old timeout if it exists with the same key
			if(delay_keys[key]) {
				clearTimeout(delay_keys[key].to_id);
			} else
				delay_keys[key]={};
			// create the timeout with the specified function
			if(func) {
				delay_keys[key].func = func;
				delay_keys[key].func_arg = func_arg;
			}
			if(delay) {
				delay_keys[key].delay = delay;
			}

			delay_keys[key].to_id=setTimeout(function() {
				// call the function if it was defined properly
				if(delay_keys[key].func && typeof delay_keys[key].func == 'function')
					delay_keys[key].func(delay_keys[key].func_arg);
				// and clears the key after function has completed it's task
				delete delay_keys[key];
			},delay_keys[key].delay);
		},

		/**
		 * Delays an event only once for a key, with the delay starting when the key is originally created, no matter how many times this function is called.
		 * Basically, while final_Delay performs the action after the last call, single_Delay performs it after the first call, ignoring all the rest
		 *
		 * @param string key An identifier for that particular delay
		 * @param callback func Function to be called when timeout expires
		 * @param delay Delay in miliseconds (parameter for setTimeout)
		 * @param mixed func_arg Argument to be sent to the callback (optional) - usually an array or object
		 */
		single_Delay: function(key,func,delay,func_arg)
		{
			if(delay_keys[key])
				return;
			else
				delay_keys[key]={};
			// create the timeout with the specified function
			if(func) {
				delay_keys[key].func = func;
				delay_keys[key].func_arg = func_arg;
			}
			if(delay) {
				delay_keys[key].delay = delay;
			}

			delay_keys[key].to_id=setTimeout(function() {
				// call the function if it was defined properly
				if(delay_keys[key].func && typeof delay_keys[key].func == 'function')
					delay_keys[key].func(delay_keys[key].func_arg);
				// and clears the key after function has completed it's task
				delete delay_keys[key];
			},delay_keys[key].delay);
		},

		/**
		 * Cancels a pending execution of a function, by specifying it's key
		 *
		 * @param key An identifier for that particular delay
		 * @param keep If true, keep stored data for that key, to allow another call to final_Delay(), without specifying the function. Not compatible with single_Delay
		 */
		cancel_Delay: function(key,keep)
		{
			if(delay_keys[key]) {
				clearTimeout(delay_keys[key].to_id);
				if(!keep)
					delete delay_keys[key];
			}
		},

		/**
		 * Loads multiple image resouces (specified in the image_list) object, and returns the object with Image() resources loaded once all have been completed
		 *
		 * @param Object image_list
		 * @param callback on_all_loaded
		 * @param int max_wait_time Maximum numbers of ms to wait for images to load
		 */
		load_ImageResources: function(image_list, on_completion, max_wait_time)
		{
			loaded_imgs = 0;

			var image_count = 0, res_uri, load_Timeout = setTimeout(function() {
				on_completion(image_list, false, loaded_imgs);
			}, max_wait_time);
			for(var idx in image_list) {
				res_uri = image_list[idx];
				image_list[idx] = new Image();
				image_list[idx].src = res_uri;
				image_list[idx].onload = function() {
					loaded_imgs ++;
					// all finished
					if(loaded_imgs == image_count) {
						clearTimeout(load_Timeout);
						on_completion(image_list, true, image_count);
					}
				};
				image_count++;
			}
		},

		/**
		 * Initializes the event tracker
		 * @param Array event_list A list of named events which should happen some time in the future
		 * @param callback on_update Called every time the tracker gets a signal that one of the events has completed
		 * @param callback on_completion Called when all events in the list have been signaled
		 * @param int max_wait_time Maximum time to wait for events to be signaled
		 */
		eventTracker_Init: function(event_list, on_update, on_completion, max_wait_time)
		{
			events = {}; events_completed = 0; events_total = event_list.length;

			setTimeout(function() {
				if(events_completed < events_total)
					on_completion(false, events_completed, new Date().getTime());
			}, max_wait_time);
			for(var c = 0; c< event_list.length; c++) {
				events[event_list[c]] = false; // not yet completed
			}
			events_on_update = on_update;
			events_on_completion = on_completion;
		},

		/**
		 * Signals that one of the named events has occured first time
		 * @param string event_id
		 */
		eventTracker_Signal: function(event_id)
		{
			if(!events)
				return;
			// only first signaling is considered, if the same event is signaled again, nothing more happens
			if(events[event_id] == false) {
				events[event_id] = true;
				events_completed++;
				events_on_update(event_id, events_completed, new Date().getTime());
				if(events_completed == events_total)
					events_on_completion(true, events_total, new Date().getTime());
			}
		},

		/**
		 * Generates a random ID which can be used as DOM element ID. Makes sure no ID used twice
		 * @param string charset List of characters used by the generator (defaults to :alnum:)
		 * @param int len Length of the ID's being created (Defaults to 8)
		 * @param bool isNotInDOM Checks that the new generated ID is not already used in DOM
		 * @return String The unique ID
		 */
		generate_UniqId: function(charset, len, isNotInDOM)
		{
			if (!charset) {charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';}
			if (!len) {len = 8;}
			var i = 0, id = '', charsetlen = charset.length, charIndex, found = false;

			do {
				// iterate on the length and get a random character for each position
				for (i = 0; i < len; i++) {
					charIndex = Math.random() * charsetlen;
					id += charset.charAt(charIndex);
				}
				if (!USED_IDS[id])
					found = true;
				if(isNotInDOM && document.getElementById(id))
					found = false; // keep trying, DOM already uses this ID (freak coincidence maybe, or the charset too small?)
			} while(found==false);
			USED_IDS[id] = true;
			return id;
		},

		/**
		 * For Hybrid AJAX requests (which return both HTML and JSON), read the JSON part and integrate it into the target_var
		 * @param jqXHR The jQuery XHR object which contains the response headers
		 * @param target_var The object which will contain the read variables
		 */
		read_JSON_Headers: function(jqXHR, target_var)
		{
			// Try... Catch because parseJSON might fail
			try {
				var c, meta = $.parseJSON(jqXHR.getResponseHeader('X-JSON-Meta')), json_header, parsed_header, loc_type = null;
				if(!meta)
					return false; // error, meta not found or malformed
				for(c = 0; c< meta.count; c++) {
					json_header = jqXHR.getResponseHeader('X-JSON-'+c);
					if(json_header.length == meta.lengths[c]) {
						// Ok, we have a valid header (not trimmed), attempt to decode
						parsed_header = $.parseJSON(json_header);
						// Scalar or Array(Object) ?
						if(typeof parsed_header == "string" || typeof parsed_header == "number" || typeof parsed_header == "boolean")
							target_var[meta.keys[c]] = parsed_header;
						else {
							// If location is undefined or an array, keep as array, if not let it become an object
							loc_type = Object.prototype.toString.call(target_var[meta.keys[c]]);
							if(loc_type === '[object Array]' || loc_type === '[object Undefined]')
								target_var[meta.keys[c]] = [];

							target_var[meta.keys[c]] = global.array_Merge(target_var[meta.keys[c]], parsed_header);
						}
					}
				}
			} catch(e) {
				return false; // error, some JSON is bad
			}
		},

		/**
		 * Returns a string containing current or specified time formatted as HH:mm:ss
		 * @param time
		 */
		get_Time_Hmmss: function(date)
		{
			if(!date || date===true)
				date = new Date();

			var h = date.getHours(), m = int_00_99(date.getMinutes()), s = int_00_99(date.getSeconds());

			return h + ":" + m + ":" + s;
		},

		/**
		 * Converts a number into letters for sending in an url [0123456789] => [wryipouteq]
		 * - The reverse way: [qwertyuiop] => [9081726354]
		 * This is a low-yield obfuscation of numberic IDs for files; TODO: Pick better letters..
		 * @param number
		 */
		qp_conv: function(number)
		{
			var	conv = {'0':'w','1':'r','2':'y','3':'i','4':'p','5':'o','6':'u','7':'t','8':'e','9':'q'},
				number = String(number), c, ret = "";
			for(c=number.length-1; c>=0; c--)
				ret+=conv[number[c]];
			return ret;
		}
	};


})(this);

/**
 * DonReY Default Configuration (Public)
 * - Add configs for the application in this object
 */
donrey.CFG = {
	debug: false,
};

/**
 * DonReY Base Object (Public), contains functions to help with prototypal JavaScript inheritance
 *
 * @example new_object = donrey.BASE._extend({ ... the extended ... });
 *   Since it inherits from BASE, the extend object can also be extended itself
 */
donrey.BASE = {
	// adds public/priviledged members to the object just created ( when extending )
	_public: function(o)
	{
		if(typeof o == 'object') {
			for(var i in o)
				this[i]=o[i];
		}
	},
	// extends the object by adding more members
	_extend: function(F, i)
	{
		F = F || function() {};
		F.prototype = this;
		var o = new F();
		// call constructor
		o._construct(i);
		return o;
	},
	// default (empty) constructor, overwrite this with specialized constructors
	_construct: function() {}
};

// External libraries integrated in the framework //

/*! LAB.js (LABjs :: Loading And Blocking JavaScript)
v2.0.3 (c) Kyle Simpson
MIT License
*/
(function(global){
var _$LAB = global.$LAB,

	// constants for the valid keys of the options object
	_UseLocalXHR = "UseLocalXHR",
	_AlwaysPreserveOrder = "AlwaysPreserveOrder",
	_AllowDuplicates = "AllowDuplicates",
	_CacheBust = "CacheBust",
	/*!START_DEBUG*/_Debug = "Debug",/*!END_DEBUG*/
	_BasePath = "BasePath",

	// stateless variables used across all $LAB instances
	root_page = /^[^?#]*\//.exec(location.href)[0],
	root_domain = /^\w+\:\/\/\/?[^\/]+/.exec(root_page)[0],
	append_to = document.head || document.getElementsByTagName("head"),

	// inferences... ick, but still necessary
	opera_or_gecko = (global.opera && Object.prototype.toString.call(global.opera) == "[object Opera]") || ("MozAppearance" in document.documentElement.style),

/*!START_DEBUG*/
	// console.log() and console.error() wrappers
	log_msg = function(){},
	log_error = log_msg,
/*!END_DEBUG*/

	// feature sniffs (yay!)
	test_script_elem = document.createElement("script"),
	explicit_preloading = typeof test_script_elem.preload == "boolean", // http://wiki.whatwg.org/wiki/Script_Execution_Control#Proposal_1_.28Nicholas_Zakas.29
	real_preloading = explicit_preloading || (test_script_elem.readyState && test_script_elem.readyState == "uninitialized"), // will a script preload with `src` set before DOM append?
	script_ordered_async = !real_preloading && test_script_elem.async === true, // http://wiki.whatwg.org/wiki/Dynamic_Script_Execution_Order

	// XHR preloading (same-domain) and cache-preloading (remote-domain) are the fallbacks (for some browsers)
	xhr_or_cache_preloading = !real_preloading && !script_ordered_async && !opera_or_gecko
;

/*!START_DEBUG*/
// define console wrapper functions if applicable
if (global.console && global.console.log) {
	if (!global.console.error) global.console.error = global.console.log;
	log_msg = function(msg) { global.console.log(msg); };
	log_error = function(msg,err) { global.console.error(msg,err); };
}
/*!END_DEBUG*/

// test for function
function is_func(func) { return Object.prototype.toString.call(func) == "[object Function]"; }

// test for array
function is_array(arr) { return Object.prototype.toString.call(arr) == "[object Array]"; }

// make script URL absolute/canonical
function canonical_uri(src,base_path) {
	var absolute_regex = /^\w+\:\/\//;

	// is `src` is protocol-relative (begins with // or ///), prepend protocol
	if (/^\/\/\/?/.test(src)) {
		src = location.protocol + src;
	}
	// is `src` page-relative? (not an absolute URL, and not a domain-relative path, beginning with /)
	else if (!absolute_regex.test(src) && src.charAt(0) != "/") {
		// prepend `base_path`, if any
		src = (base_path || "") + src;
	}
	// make sure to return `src` as absolute
	return absolute_regex.test(src) ? src : ((src.charAt(0) == "/" ? root_domain : root_page) + src);
}

// merge `source` into `target`
function merge_objs(source,target) {
	for (var k in source) { if (source.hasOwnProperty(k)) {
		target[k] = source[k]; // TODO: does this need to be recursive for our purposes?
	}}
	return target;
}

// does the chain group have any ready-to-execute scripts?
function check_chain_group_scripts_ready(chain_group) {
	var any_scripts_ready = false;
	for (var i=0; i<chain_group.scripts.length; i++) {
		if (chain_group.scripts[i].ready && chain_group.scripts[i].exec_trigger) {
			any_scripts_ready = true;
			chain_group.scripts[i].exec_trigger();
			chain_group.scripts[i].exec_trigger = null;
		}
	}
	return any_scripts_ready;
}

// creates a script load listener
function create_script_load_listener(elem,registry_item,flag,onload) {
	elem.onload = elem.onreadystatechange = function() {
		if ((elem.readyState && elem.readyState != "complete" && elem.readyState != "loaded") || registry_item[flag]) return;
		elem.onload = elem.onreadystatechange = null;
		onload();
	};
}

// script executed handler
function script_executed(registry_item) {
	registry_item.ready = registry_item.finished = true;
	for (var i=0; i<registry_item.finished_listeners.length; i++) {
		registry_item.finished_listeners[i]();
	}
	registry_item.ready_listeners = [];
	registry_item.finished_listeners = [];
}

// make the request for a scriptha
function request_script(chain_opts,script_obj,registry_item,onload,preload_this_script) {
	// setTimeout() "yielding" prevents some weird race/crash conditions in older browsers
	setTimeout(function(){
		var script, src = script_obj.real_src, xhr;

		// don't proceed until `append_to` is ready to append to
		if ("item" in append_to) { // check if `append_to` ref is still a live node list
			if (!append_to[0]) { // `append_to` node not yet ready
				// try again in a little bit -- note: will re-call the anonymous function in the outer setTimeout, not the parent `request_script()`
				setTimeout(arguments.callee,25);
				return;
			}
			// reassign from live node list ref to pure node ref -- avoids nasty IE bug where changes to DOM invalidate live node lists
			append_to = append_to[0];
		}
		script = document.createElement("script");
		if (script_obj.type) script.type = script_obj.type;
		if (script_obj.charset) script.charset = script_obj.charset;

		// should preloading be used for this script?
		if (preload_this_script) {
			// real script preloading?
			if (real_preloading) {
//				/*!START_DEBUG*/if (chain_opts[_Debug]) log_msg("start script preload: "+src);/*!END_DEBUG*/
				registry_item.elem = script;
				if (explicit_preloading) { // explicit preloading (aka, Zakas' proposal)
					script.preload = true;
					script.onpreload = onload;
				}
				else {
					script.onreadystatechange = function(){
						if (script.readyState == "loaded") onload();
					};
				}
				script.src = src;
				// NOTE: no append to DOM yet, appending will happen when ready to execute
			}
			// same-domain and XHR allowed? use XHR preloading
			else if (preload_this_script && src.indexOf(root_domain) == 0 && chain_opts[_UseLocalXHR]) {
				xhr = new XMLHttpRequest(); // note: IE never uses XHR (it supports true preloading), so no more need for ActiveXObject fallback for IE <= 7
//				/*!START_DEBUG*/if (chain_opts[_Debug]) log_msg("start script preload (xhr): "+src);/*!END_DEBUG*/
				xhr.onreadystatechange = function() {
					if (xhr.readyState == 4) {
						xhr.onreadystatechange = function(){}; // fix a memory leak in IE
						registry_item.text = xhr.responseText + "\n//@ sourceURL=" + src; // http://blog.getfirebug.com/2009/08/11/give-your-eval-a-name-with-sourceurl/
						onload();
					}
				};
				xhr.open("GET",src);
				xhr.send();
			}
			// as a last resort, use cache-preloading
			else {
//				/*!START_DEBUG*/if (chain_opts[_Debug]) log_msg("start script preload (cache): "+src);/*!END_DEBUG*/
				script.type = "text/cache-script";
				create_script_load_listener(script,registry_item,"ready",function() {
					append_to.removeChild(script);
					onload();
				});
				script.src = src;
				append_to.insertBefore(script,append_to.firstChild);
			}
		}
		// use async=false for ordered async? parallel-load-serial-execute http://wiki.whatwg.org/wiki/Dynamic_Script_Execution_Order
		else if (script_ordered_async) {
//			/*!START_DEBUG*/if (chain_opts[_Debug]) log_msg("start script load (ordered async): "+src);/*!END_DEBUG*/
			script.async = false;
			create_script_load_listener(script,registry_item,"finished",onload);
			script.src = src;
			append_to.insertBefore(script,append_to.firstChild);
		}
		// otherwise, just a normal script element
		else {
//			/*!START_DEBUG*/if (chain_opts[_Debug]) log_msg("start script load: "+src);/*!END_DEBUG*/
			create_script_load_listener(script,registry_item,"finished",onload);
			script.src = src;
			append_to.insertBefore(script,append_to.firstChild);
		}
	},1);
}

// create a clean instance of $LAB
function create_sandbox() {
	var global_defaults = {},
		can_use_preloading = real_preloading || xhr_or_cache_preloading,
		queue = [],
		registry = {},
		instanceAPI
	;

	// global defaults
	global_defaults[_UseLocalXHR] = true;
	global_defaults[_AlwaysPreserveOrder] = false;
	global_defaults[_AllowDuplicates] = false;
	global_defaults[_CacheBust] = false;
	/*!START_DEBUG*/global_defaults[_Debug] = false;/*!END_DEBUG*/
	global_defaults[_BasePath] = "";

	// execute a script that has been preloaded already
	function execute_preloaded_script(chain_opts,script_obj,registry_item) {
		var script;

		function preload_execute_finished() {
			if (script != null) { // make sure this only ever fires once
				script = null;
				script_executed(registry_item);
			}
		}

		if (registry[script_obj.src].finished) return;
		if (!chain_opts[_AllowDuplicates]) registry[script_obj.src].finished = true;

		script = registry_item.elem || document.createElement("script");
		if (script_obj.type) script.type = script_obj.type;
		if (script_obj.charset) script.charset = script_obj.charset;
		create_script_load_listener(script,registry_item,"finished",preload_execute_finished);

		// script elem was real-preloaded
		if (registry_item.elem) {
			registry_item.elem = null;
		}
		// script was XHR preloaded
		else if (registry_item.text) {
			script.onload = script.onreadystatechange = null;	// script injection doesn't fire these events
			script.text = registry_item.text;
		}
		// script was cache-preloaded
		else {
			script.src = script_obj.real_src;
		}
		append_to.insertBefore(script,append_to.firstChild);

		// manually fire execution callback for injected scripts, since events don't fire
		if (registry_item.text) {
			preload_execute_finished();
		}
	}

	// process the script request setup
	function do_script(chain_opts,script_obj,chain_group,preload_this_script) {
		var registry_item,
			registry_items,
			ready_cb = function(){ script_obj.ready_cb(script_obj,function(){ execute_preloaded_script(chain_opts,script_obj,registry_item); }); },
			finished_cb = function(){ script_obj.finished_cb(script_obj,chain_group); }
		;

		script_obj.src = canonical_uri(script_obj.src,chain_opts[_BasePath]);
		script_obj.real_src = script_obj.src +
			// append cache-bust param to URL?
			(chain_opts[_CacheBust] ? ((/\?.*$/.test(script_obj.src) ? "&_" : "?_") + ~~(Math.random()*1E9) + "=") : "")
		;

		if (!registry[script_obj.src]) registry[script_obj.src] = {items:[],finished:false};
		registry_items = registry[script_obj.src].items;

		// allowing duplicates, or is this the first recorded load of this script?
		if (chain_opts[_AllowDuplicates] || registry_items.length == 0) {
			registry_item = registry_items[registry_items.length] = {
				ready:false,
				finished:false,
				ready_listeners:[ready_cb],
				finished_listeners:[finished_cb]
			};

			request_script(chain_opts,script_obj,registry_item,
				// which callback type to pass?
				(
				 	(preload_this_script) ? // depends on script-preloading
					function(){
						registry_item.ready = true;
						for (var i=0; i<registry_item.ready_listeners.length; i++) {
							registry_item.ready_listeners[i]();
						}
						registry_item.ready_listeners = [];
					} :
					function(){ script_executed(registry_item); }
				),
				// signal if script-preloading should be used or not
				preload_this_script
			);
		}
		else {
			registry_item = registry_items[0];
			if (registry_item.finished) {
				finished_cb();
			}
			else {
				registry_item.finished_listeners.push(finished_cb);
			}
		}
	}

	// creates a closure for each separate chain spawned from this $LAB instance, to keep state cleanly separated between chains
	function create_chain() {
		var chainedAPI,
			chain_opts = merge_objs(global_defaults,{}),
			chain = [],
			exec_cursor = 0,
			scripts_currently_loading = false,
			group
		;

		// called when a script has finished preloading
		function chain_script_ready(script_obj,exec_trigger) {
//			/*!START_DEBUG*/if (chain_opts[_Debug]) log_msg("script preload finished: "+script_obj.real_src);/*!END_DEBUG*/
			script_obj.ready = true;
			script_obj.exec_trigger = exec_trigger;
			advance_exec_cursor(); // will only check for 'ready' scripts to be executed
		}

		// called when a script has finished executing
		function chain_script_executed(script_obj,chain_group) {
//			/*!START_DEBUG*/if (chain_opts[_Debug]) log_msg("script execution finished: "+script_obj.real_src);/*!END_DEBUG*/
			script_obj.ready = script_obj.finished = true;
			script_obj.exec_trigger = null;
			// check if chain group is all finished
			for (var i=0; i<chain_group.scripts.length; i++) {
				if (!chain_group.scripts[i].finished) return;
			}
			// chain_group is all finished if we get this far
			chain_group.finished = true;
			advance_exec_cursor();
		}

		// main driver for executing each part of the chain
		function advance_exec_cursor() {
			while (exec_cursor < chain.length) {
				if (is_func(chain[exec_cursor])) {
//					/*!START_DEBUG*/if (chain_opts[_Debug]) log_msg("$LAB.wait() executing: "+chain[exec_cursor]);/*!END_DEBUG*/
					try { chain[exec_cursor++](); } catch (err) {
						/*!START_DEBUG*/if (chain_opts[_Debug]) log_error("$LAB.wait() error caught: ",err);/*!END_DEBUG*/
					}
					continue;
				}
				else if (!chain[exec_cursor].finished) {
					if (check_chain_group_scripts_ready(chain[exec_cursor])) continue;
					break;
				}
				exec_cursor++;
			}
			// we've reached the end of the chain (so far)
			if (exec_cursor == chain.length) {
				scripts_currently_loading = false;
				group = false;
			}
		}

		// setup next chain script group
		function init_script_chain_group() {
			if (!group || !group.scripts) {
				chain.push(group = {scripts:[],finished:true});
			}
		}

		// API for $LAB chains
		chainedAPI = {
			// start loading one or more scripts
			script:function(){
				for (var i=0; i<arguments.length; i++) {
					(function(script_obj,script_list){
						var splice_args;

						if (!is_array(script_obj)) {
							script_list = [script_obj];
						}
						for (var j=0; j<script_list.length; j++) {
							init_script_chain_group();
							script_obj = script_list[j];

							if (is_func(script_obj)) script_obj = script_obj();
							if (!script_obj) continue;
							if (is_array(script_obj)) {
								// set up an array of arguments to pass to splice()
								splice_args = [].slice.call(script_obj); // first include the actual array elements we want to splice in
								splice_args.unshift(j,1); // next, put the `index` and `howMany` parameters onto the beginning of the splice-arguments array
								[].splice.apply(script_list,splice_args); // use the splice-arguments array as arguments for splice()
								j--; // adjust `j` to account for the loop's subsequent `j++`, so that the next loop iteration uses the same `j` index value
								continue;
							}
							if (typeof script_obj == "string") script_obj = {src:script_obj};
							script_obj = merge_objs(script_obj,{
								ready:false,
								ready_cb:chain_script_ready,
								finished:false,
								finished_cb:chain_script_executed
							});
							group.finished = false;
							group.scripts.push(script_obj);

							do_script(chain_opts,script_obj,group,(can_use_preloading && scripts_currently_loading));
							scripts_currently_loading = true;

							if (chain_opts[_AlwaysPreserveOrder]) chainedAPI.wait();
						}
					})(arguments[i],arguments[i]);
				}
				return chainedAPI;
			},
			// force LABjs to pause in execution at this point in the chain, until the execution thus far finishes, before proceeding
			wait:function(){
				if (arguments.length > 0) {
					for (var i=0; i<arguments.length; i++) {
						chain.push(arguments[i]);
					}
					group = chain[chain.length-1];
				}
				else group = false;

				advance_exec_cursor();

				return chainedAPI;
			}
		};

		// the first chain link API (includes `setOptions` only this first time)
		return {
			script:chainedAPI.script,
			wait:chainedAPI.wait,
			setOptions:function(opts){
				merge_objs(opts,chain_opts);
				return chainedAPI;
			}
		};
	}

	// API for each initial $LAB instance (before chaining starts)
	instanceAPI = {
		// main API functions
		setGlobalDefaults:function(opts){
			merge_objs(opts,global_defaults);
			return instanceAPI;
		},
		setOptions:function(){
			return create_chain().setOptions.apply(null,arguments);
		},
		script:function(){
			return create_chain().script.apply(null,arguments);
		},
		wait:function(){
			return create_chain().wait.apply(null,arguments);
		},

		// built-in queuing for $LAB `script()` and `wait()` calls
		// useful for building up a chain programmatically across various script locations, and simulating
		// execution of the chain
		queueScript:function(){
			queue[queue.length] = {type:"script", args:[].slice.call(arguments)};
			return instanceAPI;
		},
		queueWait:function(){
			queue[queue.length] = {type:"wait", args:[].slice.call(arguments)};
			return instanceAPI;
		},
		runQueue:function(){
			var $L = instanceAPI, len=queue.length, i=len, val;
			for (;--i>=0;) {
				val = queue.shift();
				$L = $L[val.type].apply(null,val.args);
			}
			return $L;
		},

		// rollback `[global].$LAB` to what it was before this file was loaded, the return this current instance of $LAB
		noConflict:function(){
			global.$LAB = _$LAB;
			return instanceAPI;
		},

		// create another clean instance of $LAB
		sandbox:function(){
			return create_sandbox();
		}
	};

	return instanceAPI;
}

// create the main instance of $LAB
global.donrey.LAB = create_sandbox(); // instead of the global $LAB instance, we move this to donrey.LAB

/* The following "hack" was suggested by Andrea Giammarchi and adapted from: http://webreflection.blogspot.com/2009/11/195-chars-to-help-lazy-loading.html
   NOTE: this hack only operates in FF and then only in versions where document.readyState is not present (FF < 3.6?).

   The hack essentially "patches" the **page** that LABjs is loaded onto so that it has a proper conforming document.readyState, so that if a script which does
   proper and safe dom-ready detection is loaded onto a page, after dom-ready has passed, it will still be able to detect this state, by inspecting the now hacked
   document.readyState property. The loaded script in question can then immediately trigger any queued code executions that were waiting for the DOM to be ready.
   For instance, jQuery 1.4+ has been patched to take advantage of document.readyState, which is enabled by this hack. But 1.3.2 and before are **not** safe or
   fixed by this hack, and should therefore **not** be lazy-loaded by script loader tools such as LABjs.
*/
(function(addEvent,domLoaded,handler){
	if (document.readyState == null && document[addEvent]){
		document.readyState = "loading";
		document[addEvent](domLoaded,handler = function(){
			document.removeEventListener(domLoaded,handler,false);
			document.readyState = "complete";
		},false);
	}
})("addEventListener","DOMContentLoaded");

})(this);