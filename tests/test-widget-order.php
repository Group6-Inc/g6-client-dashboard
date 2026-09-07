<?php
/**
 * Checks for g6_widget_order() — run with:
 *
 *     php tests/test-widget-order.php
 *
 * Separate from test-g6-api.php because that file stubs
 * g6_get_client_config() and this one needs the real includes/config.php,
 * which declares it.
 *
 * What it protects: the dashboard order is a stored list of widget keys,
 * and a stored list of keys is exactly the shape that makes a NEWLY
 * ADDED widget invisible on every site that saved an order before it
 * existed. That is not hypothetical — the settings JS had the same shape
 * as a hardcoded array and swallowed the Project Status widget's toggle
 * feedback the day it was added.
 */

define('ABSPATH', '/tmp');

set_error_handler(function (int $no, string $msg, string $file, int $line) {
    fwrite(STDERR, sprintf("PHP diagnostic: %s (%s:%d)\n", $msg, basename($file), $line));
    $GLOBALS['php_diagnostics'] = ($GLOBALS['php_diagnostics'] ?? 0) + 1;
    return true;
});
$GLOBALS['php_diagnostics'] = 0;

function get_bloginfo($what = '') { return 'Test Site'; }
function get_option($key, $default = false) { return $default; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function current_time($f) { return '2026-09-07 12:00:00'; }
function home_url($path = '') { return 'https://example.test' . $path; }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }

require __DIR__ . '/../includes/config.php';

$pass = 0; $fail = 0;
function is(string $what, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL %s\n  got  %s\n  want %s\n", $what, var_export($got, true), var_export($want, true));
}

$known = array_keys(g6_default_config()['widgets']);

// A site that saved its order before a widget existed still shows it.
$order = g6_widget_order(['widget_order' => ['contact', 'guides']]);
is('a saved order comes first', array_slice($order, 0, 2), ['contact', 'guides']);
is('every widget still renders', count($order), count($known));
is('nothing is lost', array_values(array_diff($known, $order)), []);

// Keys are not trusted just because they were stored.
$order = g6_widget_order(['widget_order' => ['guides', 'nonsense', 'contact']]);
is('an unknown key is dropped', in_array('nonsense', $order, true), false);

// A duplicate would render the same widget twice.
$order = g6_widget_order(['widget_order' => ['contact', 'contact', 'guides']]);
is('a repeated key appears once', count(array_keys($order, 'contact')), 1);

// No saved order at all is the normal case on a fresh site.
is('no saved order => the shipped order', g6_widget_order([]), $known);
is('an empty saved order => the shipped order', g6_widget_order(['widget_order' => []]), $known);

// The default the plugin ships puts guides first and contact last.
$shipped = g6_default_config()['widget_order'];
is('guides leads', $shipped[0], 'guides');
is('get in touch is last', end($shipped), 'contact');

if ($GLOBALS['php_diagnostics'] > 0) {
    $fail++;
    printf("FAIL %d PHP warning(s)/notice(s) emitted — see above\n", $GLOBALS['php_diagnostics']);
}

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
