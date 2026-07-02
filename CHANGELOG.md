# Changelog

All notable changes to this app are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.0

Major release modernizing the app to current Nextcloud standards.

> **Breaking:** requires **Nextcloud 31+** and **PHP 8.1+**. Older Nextcloud and
> PHP versions are no longer supported.

### Added
- Self-service account reactivation: users auto-disabled for missing the
  email-verification window can recover their account without an admin (#154, closes #5).
- Support for the V3 login flow in the registration process (#151).
- PHPUnit unit-test suite with a sqlite/mysql/pgsql CI matrix (#159).
- Playwright end-to-end suite covering the full account lifecycle (#162).
- Nextcloud 33, 34 and 35 support (#138, #139, #153).

### Changed
- Modernized the PHP codebase: attribute-based routing and controllers,
  constructor property promotion, SPDX/REUSE headers, and psalm + php-cs-fixer
  + rector wired through composer-bin (#158, #161).
- Migrated app configuration to the typed `IAppConfig` API (#158).
- Rewrote `admin-settings.js` dependency-free so it works on Nextcloud 32+,
  where jQuery, underscore and `OC.linkToOCS` were dropped (#162).
- Updated CI actions and dev dependencies (#140–#148, #156, #157).

### Fixed
- The `ExpireUnverifiedAccounts` background job no longer crashes when expiring
  an account (missing `IUserManager` injection, and an undefined-property
  regression in the `pp_disabled` write) (#159, #162).
- Corrected the README link to the second repository (#149) and a company-name
  typo (#150).
- Fixed capitalization in the summary tag translation (#160).
