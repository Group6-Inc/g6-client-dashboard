<?php
/**
 * Checks for g6_widget_order() and the saved-text slash repair — run with:
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
 *
 * And that text saved with backslashes in front of its quotes — every
 * version before 1.0 did that — comes back as it was typed, however many
 * times it was re-saved.
 */

define('ABSPATH', '/tmp');

set_error_handler(function (int $no, string $msg, string $file, int $line) {
    fwrite(STDERR, sprintf("PHP diagnostic: %s (%s:%d)\n", $msg, basename($file), $line));
    $GLOBALS['php_diagnostics'] = ($GLOBALS['php_diagnostics'] ?? 0) + 1;
    return true;
});
$GLOBALS['php_diagnostics'] = 0;

function get_bloginfo($what = '') { return 'Test Site'; }
$GLOBALS['o'] = [];
function get_option($key, $default = false) { return $GLOBALS['o'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['o'][$key] = $value; return true; }
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

// ── Saved text loses the slashes earlier versions put in it ──────────
// addslashes() is what WordPress does to every request value, so one call
// is one save that forgot wp_unslash(), and two calls are a re-save.
$typed = 'We\'re "good"';

is('the bug as seen', addslashes($typed), 'We\\\'re \\"good\\"');
is('one layer', g6_unslash_saved_text(addslashes($typed)), $typed);
is('a layer per re-save', g6_unslash_saved_text(addslashes(addslashes(addslashes($typed)))), $typed);
is('a backslash typed on purpose survives', g6_unslash_saved_text('C:\\path\\to'), 'C:\\path\\to');
is('clean text is untouched', g6_unslash_saved_text('Plain words'), 'Plain words');
is('not only strings', g6_unslash_saved_text(['on' => true, 'n' => 3]), ['on' => true, 'n' => 3]);
is('nested rows, like guides', g6_unslash_saved_text(['guides' => [['description' => addslashes($typed)]]]),
   ['guides' => [['description' => $typed]]]);

$GLOBALS['o'] = ['g6_client_config' => ['guides' => [['title' => 'How To', 'description' => addslashes($typed)]]]];
g6_config_repair_slashes();
is('the stored config is repaired', $GLOBALS['o']['g6_client_config']['guides'][0]['description'], $typed);
is('and marked as done', (bool) get_option(G6_SLASHES_REPAIRED_OPTION), true);

// Once is enough: anything saved after this went through wp_unslash().
$GLOBALS['o']['g6_client_config'] = ['video_title' => addslashes($typed)];
g6_config_repair_slashes();
is('it does not run twice', $GLOBALS['o']['g6_client_config']['video_title'], addslashes($typed));

// Both places that take typed text unslash it before anything else.
$_set  = file_get_contents(__DIR__ . '/../includes/settings.php');
$_from = strpos($_set, '$post = wp_unslash( $_POST );');
$_to   = strpos($_set, "update_option( 'g6_client_config', \$config );", (int) $_from);
is('the settings save unslashes', $_from !== false && $_to !== false, true);
is('and reads nothing slashed after it', str_contains(substr($_set, $_from + 30, $_to - $_from - 30), '$_POST'), false);

$_ajax = file_get_contents(__DIR__ . '/../includes/ajax.php');
is('the contact subject is unslashed', str_contains($_ajax, "wp_unslash( \$_POST['subject'] ?? '' )"), true);
is('the contact message is unslashed', str_contains($_ajax, "wp_unslash( \$_POST['message'] ?? '' )"), true);

if ($GLOBALS['php_diagnostics'] > 0) {
    $fail++;
    printf("FAIL %d PHP warning(s)/notice(s) emitted — see above\n", $GLOBALS['php_diagnostics']);
}

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
