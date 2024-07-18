# WP JS Debugger

Debugging plugin to analyze JS events in WordPress

## Usage

1. Install and activate the plugin
2. Inspect the JS console of the browser to see details about JS events

**Global JS search**

In the JS console, use the new function `globalSearch()` to find JS data in any object.

```js
// Search entire DOM document for the string value.
globalSearch( document, 'someValue' );

// Simple regex search (function recognizes prefix/suffix patterns: "^..." or "...$").
globalSearch( document, '^start' );

// Advanced Regex search.
globalSearch( document, new Regex('[a|b]') );

// Searches all keys with the name "value" inside the object window.myObj
globalSearch( 'myObj', 'value', 'key' );

// Ends the search after 3 results were found.
globalSearch( window, 'value', 'key', 3 );

// Finds the first three occurances of "value" in either an object key or value.
globalSearch( window, 'value', 'all', 3 );
```

**Disable Debugger**

1. Deactivate the plugin again

## Changelog

- 1.0.2
  - New: Add a global JS search function, from this Gist: <https://gist.github.com/stracker-phil/e5b3bbd5d5eb4ffb2acdcda90d8bd04f>
- 1.0.1
  - Improve: Move debugging script to a JS file instead of using an inline script.
  - Fix: The debugging script is now also loaded on wp-admin.
- 1.0.0
  - Initial version
