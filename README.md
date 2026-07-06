# G6 Client Dashboard

A private WordPress plugin for Group6 agency clients. Replaces the default WordPress admin dashboard with a branded client portal — SEO keyword rankings, reputation metrics, how-to guides, service upsells, tracking script status, and a contact form.

## Requirements

- WordPress 6.0+
- PHP 8.0+

---

## Installation on a client site

1. Download the latest `g6-client-dashboard.zip` from [Releases](../../releases).
2. In WordPress admin go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate **Group6 Client Dashboard**.

---

## Settings

The **G6 Dashboard** settings page is only visible to users with a `@group6inc.com` or `@group6interactive.com` email address. Find it at **Dashboard → G6 Dashboard**.

### Tabs

| Tab | What it controls |
|---|---|
| **Dashboard** | Widget visibility toggles, account manager name/email/phone/photo |
| **Content** | How-to guides, add-on services, SEO keywords, featured video |
| **Tracking** | GTM, Google Ads, Meta Pixel, X Pixel, Microsoft Clarity — IDs inject scripts automatically into the frontend `<head>` |
| **Developer Tools** | G6 Asset Manager enable/disable toggle |
| **Plugin** | Current version info, force-refresh update cache, beta update channel toggle |

---

## Automatic updates

Updates are distributed via GitHub Releases. WordPress surfaces an update notification automatically — no WP.org account needed.

### How it works

1. The plugin polls a hosted Gist manifest once every 12 hours.
2. If the manifest version is newer than the installed version, WordPress shows the standard update notice.
3. Clicking **Update Now** downloads the zip from the GitHub Release and installs it.

### Manifest URLs

| Channel | Gist file |
|---|---|
| Stable | `g6-client-dashboard.json` |
| Beta | `g6-client-dashboard-beta.json` |

Both live in the same Gist: `https://gist.github.com/g6-gabriel/8d8b3d50ba384da12359e34c57efe39a`

Constants in `g6-client-dashboard.php`:

```php
G6_DASHBOARD_MANIFEST_URL       // stable channel
G6_DASHBOARD_MANIFEST_URL_BETA  // beta channel
```

---

## Releasing a stable version

```bash
# 1. Make sure you're on main and everything is committed
git checkout main

# 2. Bump version in g6-client-dashboard.php:
#    - Plugin header:  Version: 0.X.X
#    - PHP constant:   define( 'G6_DASHBOARD_VERSION', '0.X.X' )
#    - plugin-manifest.json: version + changelog entry

# 3. Commit, tag, and push
git add .
git commit -m "v0.X.X — short description"
git tag v0.X.X
git push origin main
git push origin v0.X.X
```

Pushing the tag triggers `.github/workflows/release.yml` which:
- Builds `g6-client-dashboard-v0.X.X.zip`
- Creates a GitHub Release and attaches the zip
- Updates the stable Gist manifest automatically

Client sites will pick up the update within 12 hours, or immediately via **Settings → Plugin → Force Refresh Update Cache** then **Dashboard → Updates → Check Again**.

---

## Beta branch workflow

The `beta` branch is for testing upcoming changes on a staging/test site before releasing to clients.

### Setup (one-time per test site)

1. Install the plugin on the test site.
2. Go to **Settings → Plugin → Enable beta versions** and save.
3. That site will now receive builds from the `beta` branch instead of stable releases.

### Day-to-day beta development

```bash
# Switch to beta branch
git checkout beta

# IMPORTANT: bump the version to the next planned release number
# e.g. if stable is 0.3.4, set version to 0.3.5 in g6-client-dashboard.php
# (both the header and the define constant)

# Work on your changes, then push
git add .
git commit -m "feat: description of change"
git push origin beta
```

Pushing to `beta` triggers `.github/workflows/release-beta.yml` which:
- Reads the version from the plugin file and appends `-beta` (e.g. `0.3.5-beta`)
- Builds `g6-client-dashboard-beta.zip`
- Creates/replaces the `beta-latest` pre-release on GitHub
- Updates the beta Gist manifest automatically

The test site will show `0.3.5-beta` as an available update.

### Version bump rule

**Always bump the version on `beta` to the next planned release number before pushing.**

PHP's `version_compare` treats pre-release suffixes as *older* than the base version:

```
0.3.4-beta  <  0.3.4   ← beta manifest must be HIGHER than installed version
0.3.5-beta  >  0.3.4   ← correct: update will be offered
```

If you forget to bump, the "Force Refresh Update Cache" button will still clear the WP transient correctly — but no update will appear because the beta version looks older than what's installed.

### Merging beta to stable

When the beta feature is ready:

```bash
git checkout main
git merge beta
# Version is already bumped (e.g. 0.3.5) — just tag and push
git tag v0.3.5
git push origin main
git push origin v0.3.5
```

Test sites still on `0.3.5-beta` will be offered the `0.3.5` stable release as an upgrade.

---

## File structure

```
g6-client-dashboard/
├── g6-client-dashboard.php       ← Main plugin file, constants, conditional boot
├── plugin-manifest.json          ← Source manifest — workflows read this for changelog/metadata
├── includes/
│   ├── class-updater.php         ← GitHub auto-updater (stable + beta channel)
│   ├── config.php                ← Default config values & option retrieval
│   ├── icons.php                 ← SVG icon helpers
│   ├── dashboard.php             ← Client-facing dashboard: widgets, sidebar, CSS, render
│   ├── ajax.php                  ← Contact form AJAX handler (Zendesk)
│   ├── settings.php              ← Admin settings page (Group6 users only)
│   ├── tracking.php              ← Tracking script injection (GTM, Ads, Meta, X, Clarity)
│   └── asset-manager.php         ← G6 Asset Manager (loaded conditionally)
└── .github/
    └── workflows/
        ├── release.yml           ← Stable release: triggers on version tag push
        └── release-beta.yml      ← Beta release: triggers on every push to beta branch
```

---

## Security

- All settings pages check `g6_is_group6_user()` — only `@group6inc.com` / `@group6interactive.com` users can access them.
- Tracking scripts inject into `wp_head` (frontend only) — never into `admin_head`.
- All AJAX handlers verify nonce + `manage_options` capability.
- All output is escaped (`esc_html`, `esc_attr`, `esc_url`, `esc_js`).
