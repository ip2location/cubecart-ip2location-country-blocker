# IP2Location Country Blocker

A CubeCart 6 extension that lets you block store visitors based on the country
their IP address originates from, using the official
[IP2Location PHP library](https://github.com/ip2location/IP2Location-PHP-Module).

For each blocked visitor you can choose one of three actions:

1. **Default 403 error page** – served with an HTTP 403 status and an optional
   custom message.
2. **Custom page** – full HTML content of your choice, served with an HTTP 403
   status (handy for a branded "not available in your country" page).
3. **Redirect** – an HTTP 302 redirect to a custom URL (external or
   store-relative).

## Requirements

* CubeCart 6.x
* PHP 7.4 or higher
* For the **BIN database** lookup source: `ext-bcmath`, `ext-curl`,
  `ext-json` (recommended by the IP2Location library) and a
  [free IP2Location LITE BIN database](https://lite.ip2location.com/database-download)
  – a sample `IP2LOCATION-LITE-DB1.BIN` (country-level) is bundled with this
  plugin so it works immediately.
* For the **IP2Location.io API** lookup source: `ext-curl` and an
  [IP2Location.io API key](https://www.ip2location.io/) (a free tier is
  available).

## Installation

1. Copy the `ip2location_country_blocker` folder into
   `CubeCartRoot/modules/plugins/`.
2. Log in to the CubeCart admin panel and open **Extensions → Plugins**
   (the plugin appears in the *Installed* list).
3. Click **IP2Location Country Blocker** to open its configuration page.

There is no separate install step. The storefront hook is declared in the
plugin's `config.xml` and is registered automatically by CubeCart the first
time the module is saved with **Status** enabled (CubeCart's standard module
hook mechanism).

## Configuration

| Setting | Purpose |
|---|---|
| **Status** | Master on/off switch. The storefront hook is enabled/disabled in sync with this. |
| **IP lookup source** | Where to detect visitor countries from: **IP2Location BIN database** (local file, no ongoing cost) or **IP2Location.io API** (web service, requires an API key). |
| **IP2Location.io API key** | Your IP2Location.io API key (32 characters, from [ip2location.io](https://www.ip2location.io/)), used when the lookup source is the API. |
| **IP2Location BIN database path** | Path to the BIN database (used when the lookup source is the BIN database). Relative paths resolve from the store root. Defaults to the bundled LITE DB1 file. |
| **Use X-Forwarded-For header** | Enable only when the store sits behind a proxy/load balancer and the real visitor IP is only in the `X-Forwarded-For` header. |
| **Countries to block** | Multi-select of countries (from your store's country list). Visitors detected as originating from these countries are blocked. |
| **What to do with blocked visitors** | `403` / custom page / redirect, plus the message, HTML content or URL for the chosen action. |
| **IP2Location download token** | Your download token (see *Updating the database* below) used by the automatic download button. Stored in the store database. |
| **Database file code** | Select the database to download: `DB1BIN` (commercial IP Country database) or `DB1LITEBIN` (free LITE IP Country database). |
| **Test** | Shows the country detected for the IP you are viewing the admin from, so you can confirm the database works before enabling blocking. |

## How it works

The plugin registers a single hook on the `controller.index` trigger, which
fires on every storefront request after the session is loaded but **before** the
page is rendered. When a request is made:

1. The visitor's IP is resolved (`REMOTE_ADDR`, or `X-Forwarded-For` if
   enabled).
2. Depending on the **IP lookup source** setting, the ISO country code is
   resolved either from the local BIN database (using the bundled
   `IP2Location-PHP-Module` library) or from the IP2Location.io web API (using
   the bundled `ip2location-io-php` SDK and your API key). The result is
   cached in the visitor's session, so the source is only queried once per
   visitor.
3. If the country is on the block list, the configured action is executed and
   the request ends immediately.

The admin panel uses a different controller (`controller.admin`), so admin
users are never affected by the block. Requests served from `api.php`, the CLI,
and static files (images, CSS, JS) are not processed by the storefront
controller and are not blocked.

## Block list semantics

* Blocking applies to the **storefront only**.
* Visitors whose IP cannot be resolved, or whose range has no country data,
  are **not** blocked (the store fails open so you can't lock yourself out).
* If the BIN database file is missing or unreadable, no blocking occurs and a
  notice is written to the CubeCart debug log.

> **Note on caching:** CubeCart caches SQL query results on the storefront.
> After changing the plugin settings in the admin, the storefront may keep
> serving the previously cached configuration until the cache expires. If you
> need a change to take effect immediately, clear the store's cache from
> **Extensions → Maintenance** (the "clear cache" option). This is standard
> CubeCart behaviour and applies to all extensions.

## Updating the database

There are two ways to update the BIN database:

**Automatic download (recommended).** Get a free download token from your
[IP2Location account](https://www.ip2location.com/account/) after registering
for the free LITE database, then on the plugin's configuration page:

1. Paste the token into the **IP2Location download token** field.
2. In **Database file code**, choose `DB1BIN` (commercial IP Country database)
   or `DB1LITEBIN` (free LITE IP Country database).
3. Click **Download / Update database**.

The plugin downloads from
`https://www.ip2location.com/download?token=...&file=...`, verifies the
archive, extracts the BIN and installs it into the folder of the configured
database path using the **original filename from the package** (for example
`IPV6-COUNTRY.BIN` for the IPv6 databases). The **IP2Location BIN database
path** setting is updated automatically to point at the newly installed file.
The existing database is only replaced after the downloaded archive opens as
a valid ZIP, contains a `.BIN`, and that file validates as a real IP2Location
BIN database (header + format check), so a bad download can never corrupt a
working database.

**Manual.** Download a fresh copy from
[https://lite.ip2location.com/database-download](https://lite.ip2location.com/database-download)
(LITE **DB1** is country-only; any newer DBn still contains the country code,
so larger editions also work). Upload it anywhere inside the store and set its
path on the plugin's configuration page, or simply replace the bundled file at
`modules/plugins/ip2location_country_blocker/data/`.

## Files

```
ip2location_country_blocker/
├── config.xml                 # Extension metadata
├── class.ip2location_country_blocker.php   # Core helper (detection + blocking)
├── hooks/controller.index.php # Storefront hook
├── admin/index.inc.php        # Admin configuration screen
├── skin/admin/index.tpl       # Admin screen template
├── language/                  # UI language strings
├── includes/                  # Bundled IP2Location PHP library (src/)
├── includes/ip2locationio/    # Bundled IP2Location.io PHP SDK
└── data/                      # Sample IP2Location LITE DB1 BIN database
```

## Licence

This plugin is MIT licensed. The bundled IP2Location PHP module is MIT
licensed (see `includes/`). The bundled sample BIN database is the free
IP2Location LITE DB1 and is subject to the
[IP2Location LITE licence](https://www.ip2location.com/terms-and-conditions).
