# Changelog

All notable changes to this plugin should be appended here.

## [1.1.7] - 2026-03-27
- Removed the custom updater post-install hook that could interfere with WordPress core plugin replacement during updates.
- Updated the form tooltip to use only the existing chevron button and default the date to the viewer's local day.

## [1.1.6] - 2026-03-27
- Removed the expanded-result layover/change-planes separator text and divider lines because the message was not reliably accurate for through flights.
- Rebuilt and redeployed the widget bundle.

## [1.1.5] - 2026-03-26
- Fixed the plugin updater post-install hook to return the updated install result, preserving the correct plugin destination after upgrades.
- Prepared a fresh manual reinstall package for recovering sites affected by the broken update flow.

## [1.1.4] - 2026-03-26
- Changed analytics pagination to client-side controls so page changes do not reload the WordPress admin page.
- Preserved the active admin tab using the URL hash, keeping the Analytics tab active during pagination.

## [1.1.3] - 2026-03-26
- Added pagination for analytics tables with 10 items per page by default.
- Placed Daily Usage and Top Searched Routes in a two-column layout on wider screens.

## [1.1.2] - 2026-03-26
- Added a manual `Check for updates` link to the WordPress plugin row.
- Added a secure admin action to clear updater cache and force a fresh GitHub update check.
- Added admin notices to report whether a newer GitHub release is available.

## [1.1.1] - 2026-03-26
- Added custom analytics database tables: `fst_daily_stats`, `fst_searches`, and `fst_route_counts`.
- Added automatic migration to backfill the new tables from the legacy `fst_stats` option.
- Switched analytics reads and writes to the custom tables while keeping the legacy option as fallback data.

## [1.1.0] - 2026-03-26
- Added a WordPress plugin updater wired to GitHub releases for `jkhliffz09/flight-schedule-timetable`.
- Added the `Update URI` plugin header and a GitHub release metadata fetcher.
- Added `scripts/release.sh` to bump versions, build assets, package the plugin zip, commit, tag, push, and create a GitHub release.

## [1.2.0] - 2026-02-20
- Added GitHub updater class for WordPress plugin update checks.
- Added `Update URI` plugin header for external update source.
- Wired update checks to GitHub latest release (`jkhliffz09/flight-schedule-timetable`).
- Initialized and pushed repository to GitHub.

## [1.1.0] - 2026-02-20
- Added admin dashboard with tabs: KPI, Analytics, Settings.
- Added settings fields for API URL and subscription key.
- Integrated timetable API request/response handling based on `api.pdf` format.
- Added AJAX search endpoint and frontend integration.
- Added analytics counters for searches, success/fail, routes, and response time.

## [1.0.0] - 2026-02-20
- Initial plugin scaffold and shortcode rendering.
- Added TailwindCSS and Gridicons integration.
- Implemented desktop and mobile search forms from provided layout.
- Implemented loading animation with moving plane and pulse effect.
- Implemented specific-day and 7-day result layouts.
- Implemented expandable result details.
- Added old-form fields as advanced filters.
