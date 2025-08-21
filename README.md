![Banner](./docs/img/banner.png)

# Cleaver for Craft CMS

Working with large production databases in development can be slow and cumbersome. Chop your data down to size with Cleaver - a developer utility for trimming entry datasets in non‑production environments.

Cleaver deletes a percentage of entries from selected sections while preserving status distribution, with safeguards and a dry‑run mode.

- Control Panel utility for a guided run
- CLI command for scripts and automation
- Environment lock (only runs in allowed environments)
- Minimum‑entries guard per section
- Adjustable logging verbosity

## Requirements

This plugin requires Craft CMS 5.0.0 or later, and PHP 8.2 or later.

## Installation

You can install Cleaver by searching for “Cleaver” in the Craft Plugin Store, or install manually using composer.
```bash
composer require tallowandsons/craft-cleaver
```

## Configuration

Configure via Control Panel (Settings → Plugins → Cleaver) or with a config file at `config/cleaver.php`.

Settings (defaults in parentheses):

- defaultPercent (90) — Percent of entries to delete when none specified (1–100).
- minimumEntries (1) — Minimum entries to keep per section
- defaultStatuses (["live"]) — Default statuses to target: live, pending, expired, disabled. Use `all` to include all.
- defaultSections ([]) — Default section handles to target when none specified. Use `all` to include all.
- allowedEnvironments (["dev","staging","local"]) — Only these environments can run Cleaver
- batchSize (50) — Queue batch size per job
- defaultDeleteMode ("soft") — "soft" (restorable) or "hard" (permanent)
- logLevel ("info") — "none", "info", or "verbose"
- enableUtility (true) — Show the CP utility

Example `config/cleaver.php`:

```php
<?php
return [
	'defaultPercent' => 90,
	'minimumEntries' => 1,
	'defaultStatuses' => ['live'], // or 'all'
	'defaultSections' => [],       // or 'all'
	'allowedEnvironments' => ['dev', 'staging', 'local'],
	'batchSize' => 50,
	'defaultDeleteMode' => 'soft', // 'hard' to permanently delete
	'logLevel' => 'info',          // 'none' | 'info' | 'verbose'
	'enableUtility' => true,
];
```

## Using the Control Panel utility

Utilities → Cleaver:

1. Choose sections and statuses (defaults apply if you leave them blank).
2. Set the percent and minimum entries to keep.
3. Pick delete mode (soft/hard) and optionally enable Dry Run.
4. Confirm the current environment to proceed.

Jobs are queued and processed in the background. Dry Run audits and logs what would happen without deletion.

## CLI usage

Command:

```bash
php craft cleaver/chop/entries [options]
```

Options:

- `--sections|-s=blog,news|all` — Section handles or `all` (default: all)
- `--statuses|-st=live,disabled|all` — Statuses or `all` (default: from settings)
- `--percent|-p=25` — Percent to delete (default: from settings)
- `--min-entries|-m=5` — Minimum entries to keep per section (default: from settings)
- `--dry-run|-d` — Log planned deletions only
- `--skip-confirm|-y` — Skip interactive confirmation
- `--verbose|-v` — Extra console output

Notes:

- Delete mode (soft vs hard) comes from plugin settings.
- Defaults (`defaultSections`, `defaultStatuses`, etc.) are applied when a flag isn’t provided.
- The environment lock prevents execution unless the current environment is allowed.

Examples:

```bash
# Dry‑run: remove 25% from “blog” section while keeping at least 5 entries
php craft cleaver/chop/entries -s=blog -p=25 -m=5 -d

# Target multiple sections and statuses
php craft cleaver/chop/entries -s=blog,news -st=live,disabled -p=40 -y
```

## Safety

- Environment lock: Only runs when `CRAFT_ENVIRONMENT` (or `ENVIRONMENT`) matches `allowedEnvironments`.
- Confirmation: CP utility requires explicit environment confirmation; CLI prompts unless `--skip-confirm` is set.
- Minimum entries: Ensures at least a configured number remain per section.
- Dry Run: Produces the plan without deleting anything.

## Logging

Control output via `logLevel`:

- `none`: no plugin logs
- `info`: key events
- `verbose`: detailed progress (includes debug messages)

Logs are emitted under the `cleaver.*` category in Craft logs (see `storage/logs`).

## How it works

Cleaver partitions entries by section and status, calculates how many to remove based on the configured percent, respects the minimum‑entries guard, selects entries randomly, and queues jobs for deletion (or audit when Dry Run is enabled).

## Issues

Report problems or ideas:
https://github.com/tallowandsons/craft-cleaver/issues

## Credits

Made with care and attention by [Tallow &amp; Sons](https://github.com/tallowandsons)
