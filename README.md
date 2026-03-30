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

---

## Automatic updates

Updates are distributed via GitHub Releases. WordPress will surface an update notification automatically — no WP.org account needed.

### How it works

1. The plugin polls a hosted manifest JSON file once every 12 hours.
2. If the manifest version is newer than the installed version, WordPress shows the standard update notice.
3. Clicking **Update Now** downloads the zip from the GitHub Release and installs it.

No token or authentication needed — the repo is public.

### Manifest URL

The plugin points to:

```
https://gist.githubusercontent.com/g6-gabriel/8d8b3d50ba384da12359e34c57efe39a/raw/g6-client-dashboard.json
```

Update this URL in `g6-client-dashboard.php` (`G6_DASHBOARD_MANIFEST_URL`) if you move the manifest. The `plugin-manifest.json` in this repo is a reference copy — the live one is the Gist above.

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
