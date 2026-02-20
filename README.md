# Flight Schedule Timetable

WordPress plugin for flight search UI, timetable results, API integration, and admin analytics.

## Plugin Info
- Name: Flight Schedule Timetable
- Author: khliffz
- Current Version: 1.2.0
- GitHub: https://github.com/jkhliffz09/flight-schedule-timetable

## Features
- TailwindCSS + Gridicons powered UI
- Desktop and mobile flight search form
- Loading animation and expandable result cards
- Specific-day and 7-day timetable views
- Admin dashboard tabs: KPI, Analytics, Settings
- API settings for URL + subscription key
- GitHub release-based plugin updater

## Settings
In WordPress Admin:
1. Go to `Flight Timetable`.
2. Open `Settings` tab.
3. Set API URL and Subscription Key.

Example values:
- URL: `https://passrider.prod.flightlookup.com/v1/xml/TimeTable/`
- Subscription Key: `ee573326b2c34c619eadfff56300ba16`

## Usage
Add shortcode to any page/post:

```text
[flight_schedule_timetable]
```

## Version Log (Append On Every Update)
Always append a new version section at the top of `CHANGELOG.md` for each update.

Current history summary:
- 1.2.0: Added GitHub updater integration and update metadata.
- 1.1.0: Added admin dashboard (KPI, Analytics, Settings) and timetable API integration.
- 1.0.0: Initial release (UI layouts, loading animation, 7-day/specific-day results, expandable cards).

Detailed log: see `CHANGELOG.md`.
