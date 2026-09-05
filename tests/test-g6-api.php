<?php
/**
 * Standalone checks for includes/g6-api.php — run with:
 *
 *     php tests/test-g6-api.php
 *
 * No WordPress, no PHPUnit, no composer: the file stubs the handful of
 * WP functions the API layer touches and asserts on the results. That is
 * deliberate. This plugin has no test tooling, and a suite nobody can run
 * without installing something is a suite nobody runs.
 *
 * What it is actually protecting:
 *   - a site with no source saved keeps reading Airtable. Get this wrong
 *     and every existing client site goes blank on update.
 *   - the cache key includes the portal URL, so pointing a site at
 *     staging and back cannot serve the other one's balance.
 *   - failures are cached briefly, so a portal that is down is not hit
 *     on every single admin page load.
 */

// Any warning or notice is a failure. A foreach over a malformed API
// response only warns — it does not stop — so without this a missing
// guard passes every assertion while writing to the client's error log
// on every dashboard load. That exact sabotage went undetected until
// this was added.
set_error_handler(function (int $no, string $msg, string $file, int $line) {
    fwrite(STDERR, sprintf("PHP diagnostic: %s (%s:%d)\n", $msg, basename($file), $line));
    $GLOBALS['php_diagnostics'] = ($GLOBALS['php_diagnostics'] ?? 0) + 1;
    return true;
});
$GLOBALS['php_diagnostics'] = 0;

// Minimal WordPress stand-in, enough to exercise includes/g6-api.php.
define('ABSPATH', '/tmp');
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

class WP_Error {
    public function __construct(public string $code = '', public string $message = '') {}
    public function get_error_message(): string { return $this->message; }
}
function is_wp_error($t): bool { return $t instanceof WP_Error; }

$GLOBALS['t'] = [];
function get_transient($k) { return $GLOBALS['t'][$k] ?? false; }
function set_transient($k, $v, $ttl = 0) { $GLOBALS['t'][$k] = $v; return true; }
function delete_transient($k) { unset($GLOBALS['t'][$k]); return true; }
function current_time($f) { return '2026-09-04 12:00:00'; }

$GLOBALS['cfg'] = [];
function g6_get_config() { return $GLOBALS['cfg']; }

$GLOBALS['http'] = ['code' => 200, 'body' => '{}', 'calls' => []];
function wp_remote_get($url, $args = []) {
    $GLOBALS['http']['calls'][] = ['url' => $url, 'args' => $args];
    return $GLOBALS['http'];
}
function wp_remote_post($url, $args = []) { return wp_remote_get($url, $args); }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }

require __DIR__ . '/../includes/g6-api.php';

$pass = 0; $fail = 0;
function is(string $what, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL %s\n  got  %s\n  want %s\n", $what, var_export($got, true), var_export($want, true));
}

// ── 1. An older site with no source saved must keep reading Airtable ──
is('no setting saved => airtable', g6_support_hours_source([]), 'airtable');
is('empty string => airtable', g6_support_hours_source(['support_hours_source' => '']), 'airtable');
is('garbage => airtable', g6_support_hours_source(['support_hours_source' => 'nonsense']), 'airtable');
is('portal => portal', g6_support_hours_source(['support_hours_source' => 'portal']), 'portal');

// ── 2. Base URL precedence, and trailing slashes ──
is('blank setting => production', g6_api_base([]), 'https://portal.group6inc.com/api/v1');
is('setting wins over default', g6_api_base(['portal_url' => 'http://wp.test/api/v1']), 'http://wp.test/api/v1');
is('trailing slash trimmed', g6_api_base(['portal_url' => 'http://wp.test/api/v1/']), 'http://wp.test/api/v1');

// ── 3. Cache identity includes the base URL ──
$a = g6_api_transient_key('support-hours', 'tok', ['portal_url' => 'https://a.test/api/v1']);
$b = g6_api_transient_key('support-hours', 'tok', ['portal_url' => 'https://b.test/api/v1']);
is('different portal => different key', $a !== $b, true);
$c = g6_api_transient_key('support-hours', 'other', ['portal_url' => 'https://a.test/api/v1']);
is('different token => different key', $a !== $c, true);

// ── 4. A successful fetch is passed through and cached ──
$GLOBALS['cfg'] = ['portal_url' => 'https://a.test/api/v1'];
$GLOBALS['http'] = ['code' => 200, 'calls' => [], 'body' => json_encode([
    'active' => true, 'balance_hours' => 12.5, 'total_hours' => 40.0, 'fetched_at' => '2026-09-04 11:00:00',
])];
$got = g6_api_get_support_hours('tok');
is('shape matches the Airtable contract', array_keys($got), ['active', 'balance_hours', 'total_hours', 'fetched_at']);
is('balance passed through', $got['balance_hours'], 12.5);
is('bearer token sent', $GLOBALS['http']['calls'][0]['args']['headers']['Authorization'], 'Bearer tok');
is('hits the configured host', $GLOBALS['http']['calls'][0]['url'], 'https://a.test/api/v1/support-hours');

