# Release Notes

## 1.1.0 - Every environment reports
- Staging, dev and any other environment report too, named by `APP_ENV`: only where the app answers there (`APP_URL`), which the Dashboard lists beside live's. Live's report is unchanged, plus its environment and its URL
- Without a token, the command names the environment it skipped
- Every report says which environment it came from and prints every value it sent
- Needs a Dashboard that knows environments (Nov 2026): an older one refuses a report from anywhere but live

## 1.0.0 - Initial release
- Report a Laravel app to the Glue Dashboard after each deploy: its name, Laravel and PHP versions, and the Composer packages it requires directly
- `--dry-run` prints the report without sending it
- Laravel 8 to 13, PHP 7.3 and later
