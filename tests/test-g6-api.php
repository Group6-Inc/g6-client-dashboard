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

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