$before = count($GLOBALS['http']['calls']);
g6_api_get_support_hours('tok');
is('second call served from cache', count($GLOBALS['http']['calls']), $before);

// ── 5. Failures return false, and say something useful ──
$GLOBALS['http'] = ['code' => 401, 'body' => '{}', 'calls' => []];
is('401 => false', g6_api_get_support_hours('bad'), false);
is('401 message names the cause', str_contains(g6_api_get_last_error('support-hours', 'bad'), 'token was rejected'), true);

$GLOBALS['http'] = ['code' => 403, 'body' => '{}', 'calls' => []];
is('403 => false', g6_api_get_support_hours('scoped'), false);
is('403 mentions permission', str_contains(g6_api_get_last_error('support-hours', 'scoped'), 'not permitted'), true);

$GLOBALS['http'] = ['code' => 500, 'body' => 'nope', 'calls' => []];
is('500 => false', g6_api_get_support_hours('boom'), false);

$GLOBALS['http'] = ['code' => 200, 'body' => '{"active":true}', 'calls' => []];
is('no token => false', g6_api_get_support_hours(''), false);
is('no token => no request at all', count($GLOBALS['http']['calls']), 0);

// ── 6. Clearing under the old key when settings change ──
$old = ['portal_url' => 'https://a.test/api/v1'];
$GLOBALS['cfg'] = ['portal_url' => 'https://b.test/api/v1'];
$GLOBALS['t'][g6_api_transient_key('support-hours', 'tok', $old)] = ['balance_hours' => 99];
g6_api_clear_cache('tok', $old);
is('old entry is gone', get_transient(g6_api_transient_key('support-hours', 'tok', $old)), false);

// ── 7. Destination defaults, same upgrade-safety rule as the source ──
is('no destination saved => zendesk', g6_tickets_destination([]), 'zendesk');
is('garbage => zendesk', g6_tickets_destination(['tickets_destination' => 'nonsense']), 'zendesk');
is('portal => portal', g6_tickets_destination(['tickets_destination' => 'portal']), 'portal');

// ── 8. The token is one shared connection, not a per-feature setting ──
is('token read from the shared key', g6_portal_token(['portal_token' => ' abc ']), 'abc');
is('missing token => empty', g6_portal_token([]), '');

// ── 9. Submitting sends no category ──────────────────────────────────
// The dropdown mirrors Zendesk's issue-type field in prose; none of its
// values is a portal category slug, so sending one would be a 422.
function wp_get_current_user() {
    return new class { public function exists() { return true; }
        public $user_email = 'jane@acme.test'; public $display_name = 'Jane'; };
}
function home_url() { return 'https://acme.test'; }
$GLOBALS['cfg'] = ['portal_url' => 'https://a.test/api/v1'];
$GLOBALS['http'] = ['code' => 201, 'body' => '{"id":7}', 'calls' => []];
$made = g6_api_submit_ticket('tok', ['subject' => 'Hosting-related issues', 'body' => 'help']);
is('ticket created', $made['id'], 7);
$sent = $GLOBALS['http']['calls'][0]['args']['body'];
is('no category is sent', array_key_exists('category', $sent), false);
is('the chosen topic survives as the subject', $sent['subject'], 'Hosting-related issues');
is('the wp user identifies the sender', $sent['email'], 'jane@acme.test');

// ── 10. Categories come from the portal, not from this plugin ────────
$GLOBALS['t'] = [];
$GLOBALS['http'] = ['code' => 200, 'calls' => [], 'body' => json_encode(['categories' => [
    ['slug' => 'website_update', 'name' => 'Website Update'],
    ['slug' => 'seo',            'name' => 'SEO'],
]])];
is('slug => name, in the order given',
   g6_api_get_ticket_categories('tok'),
   ['website_update' => 'Website Update', 'seo' => 'SEO']);

// A rename in the portal reaches the form with no plugin release.
$GLOBALS['t'] = [];
$GLOBALS['http'] = ['code' => 200, 'calls' => [], 'body' => json_encode(['categories' => [
    ['slug' => 'bug_report', 'name' => 'Something Is Broken'],
]])];
is('a rename comes through', g6_api_get_ticket_categories('tok'), ['bug_report' => 'Something Is Broken']);

// Unreachable or malformed must not throw — the form falls back.
$GLOBALS['t'] = [];
$GLOBALS['http'] = ['code' => 500, 'body' => 'nope', 'calls' => []];
is('portal down => empty list, no error', g6_api_get_ticket_categories('tok'), []);

$GLOBALS['t'] = [];
$GLOBALS['http'] = ['code' => 200, 'calls' => [], 'body' => json_encode(['categories' => 'not-an-array'])];
is('garbage => empty list', g6_api_get_ticket_categories('tok'), []);

