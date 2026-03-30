# G6 Client Dashboard

A private WordPress plugin for Group6 agency clients. Replaces the default WordPress admin dashboard with a branded client portal showing SEO rankings, reputation metrics, service upsells, how-to guides, and a contact form.

## Requirements

- WordPress 6.0+
- PHP 8.0+

---

## Installation on a client site

1. Download the latest `g6-client-dashboard.zip` from [Releases](../../releases).
2. In WordPress admin go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate **Group6 Client Dashboard**.
4. Add the GitHub token to `wp-config.php` (see below) so automatic updates work.

---

## Automatic updates

Updates are distributed via GitHub Releases. WordPress will surface an update notification automatically — no WP.org account needed.

### How it works

1. The plugin polls a hosted manifest JSON file once every 12 hours.
2. If the manifest version is newer than the installed version, WordPress shows the standard update notice.
3. Clicking **Update Now** downloads the zip from the GitHub Release and installs it.

### GitHub token (required for private repo)

Because this repo is private, each client site needs a GitHub Personal Access Token to download updates.

Add this line to **`wp-config.php`** on every client site:

```php
define( 'G6_GITHUB_TOKEN', 'github_pat_xxxxxxxxxxxxxxxxxxxx' );
```

**Token permissions needed:** `Contents: Read-only` on this repo.

> Never commit a real token to the repo. Rotate tokens annually.

### Manifest URL

The plugin points to:

```
https://group6inc.com/g6-client-dashboard.json
```

Update this URL in `g6-client-dashboard.php` (`G6_DASHBOARD_MANIFEST_URL`) if you host it elsewhere. The manifest file in this repo (`plugin-manifest.json`) is a reference copy — you manage the live one on your server.

---

## Releasing a new version

**Every release is two steps:**

### Step 1 — Tag the release in GitHub

Bump the version string in **two places**:

- `g6-client-dashboard.php` — the `Version:` header **and** `define( 'G6_DASHBOARD_VERSION', '...' )`

Then push a tag:

```bash
git add .
git commit -m "Release v1.2.0"
git tag v1.2.0
git push origin main --tags
```

GitHub Actions will automatically build `g6-client-dashboard.zip` and attach it to a new Release.

### Step 2 — Update the manifest JSON

Edit the live `g6-client-dashboard.json` on your server (e.g. `https://group6inc.com/g6-client-dashboard.json`):

- Bump `version` to match the new tag
- Update `download_url` to point to the new Release zip
- Update `last_updated`

Client sites will pick up the update within 12 hours, or immediately via **Dashboard → Updates → Check Again**.

---

## Settings

The **G6 Dashboard** settings page is only visible to users with an `@group6inc.com` email address. Find it at **Dashboard → G6 Dashboard**.

Configurable per-site:
- Account manager name, email, phone, photo
- Zendesk subdomain (falls back to email if blank)
- SEO keyword rankings

---

## File structure

```
g6-client-dashboard/
├── g6-client-dashboard.php     ← Main plugin file & constants
├── includes/
│   ├── class-updater.php       ← GitHub auto-updater
│   ├── config.php              ← Default config & option retrieval
│   ├── icons.php               ← SVG icon helpers
│   ├── dashboard.php           ← Widget registration, CSS, render
│   ├── ajax.php                ← Contact form AJAX handler
│   └── settings.php            ← Admin settings page
├── plugin-manifest.json        ← Reference copy of the hosted manifest
├── .github/
│   └── workflows/
│       └── release.yml         ← Auto-builds zip on version tag push
└── .gitignore
```
