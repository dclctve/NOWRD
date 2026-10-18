# Security policy

## Reporting a vulnerability

Please do not publish exploitable details or real credentials in a public issue. Until a private security contact is configured for this repository, report only a minimal description and ask the maintainer for a private reporting channel.

## Deployment baseline

- Keep `config.php`, backups, SQL exports, and production media out of source control.
- Remove `install.php` from the live site after installation.
- Use HTTPS, least-privilege database credentials, and current supported PHP/MySQL versions.
- Test upgrades and restores on staging before changing production.
- The included security checks are a defensive baseline, not a formal audit or certification.
