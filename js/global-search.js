/**
 * Recursively searches the scope for the given value.
 *
 * All matches are displayed in the browser console and stored in the global variable "gsResults"
 * The function tries to simplify DOM element names by using their ID, when possible.
 *
 * Usage samples:
 *
 * // Search entire DOM document for the string value.
 * globalSearch( document, 'someValue' );
 *
 * // Simple regex search (function recognizes prefix/suffix patterns: "^..." or "...$").
 * globalSearch( document, '^start' );
 *
 * // Advanced Regex search.
 * globalSearch( document, new Regex('[a|b]') );
 *
 * // Searches all keys with the name "value" inside the object window.myObj
 * globalSearch( 'myObj', 'value', 'key' );
 *
 * // Ends the search after 3 results were found.
 * globalSearch( window, 'value', 'key', 3 );
 *
 * // Finds the first three occurances of "value" in either an object key or value.
 * globalSearch( window, 'value', 'all', 3 );
 *
 * Output:
 *
 * 1. Match: 	KEY
 *  Value:   	[string] on
 *  Address: 	window.gsResults[1]
 *  document.getElementById("the-id").childNodes[1].__reactInternalInstance$v9o4el5z24e.alternate..memoizedProps._owner.alternate.memoizedState.attrs.some_value
 *
 * @param {string|object} scope The object to search. Either an object, or the global object name as string.
 * @param {any} value The value to find. Can be any type, or a Regex string.
 * @param {string} searchField Either of [value|key|all].
 * @param {int} limit Max results to return. Default is -1, which means "unlimited".
 */
window.globalSearch = function globalSearch(scope, value, searchField = 'value', limit = -1) {
	let startName = '';

	if ('string' === typeof scope) {
		startName = scope;
		scope = eval(scope);
	} else if (window === scope) {
		startName = 'window';
	} else if (document === scope) {
		startName = 'document';
	}

	const stack = [[scope, startName, startName]];
	let searched = [];
	let found = 0;
	let count = 1;
	let isRegex = 'string' === typeof value && (-1 !== value.indexOf('*') || '^' === value[0] || '$' === value[value.length - 1]);

	const resultBuffer = [];
	window.gsResults = resultBuffer;

	if (isRegex) {
		value = new RegExp(value);
	} else if ('object' === typeof value && value instanceof RegExp) {
		isRegex = true;
	}

	if (!searchField) {
		searchField = 'value';
	}

	if (!scope) {
		console.error('The "scope" parameter must be a valid object, or object-name. Found:', scope);
		showUsage();
		return;
	}

	if (-1 === ['value', 'key', 'all'].indexOf(searchField)) {
		console.error('The "where" parameter must be either of [value|key|all]. Found:', searchField);
		showUsage();
		return;
	}

	function showUsage() {
		console.log(
			'Usage: %cglobalSearch%c( %c{object}%c %cscope%c, %c{string|Regex}%c %csearch%c, %c{value|key|all}%c %cwhere%c = \'value\', %c{int}%c %climit%c = -1 )',
			'font-weight:bold',
			'',
			'color:#aaa;font-style:italic',
			'',
			'font-weight:bold',
			'',
			'color:#aaa;font-style:italic',
			'',
			'font-weight:bold',
			'',
			'color:#aaa;font-style:italic',
			'',
			'font-weight:bold',
			'',
			'color:#aaa;font-style:italic',
			'',
			'font-weight:bold',
			'',
		);
	}

	function isArray(test) {
		const type = Object.prototype.toString.call(test);
		return '[object Array]' === type || '[object NodeList]' === type;
	}

	function isElement(o) {
		try {
			return (typeof HTMLElement === 'object' ? o instanceof HTMLElement : //DOM2
				o && typeof o === 'object' && true && o.nodeType === 1 && typeof o.nodeName === 'string');
		} catch (e) {
			// usually a security error like "DOMException: Blocked a frame with origin"
			return false;
		}
	}

	function isMatch(item) {
		if (isRegex) {
			return value.test(item);
		} else {
			return item === value;
		}
	}

	function result(type, address, shortAddr, value) {
		const msg = [];

		found++;
		resultBuffer[found] = {
			match: type,
			value: value,
			pathOrig: address,
			pathShort: shortAddr,
		};

		msg.push(found + '. Match: \t' + type.toUpperCase());
		msg.push('  Value:   \t[' + (typeof value) + '] ' + value);
		msg.push('  Address: \twindow.gsResults[' + found + ']');
		msg.push('%c' + shortAddr);

		console.log(msg.join('\n'), 'background:Highlight;color:HighlightText;margin-left:12px');
	}

	function skip(obj, key) {
		const traversing = [
			'firstChild',
			'previousSibling',
			'nextSibling',
			'lastChild',
			'previousElementSibling',
			'nextElementSibling',
			'firstEffect',
			'nextEffect',
			'lastEffect',
		];
		const scopeChange = [
			'ownerDocument',
		];
		const deprecatedDOM = [
			'webkitStorageInfo',
		];

		if (-1 !== traversing.indexOf(key)) {
			return true;
		}
		if (-1 !== scopeChange.indexOf(key)) {
			return true;
		}
		if (-1 !== deprecatedDOM.indexOf(key)) {
			return true;
		}
		if (obj === resultBuffer) {
			return true;
		}

		let isInvalid = false;
		try {
			const ignore = obj[key];
		} catch (ex) {
			isInvalid = true;
		}

		return isInvalid;
	}

	while (stack.length) {
		if (limit > 0 && found >= limit) {
			break;
		}

		const fromStack = stack.pop();
		const obj = fromStack[0];
		const address = fromStack[1];
		let display = fromStack[2];

		if ('key' !== searchField && isMatch(obj)) {
			result('value', address, display, obj);
			if (limit > 0 && found >= limit) {
				break;
			}
		}

		if (obj && typeof obj == 'object' && -1 === searched.indexOf(obj)) {
			const objIsArray = isArray(obj);

			if (isElement(obj) && obj.id) {
				display = 'document.getElementById("' + obj.id + '")';
			}

			for (let i in obj) {
				if (skip(obj, i)) {
					continue;
				}

				const subAddr = (objIsArray || 'number' === typeof i) ? '[' + i + ']' : '.' + i;
				const addr = address + subAddr;
				const displayAddr = display + subAddr;

				stack.push([obj[i], addr, displayAddr]);
				count++;

				if ('value' !== searchField && isMatch(i)) {
					result('key', address, displayAddr, obj[i]);
					if (limit > 0 && found >= limit) {
						break;
					}
				}
			}
			searched.push(obj);
		}
	}

	searched = null;

	console.log('-----');
	console.log('All Done!');
	console.log('Searched', count.toLocaleString(), 'items');
	console.log('Found', found.toLocaleString(), 'results');
	return found;
};
