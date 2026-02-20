# Flight Schedule Timetable (Embeddable Web Widget)

Modern ReactJS + TailwindCSS + Griddy Icons flight timetable widget.

## Stack
- React (browser ESM)
- TailwindCSS
- Griddy Icons (`griddy-icons`)

## Why this is safe to embed
This widget is embedded via `iframe`, so its styles and animations are isolated from any WordPress theme or host site CSS.

## Files
- `web/index.html` - widget app
- `web/app.js` - React app logic
- `web/styles.css` - custom animation styles
- `web/embed.js` - embeddable loader script

## Host it
Deploy the `web/` folder to any static host (Cloudflare Pages, Netlify, Vercel, S3, etc).

Example hosted URL:
- `https://your-domain.com/flight-widget`

Then your widget main page will be:
- `https://your-domain.com/flight-widget/index.html`

## Embed in any website
```html
<script
  src="https://your-domain.com/flight-widget/embed.js"
  data-host="https://your-domain.com/flight-widget"
  data-height="860"
  data-from="MNL"
  data-to="HKG"
></script>
```

Optional attributes:
- `data-target="#someContainer"` (mount iframe into an existing element)
- `data-apiurl="https://passrider.prod.flightlookup.com/v1/xml/TimeTable/"`
- `data-key="your-subscription-key"`
- `data-date="2026-02-20"`

## API notes
- The timetable endpoint returns schedule data, not fare pricing.
- If browser requests are blocked by CORS, use a backend proxy and point `data-apiurl` to your proxy endpoint.

## Loading animation
Implemented as requested:
- start pulse circle
- line
- airplane icon moving across line
- end pulse circle
