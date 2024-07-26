<?php
/**
 * @formatter:off
 * Plugin Name: JS Debugger
 * Plugin URI:  https://github.com/stracker-phil/wp-js-debugger
 * Description: Injects JS snippets into the page to help with front-end debugging, like logging all events to the console.
 * Author:      Philipp Stracker (Syde)
 * Version:     1.1.0
 * @formatter:on
 */

namespace Syde\Debug;

require_once __DIR__ . '/admin/settings.php';
require_once __DIR__ . '/browser/debugger.php';
