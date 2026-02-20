import React, { useMemo, useState } from "https://esm.sh/react@18.3.1";
import { createRoot } from "https://esm.sh/react-dom@18.3.1/client";
import htm from "https://esm.sh/htm@3.1.1";
import * as Griddy from "https://esm.sh/griddy-icons@0.1.1";

const html = htm.bind(React.createElement);

const defaults = {
  apiUrl: "https://passrider.prod.flightlookup.com/v1/xml/TimeTable/",
  subscriptionKey: "ee573326b2c34c619eadfff56300ba16",
  from: "MNL",
  to: "HKG",
  date: new Date().toISOString().slice(0, 10),
  sevenDay: false,
  connection: "AUTO",
  count: 20,
  sort: "Departure",
  time: "ANY"
};

const params = new URLSearchParams(window.location.search);
const bootConfig = {
  ...defaults,
  apiUrl: params.get("apiUrl") || defaults.apiUrl,
  subscriptionKey: params.get("key") || defaults.subscriptionKey,
  from: params.get("from") || defaults.from,
  to: params.get("to") || defaults.to,
  date: params.get("date") || defaults.date
};

function Icon({ name, className = "", size = 20 }) {
  const Comp = Griddy[name] || Griddy.Airplane;
  return html`<${Comp} size=${size} className=${className} />`;
}

function extractIata(value) {
  const input = String(value || "").toUpperCase();
  const withParens = input.match(/\(([A-Z]{3})\)/);
  if (withParens) return withParens[1];
  const plain = input.match(/\b([A-Z]{3})\b/);
  return plain ? plain[1] : "";
}

