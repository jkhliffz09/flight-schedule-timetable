# Flight Schedule Timetable (Embeddable Web Widget)

Modern React flight timetable widget.

## Stack
- React
- Font Awesome (icons)
- Vite

## Project Structure (standard)
- `index.html`
- `src/main.jsx`
- `src/styles.css`
- `public/embed.js`

## Run locally
```bash
npm install
npm run dev
```
Open `http://localhost:5173`.

## Build
```bash
npm run build
npm run preview
```

## WordPress updater
The WordPress plugin now checks GitHub Releases for updates from `jkhliffz09/flight-schedule-timetable`. The updater expects each release to include the asset `flight-schedule-timetable-plugin.zip`.

## Release a new version
```bash
scripts/release.sh 1.1.1 "Release notes here"
```
This script will:
- bump the widget and plugin versions
- build the widget
- generate `flight-schedule-timetable-plugin.zip`
- commit the version change
- tag the repo
- push `main` and the tag
- create a GitHub release and upload the plugin zip

## Why this is safe to embed
The widget is embedded via `iframe`, so its styles and animations are isolated from WordPress/theme CSS.

## Embed in any website
```html
<script
  src="https://your-domain.com/embed.js"
  data-host="https://your-domain.com"
  data-height="860"
  data-from="MNL"
  data-to="HKG"
></script>
```

Optional attributes:
- `data-target="#someContainer"`
- `data-apiurl="https://services.flightlookup.com/v1/xml/TimeTable/"`
- `data-proxyurl="/wp-json/fst/v1/timetable"` (recommended for production)
- `data-key="your-subscription-key"`
- `data-date="2026-02-20"`

## API notes
- Timetable endpoint returns schedule data (not fares).
- If blocked by CORS in-browser, use a backend proxy and set `data-proxyurl`.

### WordPress proxy example (fixes CORS)
Add this in your theme/plugin:

```php
add_action('rest_api_init', function () {
  register_rest_route('fst/v1', '/timetable', [
    'methods' => 'GET',
    'permission_callback' => '__return_true',
    'callback' => function (WP_REST_Request $req) {
      $from = strtoupper(sanitize_text_field($req->get_param('from')));
      $to = strtoupper(sanitize_text_field($req->get_param('to')));
      $date = preg_replace('/[^0-9]/', '', (string) $req->get_param('date'));
      $connection = sanitize_text_field($req->get_param('connection') ?: 'AUTO');
      $count = (int) ($req->get_param('count') ?: 20);
      $sort = sanitize_text_field($req->get_param('sort') ?: 'Departure');
      $time = sanitize_text_field($req->get_param('time') ?: 'ANY');

      $api_base = rtrim('https://services.flightlookup.com/v1/xml/TimeTable/', '/');
      $api_key = 'YOUR_SUBSCRIPTION_KEY';

      $url = $api_base . '/' . rawurlencode($from) . '/' . rawurlencode($to) . '/' . rawurlencode($date) . '/?'
        . http_build_query([
          '7Day' => 'N',
          'Connection' => $connection,
          'Count' => $count,
          'TRC' => 'N',
          'Compression' => 'ALL',
          'Sort' => $sort,
          'Time' => $time,
          'Interline' => 'N',
          'CodeShare' => 'N',
          'subscription-key' => $api_key,
        ], '', '&', PHP_QUERY_RFC3986);

      $resp = wp_remote_get($url, [
        'timeout' => 20,
        'headers' => ['Accept' => 'application/xml,text/xml,*/*'],
      ]);

      if (is_wp_error($resp)) {
        return new WP_REST_Response('Proxy request failed', 502);
      }

      $code = wp_remote_retrieve_response_code($resp);
      $body = wp_remote_retrieve_body($resp);
      return new WP_REST_Response($body, $code, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
  ]);
});
```

Embed usage with proxy:

```html
<script
  src="https://your-domain.com/embed.js"
  data-host="https://your-domain.com"
  data-proxyurl="https://passrider.com/wp-json/fst/v1/timetable"
  data-from="MNL"
  data-to="HKG"
></script>
```

## Loading animation
Implemented:
- pulse circle (start)
- line
- airplane icon moving along line
- pulse circle (end)