$GLOBALS['t'] = [];
$GLOBALS['http'] = ['code' => 200, 'calls' => [], 'body' => json_encode(['categories' => [
    ['slug' => 'ok', 'name' => 'Fine'], ['name' => 'No slug'], ['slug' => 'no-name'],
]])];
is('half-built rows are dropped', g6_api_get_ticket_categories('tok'), ['ok' => 'Fine']);

// ── 11. A chosen category rides along with the submission ────────────
$GLOBALS['t'] = [];
$GLOBALS['http'] = ['code' => 201, 'body' => '{"id":9}', 'calls' => []];
g6_api_submit_ticket('tok', ['subject' => 'Slider is broken', 'body' => 'help', 'category' => 'bug_report']);
$sent = $GLOBALS['http']['calls'][0]['args']['body'];
is('category sent when chosen', $sent['category'], 'bug_report');
is('subject is the typed one, not the category', $sent['subject'], 'Slider is broken');

// ── 12. A refusal and an outage are different things ─────────────────
// The caller shows a refusal to the person and falls back to email on an
// outage, so conflating them either hides the reason or emails a message
// the portal has already said is misaddressed.
$GLOBALS['http'] = ['code' => 422, 'calls' => [],
    'body' => json_encode(['message' => 'That is a Group6 staff account, so it has no client to file this under.'])];
$err = g6_api_submit_ticket('tok', ['subject' => 'x', 'body' => 'y']);
is('422 is a rejection', $err->code, 'g6_api_rejected');
is('the portal\'s own sentence survives', $err->get_error_message(),
   'That is a Group6 staff account, so it has no client to file this under.');

$GLOBALS['http'] = ['code' => 500, 'body' => 'boom', 'calls' => []];
$err = g6_api_submit_ticket('tok', ['subject' => 'x', 'body' => 'y']);
is('500 is not a rejection', $err->code, 'g6_api_submit');

// A revoked token answers exactly this. It is OUR problem, not the
// client's: showing them "Unauthenticated." while silently not sending
// their message is the worst of both, so it falls through to email like
// any other outage.
$GLOBALS['http'] = ['code' => 401, 'calls' => [], 'body' => json_encode(['message' => 'Unauthenticated.'])];
$err = g6_api_submit_ticket('tok', ['subject' => 'x', 'body' => 'y']);
is('a revoked token is an outage, not a rejection', $err->code, 'g6_api_submit');

$GLOBALS['http'] = ['code' => 403, 'body' => '{}', 'calls' => []];
$err = g6_api_submit_ticket('tok', ['subject' => 'x', 'body' => 'y']);
is('a scope failure is an outage too', $err->code, 'g6_api_submit');

$GLOBALS['http'] = ['code' => 429, 'body' => '{}', 'calls' => []];
$err = g6_api_submit_ticket('tok', ['subject' => 'x', 'body' => 'y']);
is('rate limiting is an outage too', $err->code, 'g6_api_submit');

// ── 13. Only the projects still going belong on a dashboard ──────────
$GLOBALS['t'] = [];
$GLOBALS['http'] = ['code' => 200, 'calls' => [], 'body' => json_encode(['projects' => [
    ['name' => 'Site redesign', 'launched_on' => null,         'steps_done' => 4, 'steps_total' => 7],
    ['name' => 'Old build',     'launched_on' => '2025-11-02',  'steps_done' => 9, 'steps_total' => 9],
]])];
$live = g6_api_get_live_projects('tok');
is('launched projects are left off', count($live), 1);
is('the live one is kept', $live[0]['name'], 'Site redesign');

$GLOBALS['t'] = [];
$GLOBALS['http'] = ['code' => 200, 'calls' => [], 'body' => json_encode(['projects' => [
    ['name' => 'Done', 'launched_on' => '2026-01-01'],
]])];
is('nothing live => empty, not false', g6_api_get_live_projects('tok'), []);

$GLOBALS['t'] = [];
$GLOBALS['http'] = ['code' => 500, 'body' => 'boom', 'calls' => []];
is('portal down => empty, not false', g6_api_get_live_projects('tok'), []);

$GLOBALS['t'] = [];
$GLOBALS['http'] = ['code' => 200, 'calls' => [], 'body' => json_encode(['projects' => 'nonsense'])];
is('garbage => empty', g6_api_get_live_projects('tok'), []);

// ── 14. The dashboard template renders no template syntax ────────────
// A Blade comment written into a WordPress template does not get
// compiled away — it prints, in full, on a client's dashboard. This
// plugin is plain PHP, and the habit of the other repo is one keystroke
// away at any time.
$tpl = file_get_contents(__DIR__ . '/../includes/dashboard.php');
is('no Blade comments in the template', str_contains($tpl, '{{--'), false);
is('no Blade echoes in the template', (bool) preg_match('/\{\{\s*\$/', $tpl), false);
is('no Blade directives in the template', (bool) preg_match('/^\s*@(if|foreach|endif|php)\b/m', $tpl), false);

if ($GLOBALS['php_diagnostics'] > 0) {
    $fail++;
    printf("FAIL %d PHP warning(s)/notice(s) emitted — see above\n", $GLOBALS['php_diagnostics']);
}

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
