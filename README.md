# vc_safety_check

TYPO3 extension that checks **all installed packages** – TYPO3 core packages,
extensions and their transitive Composer dependencies – 
against an vulnerability database (typo3-status.com/typo3-api),
stores the findings in the database and shows them in a dashboard widget.

## Features

- One scan process (`SafetyCheckService`) used by **both** trigger points, so
  CLI/scheduler and the widget button always produce identical results.
- **Scheduler / CLI**: schedulable Symfony console command `vc_safety_check:check`.
- **TYPO3 version check** against an API (typo3-status.com/typo3-api)
  is a newer patch level available in the installed branch, and is the branch
  still within active support / security support / ELTS?
- **Dashboard widget** "T3 Safety Check" with:
  - the TYPO3 version status
  - a colour-coded table sorted by severity,
  - a **"Run check now"** button
 
## Requirements / compatibility

- PHP ≥ 8.1
- TYPO3 **12.4, 13.4 and 14.x**
  Only stable, cross-version APIs are used: Symfony console commands
  (`schedulable`), the dashboard `WidgetInterface`/`JavaScriptInterface`/
  `AdditionalCssInterface`, backend AJAX routes, the ConnectionPool and the
  Composer runtime API. The widget body is rendered from Fluid templates in
  `Resources/Private/`; `Vcat\Vcsafetycheck\View\TemplateRenderer` uses the
  `ViewFactoryInterface` on v13.3+/v14 and falls back to `StandaloneView` on
  v12, which is the only version where that class is still available.

> Composer mode is recommended. In classic (non-Composer) installations the
> package list falls back to the TYPO3 `PackageManager` (extensions only).

## Installation

```bash
composer require vcat/vc-safety-check
vendor/bin/typo3 extension:setup
vendor/bin/typo3 database:updateschema
```

## Usage

Run a scan from the CLI:

```bash
vendor/bin/typo3 vc_safety_check:check
```

As a scheduler task: create a task for the command `vc_safety_check:check`,
or use the widget's "Run check now" button.

Add the widget "T3 Safety Check" to a backend dashboard.

## Data

Findings are stored in `tx_vcsafetycheck_vulnerability`.
Each run stamps `last_seen`. Entries not seen in the latest run
(package upgraded/removed or advisory withdrawn) are deleted,
so the table always reflects the current state.

The endoflife.date payload is cached in `sys_registry`
as `vc_safety_check/versionReleases` for 24 hours.
A full scan always refreshes it.
The widget only fetches when the cache is stale, and falls back to the last
known payload when the API is unreachable.
