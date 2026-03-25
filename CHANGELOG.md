# Changelog

All notable changes to this plugin should be appended here.

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
