# Glue Backoffice for Laravel

Reports a Laravel app to the Glue Dashboard after each deploy: its name, Laravel version, PHP version and the Composer packages it requires. The Laravel counterpart of `glue-agency/craft-glue-backoffice` and `glue-agency/m2-glue-backoffice`.

## Installation

    composer require glue-agency/laravel-glue-backoffice

The service provider is discovered on its own. Requires Laravel 8 or later and PHP 7.3 or later.

## Configuration

Two `.env` values, read through `config/glue-backoffice.php`:

| Variable | |
|---|---|
| `GLUE_BACKOFFICE_TOKEN` | The install's own reporting token, issued on its page on the Dashboard. |
| `GLUE_BACKOFFICE_URL` | The Dashboard. Defaults to `https://dashboard.glue.be/`; only set it to try a report against another one. |

Only production reports: the command does nothing unless `APP_ENV` is `production`. Set the token on the production host's `.env` and nowhere else: a staging host that also runs as `production` would report with it, and file its versions as live's.

The config is only ever read from `config/glue-backoffice.php`, never from `env()` directly, so a cached config works as usual: add the token to the host's `.env` before the deploy that caches it. To change the defaults in the app itself:

    php artisan vendor:publish --tag=glue-backoffice-config

## Reporting

    php artisan glue:backoffice:report --repository-name=git@bitbucket.org:glue-team/project.git

`--repository-name` is optional: without it, the repository is left out of the report.

`--dry-run` prints the report as JSON and sends nothing. It needs no token, keeps no uid and works in every environment, so it is the way to check what an app would report on any host, a developer's machine included.

The command reports nothing, says why, and exits cleanly when:

- the app runs in any environment but `production` (`Running in the staging environment, not reporting: only production reports.`);
- no token is configured (`No Glue Dashboard token configured, not reporting.`).

When the Dashboard refuses the report it prints every message it sent back, which tell the deployer what to do, and exits with 1. So does a Dashboard that can't be reached. Hooked in after `deploy:symlink`, that fails the deploy after the release is already live, as the Craft and Magento reporters do.

What is reported:

- the app's name (`APP_NAME`), Laravel version and PHP version;
- the Composer packages the app requires directly — its root `composer.json`'s `require`, not its whole lock file, and not `require-dev` — with the installed version and description; Laravel itself is among them as `laravel/framework`, and platform requirements (`php`, `ext-*`) are left out;
- the repository, when given.

Every value is cut to 255 characters, the most the Dashboard keeps.

The report carries a uid, a UUID made on the first report and kept in `storage/app/glue-backoffice-uid`. Deployer shares `storage` between releases, so it stays the same from one deploy to the next. The Dashboard knows the app by its token, not by this uid: an app on a fresh `storage` reports a new uid and its own token accepts it, and a uid that can't be written is no reason to fail a deploy.

### Onboarding an app

The Dashboard has to know the Laravel platform first (the release that adds platforms, Oct 2026).

1. On the Dashboard, add the app by hand (platform **Laravel**, its domain and current version), or use the install its brand already has: a Laravel app beside a Craft site is one install, and both report with that install's one token.
2. Switch the install to **automatic** and issue it a reporting token. A token can't be read back once issued: if another platform of the install already reports with it, issue a new one and give it to every platform.
3. On the app's production host only, add `GLUE_BACKOFFICE_TOKEN=<token>` to the shared `.env`.
4. Require this package, hook the report into the deploy (below) and deploy. The app's first report fills in its Laravel section and its packages.

### Deployer

From a Deployer 7 `deploy.php` that uses `recipe/laravel.php`:

```php
desc('Report to the Glue Dashboard');
task('glue:backoffice_report', function () {
    run('{{bin/php}} {{release_or_current_path}}/artisan glue:backoffice:report --repository-name={{repository}}');
});

after('deploy:symlink', 'glue:backoffice_report');
```

On Deployer 6, which has no `{{release_or_current_path}}`, run `{{deploy_path}}/current/artisan` instead.

## Testing

    composer install
    vendor/bin/phpunit