function toYmd(value) {
  const raw = String(value || "").replace(/\//g, "-");
  const dt = new Date(raw + "T00:00:00");
  if (Number.isNaN(dt.getTime())) return "";
  const y = dt.getUTCFullYear();
  const m = String(dt.getUTCMonth() + 1).padStart(2, "0");
  const d = String(dt.getUTCDate()).padStart(2, "0");
  return `${y}${m}${d}`;
}

function durationToLabel(iso) {
  const m = String(iso || "").match(/^PT(?:(\d+)H)?(?:(\d+)M)?$/);
  if (!m) return "N/A";
  const h = Number(m[1] || 0);
  const min = Number(m[2] || 0);
  return `${String(h).padStart(2, "0")}h ${String(min).padStart(2, "0")}min`;
}

function formatDateLabel(isoLike) {
  const d = new Date(isoLike);
  if (Number.isNaN(d.getTime())) return isoLike || "";
  return d.toLocaleDateString(undefined, { weekday: "short", month: "short", day: "numeric" });
}

function formatTimeLabel(isoLike) {
  const d = new Date(isoLike);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", hour12: false });
}

async function fetchFlights(config) {
  const from = extractIata(config.from);
  const to = extractIata(config.to);
  const ymd = toYmd(config.date);
  if (!from || !to || !ymd) {
    throw new Error("From, To, and Date are required.");
  }

  const url = new URL(`${config.apiUrl.replace(/\/$/, "")}/${from}/${to}/${ymd}/`);
  url.searchParams.set("7Day", config.sevenDay ? "Y" : "N");
  url.searchParams.set("Connection", config.connection);
  url.searchParams.set("Count", String(config.count));
  url.searchParams.set("TRC", "N");
  url.searchParams.set("Compression", "ALL");
  url.searchParams.set("Sort", config.sort);
  url.searchParams.set("Time", config.time);
  url.searchParams.set("Interline", "N");
  url.searchParams.set("CodeShare", "N");
  url.searchParams.set("subscription-key", config.subscriptionKey);

  const res = await fetch(url.toString(), {
    method: "GET",
    headers: { Accept: "application/xml,text/xml,*/*" }
  });

  if (!res.ok) {
    throw new Error(`API request failed (${res.status})`);
  }

  const text = await res.text();
  const doc = new DOMParser().parseFromString(text, "application/xml");
  const details = [...doc.getElementsByTagNameNS("*", "FlightDetails")];

  return details.map((node, idx) => {
    const depIso = node.getAttribute("FLSDepartureDateTime") || "";
    const arrIso = node.getAttribute("FLSArrivalDateTime") || "";
    const flightType = (node.getAttribute("FLSFlightType") || "").toLowerCase();
    const legs = Number(node.getAttribute("FLSFlightLegs") || 1);
    const firstLeg = node.getElementsByTagNameNS("*", "FlightLegDetails")[0];
    const carrier = firstLeg?.getElementsByTagNameNS("*", "MarketingAirline")[0];

    let stops = "Direct";
    if (flightType !== "nonstop") {
      const count = Math.max(0, legs - 1);
      stops = `${count} stop${count === 1 ? "" : "s"}`;
    }

    return {
      id: `f-${idx + 1}`,
      dep: `${formatTimeLabel(depIso)} ${node.getAttribute("FLSDepartureCode") || ""}`.trim(),
      arr: `${formatTimeLabel(arrIso)} ${node.getAttribute("FLSArrivalCode") || ""}`.trim(),
      depDate: formatDateLabel(depIso),
      arrDate: formatDateLabel(arrIso),
      duration: durationToLabel(node.getAttribute("TotalFlightTime") || ""),
      stops,
      airline: carrier?.getAttribute("CompanyShortName") || carrier?.getAttribute("Code") || "Unknown Airline",
      flightDays: node.getAttribute("FLSFlightDays") || "",
      price: null
    };
  });
}

function App() {
  const [form, setForm] = useState(bootConfig);
  const [phase, setPhase] = useState("idle");
  const [error, setError] = useState("");
  const [flights, setFlights] = useState([]);
  const [expanded, setExpanded] = useState(null);

  const dayRibbon = useMemo(() => {
    const start = new Date(form.date + "T00:00:00");
    return Array.from({ length: 7 }).map((_, i) => {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      return d.toLocaleDateString(undefined, { day: "numeric", month: "short" });
    });
  }, [form.date]);

  const onSubmit = async (e) => {
    e.preventDefault();
    setPhase("loading");
    setError("");
    setExpanded(null);

    try {
      const result = await fetchFlights(form);
      setFlights(result);
      setPhase("done");
      if (!result.length) setError("No flights found for this route/date.");
    } catch (err) {
      setFlights([]);
      setPhase("done");
      setError(err.message.includes("Failed to fetch")
        ? "Request blocked (likely CORS). Use a server-side proxy for production embedding."
        : err.message
      );
    }
  };

  const setField = (name, value) => setForm((s) => ({ ...s, [name]: value }));

  return html`
    <main className="fst-bg min-h-screen text-slate-100">
      <div className="mx-auto max-w-6xl p-4 md:p-8">
        <section className="fst-glass rounded-3xl border border-white/10 p-4 shadow-soft md:p-6">
          <div className="mb-5 flex items-center justify-between gap-3">
            <h1 className="text-2xl font-bold tracking-tight md:text-3xl">Flight Schedule</h1>
            <span className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-200">React + Tailwind + Griddy</span>
          </div>

          <form className="grid gap-3 md:grid-cols-12" onSubmit=${onSubmit}>
            <label className="md:col-span-4">
              <span className="mb-1 flex items-center gap-2 text-xs uppercase tracking-wider text-slate-300"><${Icon} name="Airplane" size=${14} />From</span>
              <input className="w-full rounded-xl border border-white/10 bg-slate-900/70 px-3 py-3 text-sm outline-none ring-cyan-400/70 transition focus:ring" value=${form.from} onInput=${(e) => setField("from", e.target.value)} placeholder="MNL or Manila (MNL)" />
            </label>

            <div className="md:col-span-1 flex items-end justify-center pb-1">
              <button type="button" className="rounded-xl border border-white/10 bg-slate-900/60 p-3 transition hover:bg-slate-800/80" onClick=${() => setForm((s) => ({ ...s, from: s.to, to: s.from }))}>
                <${Icon} name="SwapHorizontal" size=${18} className="text-cyan-200" />
              </button>
            </div>

            <label className="md:col-span-4">
              <span className="mb-1 flex items-center gap-2 text-xs uppercase tracking-wider text-slate-300"><${Icon} name="AirplaneAlt02" size=${14} />To</span>
              <input className="w-full rounded-xl border border-white/10 bg-slate-900/70 px-3 py-3 text-sm outline-none ring-cyan-400/70 transition focus:ring" value=${form.to} onInput=${(e) => setField("to", e.target.value)} placeholder="HKG or Hong Kong (HKG)" />
            </label>

            <label className="md:col-span-3">
              <span className="mb-1 flex items-center gap-2 text-xs uppercase tracking-wider text-slate-300"><${Icon} name="CalendarDate" size=${14} />Departure</span>
              <input type="date" className="w-full rounded-xl border border-white/10 bg-slate-900/70 px-3 py-3 text-sm outline-none ring-cyan-400/70 transition focus:ring" value=${form.date} onInput=${(e) => setField("date", e.target.value)} />
            </label>

            <label className="md:col-span-2">
              <span className="mb-1 text-xs uppercase tracking-wider text-slate-300">Connection</span>
              <select className="w-full rounded-xl border border-white/10 bg-slate-900/70 px-3 py-3 text-sm" value=${form.connection} onInput=${(e) => setField("connection", e.target.value)}>
                <option value="AUTO">Auto</option>
                <option value="NONSTOP">Nonstop</option>
                <option value="1STOP">1 Stop</option>
                <option value="MORE">More</option>
              </select>
            </label>

            <label className="md:col-span-2">
              <span className="mb-1 text-xs uppercase tracking-wider text-slate-300">Sort</span>
              <select className="w-full rounded-xl border border-white/10 bg-slate-900/70 px-3 py-3 text-sm" value=${form.sort} onInput=${(e) => setField("sort", e.target.value)}>
                <option value="Departure">Departure</option>
                <option value="Arrival">Arrival</option>
                <option value="Duration">Duration</option>
                <option value="Flights">Flights</option>
              </select>
            </label>

            <label className="md:col-span-2">
              <span className="mb-1 text-xs uppercase tracking-wider text-slate-300">Time</span>
              <select className="w-full rounded-xl border border-white/10 bg-slate-900/70 px-3 py-3 text-sm" value=${form.time} onInput=${(e) => setField("time", e.target.value)}>
                <option value="ANY">Any</option>
                <option value="AM">AM</option>
                <option value="PM">PM</option>
                <option value="NIGHT">Night</option>
              </select>
            </label>

            <label className="flex items-center gap-2 md:col-span-2 md:pt-6">
              <input type="checkbox" checked=${form.sevenDay} onChange=${(e) => setField("sevenDay", e.target.checked)} className="h-4 w-4 rounded border-white/20 bg-slate-900/80" />
              <span className="text-sm text-slate-200">7-day lookup</span>
            </label>

            <button className="group md:col-span-3 inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-400 to-emerald-400 px-5 py-3 text-sm font-bold text-slate-950 transition hover:scale-[1.01]">
              <${Icon} name="Search" size=${16} className="transition group-hover:rotate-6" />
              Search Flights
            </button>

            <details className="md:col-span-12 rounded-xl border border-white/10 bg-slate-900/40 p-3">
              <summary className="cursor-pointer text-sm text-slate-300">API Settings</summary>
              <div className="mt-3 grid gap-3 md:grid-cols-2">
                <label>
                  <span className="mb-1 block text-xs uppercase tracking-wider text-slate-400">API URL</span>
                  <input className="w-full rounded-xl border border-white/10 bg-slate-900/70 px-3 py-2 text-sm" value=${form.apiUrl} onInput=${(e) => setField("apiUrl", e.target.value)} />
                </label>
                <label>
                  <span className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Subscription Key</span>
                  <input className="w-full rounded-xl border border-white/10 bg-slate-900/70 px-3 py-2 text-sm" value=${form.subscriptionKey} onInput=${(e) => setField("subscriptionKey", e.target.value)} />
                </label>
              </div>
            </details>
          </form>
        </section>

        ${phase === "loading" && html`
          <section className="fst-glass mt-6 rounded-3xl border border-white/10 p-4 md:p-6">
            <p className="mb-4 text-lg font-semibold">Searching flights...</p>
            <div className="fst-loading-track">
              <span className="fst-loading-line"></span>
              <span className="fst-pulse-dot fst-pulse-dot-start"></span>
              <span className="fst-pulse-dot fst-pulse-dot-end"></span>
              <span className="fst-plane"><${Icon} name="Airplane" size=${38} /></span>
            </div>
          </section>
        `}

        ${phase === "done" && html`
          <section className="fst-glass mt-6 rounded-3xl border border-white/10 p-4 md:p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
              <h2 className="text-xl font-bold">${flights.length} Flights Found</h2>
              ${error && html`<p className="rounded-lg border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-xs text-amber-200">${error}</p>`}
            </div>

            <div className="fst-scrollbar mb-5 overflow-x-auto">
              <div className="grid min-w-[820px] grid-cols-7 gap-2">
                ${dayRibbon.map((d, i) => html`
                  <button key=${d} className=${`rounded-xl border px-3 py-2 text-sm transition ${i === 0 ? "border-cyan-300/80 bg-cyan-400/15 text-cyan-100" : "border-white/10 bg-slate-900/50 text-slate-300 hover:border-cyan-300/40"}`}>
                    ${d}
                  </button>
                `)}
              </div>
            </div>

            <div className="space-y-3">
              ${flights.map((f) => html`
                <article key=${f.id} className="rounded-2xl border border-white/10 bg-slate-900/50">
                  <button className="grid w-full gap-4 p-4 text-left md:grid-cols-[1fr_auto]" onClick=${() => setExpanded((x) => (x === f.id ? null : f.id))}>
                    <div>
                      <div className="mb-2 flex flex-wrap gap-2">
                        <span className="rounded-full border border-cyan-300/30 bg-cyan-300/10 px-2 py-1 text-[11px] font-semibold text-cyan-200">${f.stops}</span>
                        ${f.flightDays && html`<span className="rounded-full border border-fuchsia-300/30 bg-fuchsia-300/10 px-2 py-1 text-[11px] font-semibold text-fuchsia-200">Days ${f.flightDays}</span>`}
                      </div>
                      <div className="grid gap-3 sm:grid-cols-4">
                        <div><p className="text-lg font-bold">${f.dep}</p><p className="text-xs text-slate-400">${f.depDate}</p></div>
                        <div><p className="text-sm font-semibold text-slate-200">${f.duration}</p><p className="text-xs text-slate-400">${f.stops}</p></div>
                        <div><p className="text-lg font-bold">${f.arr}</p><p className="text-xs text-slate-400">${f.arrDate}</p></div>
                        <div><p className="text-sm font-semibold text-slate-200">${f.airline}</p><p className="text-xs text-slate-400">Flight schedule only</p></div>
                      </div>
                    </div>
                    <div className="flex items-center gap-3">
                      <p className="text-2xl font-extrabold text-cyan-200">N/A</p>
                      <${Icon} name="ChevronDown" className=${`transition ${expanded === f.id ? "rotate-180" : ""}`} />
                    </div>
                  </button>

                  ${expanded === f.id && html`
                    <div className="border-t border-white/10 px-4 pb-4 pt-3 text-sm text-slate-300">
                      <p className="mb-2">Live timetable data returned from FlightLookup API.</p>
                      <p className="text-xs text-slate-400">Pricing is not included in this timetable endpoint.</p>
                    </div>
                  `}
                </article>
              `)}
            </div>
          </section>
        `}
      </div>
    </main>
  `;
}

createRoot(document.getElementById("fst-app")).render(html`<${App} />`);
