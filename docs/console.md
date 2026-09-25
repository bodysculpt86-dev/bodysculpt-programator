# Console

Easy!Appointments includes a set of command-line tools you can run from a terminal. These are useful for automating tasks like backups and database updates.

## Before You Start

Make sure PHP is installed and available in your terminal. You can check by running:

```
php -v
```

This should print the PHP version number. If it doesn't, you need to install PHP or add it to your system's PATH.

## Available Commands

All commands start with `php index.php console` and are run from the Easy!Appointments root folder.

### Migrate

Updates your database to the latest version:

```
php index.php console migrate
```

To reset the database and re-run all migrations from scratch:

```
php index.php console migrate fresh
```

> **Warning:** The `fresh` option deletes all existing data.

### Seed

Adds sample data (a test admin, provider, customer, and service) so you can try things out:

```
php index.php console seed
```

### Install

Runs the installer from the command line (instead of using the browser):

```
php index.php console install
```

Make sure you've already filled in your `config.php` file before running this.

### Backup

Creates a backup of your data in the `storage/backups` folder:

```
php index.php console backup
```

To save the backup to a different folder:

```
php index.php console backup /path/to/your/folder
```

### Sync

Syncs calendars for all providers who have calendar sync enabled:

```
php index.php console sync
```

**Tip:** Set this up as a [cron job](https://en.wikipedia.org/wiki/Cron) to run automatically (e.g. every hour) so your calendars stay up to date without manual work.

### Cleanup

Runs storage and data cleanup tasks:

```
php index.php console cleanup
```

This command removes expired sessions, old logs and cache files, and deletes old customer data based on your configured data retention settings.

**Tip:** Add this command to a [cron job](https://en.wikipedia.org/wiki/Cron) so cleanup runs automatically.

### Send Reminders

Sends reminders to customers whose appointment is scheduled for tomorrow (in the business timezone):

```
php index.php console send_sms_reminders
```

Each customer is reminded on two channels — SMS via SMSO.ro and WhatsApp via Flaxxa WAPI or the Graph API, whichever `WA_PROVIDER` selects. Appointments that are cancelled, draft or no-show are skipped.

The two channels are recorded separately, in `sms_reminder_sent_at` / `sms_reminder_error` and `wa_reminder_sent_at` / `wa_reminder_error`. An appointment is only flagged as reminded (`reminder_sent_at`) when at least one channel actually delivered, so a provider outage leaves it in the pending set to be retried by the next run instead of being silently marked as reminded.

Retries are capped at `REMINDER_MAX_ATTEMPTS` (3, in `application/config/constants.php`), tracked per appointment in `reminder_attempts`. Once an appointment has failed that many attempts on both channels, it drops out of the pending set for good — it will not be retried automatically, and it posts a single Telegram alert (on the attempt that hits the cap, not on every attempt) listing the appointment IDs and the distinct failure reasons; from there it needs manual follow-up. That needs `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_ID`; without them the alert is written to the application log instead.

Add `--dry-run` to preview exactly what a run would process — the recipients, appointment IDs and their current attempt count — without sending anything, writing to the database, or posting a Telegram alert:

```
php index.php console send_sms_reminders --dry-run
```

**Tip:** Run this command on a cron schedule. On Railway, set the `RAILWAY_CRON_COMMAND` environment variable to `php /var/www/html/index.php console send_sms_reminders` and configure a cron schedule.

### Telegram Test

Sends one alert through the Telegram channel, to prove the alerting above actually arrives:

```
php index.php console telegram_test
```

### Help

Shows a summary of all available commands:

```
php index.php console help
```

*This document applies to Easy!Appointments v1.6.0.*

[Back](readme.md)
