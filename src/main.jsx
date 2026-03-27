import React, { useEffect, useRef, useState } from "react";
import "./styles.css";
import { createRoot } from "react-dom/client";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import {
  faPlane,
  faJetFighterUp,
  faCalendarDays,
  faMagnifyingGlass,
  faRightLeft,
  faChevronDown,
  faChevronUp,
  faVideo,
  faPlug,
  faMugHot,
  faWineGlass,
  faMugSaucer,
  faPhone,
  faMusic,
  faTv,
  faCalendarCheck,
  faBagShopping,
  faBanSmoking,
  faWifi,
  faChair,
  faPrint,
  faEnvelope,
  faBed,
  faChevronRight,
  faChevronLeft,
  faCirclePlus,
  faCircleExclamation,
  faCircleQuestion,
  faCircle
} from "@fortawesome/free-solid-svg-icons";

function getLocalISODate() {
  const now = new Date();
  const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 10);
}

const defaults = {
  apiUrl: "https://services.flightlookup.com/v1/xml/TimeTable/",
  proxyUrl: "",
  emailUrl: "",
  subscriptionKey: "",
  result: "100",
  from: "",
  to: "",
  date: getLocalISODate(),
  connection: "AUTO",
  sort: "Departure",
  time: "ANY",
  airline: "",
  via: "",
  language: "en",
  nofilter: "Y",
  compression: "MOST",
  showCodeshare: "N",
  interline: "N",
  specificDate: "Y"
};
const LOADER_DURATION_MS = 1800;
const DAY_SWITCH_REOPEN_MS = 560;

const MEAL_CODE_MAP = {
  B: { label: "Breakfast", icon: "RestaurantMenu" },
  C: { label: "Alcoholic Beverages - Complimentary", icon: "CupStraw" },
  D: { label: "Dinner", icon: "RestaurantMenu" },
  F: { label: "Food for Purchase", icon: "RestaurantMenu" },
  G: { label: "Food and Beverages for Purchase", icon: "RestaurantMenu" },
  H: { label: "Hot Meal", icon: "RestaurantMenu" },
  K: { label: "Continental Breakfast", icon: "RestaurantMenu" },
  L: { label: "Lunch", icon: "RestaurantMenu" },
  M: { label: "Meal", icon: "RestaurantMenu" },
  N: { label: "No Meal Service", icon: "NoSmoking" },
  O: { label: "Cold Meal", icon: "RestaurantMenu" },
  P: { label: "Alcoholic Beverages for Purchase", icon: "CupStraw" },
  R: { label: "Refreshments - Complimentary", icon: "Cup" },
  S: { label: "Snack or Brunch", icon: "RestaurantMenu" },
  V: { label: "Refreshments for Purchase", icon: "Cup" }
};

const INFLIGHT_SERVICE_MAP = {
  "1": { label: "Movie", icon: "VideoCamera" },
  "2": { label: "Telephone", icon: "Telephone" },
  "3": { label: "Entertainment on demand", icon: "VideoCamera" },
  "4": { label: "Audio programming", icon: "MusicNote" },
  "5": { label: "Live TV", icon: "Tv" },
  "6": { label: "Reservation booking service", icon: "CalendarCheck" },
  "7": { label: "Duty Free sales", icon: "Bag" },
  "8": { label: "Smoking", icon: "Cigarette" },
  "9": { label: "Non-smoking", icon: "NoSmoking" },
  "10": { label: "Short Feature Video", icon: "VideoCamera" },
  "11": { label: "No Duty-Free sales", icon: "BagX" },
  "12": { label: "In-seat power source", icon: "Plug" },
  "13": { label: "Internet access", icon: "Wifi" },
  "14": { label: "Currently unused", icon: "QuestionCircle" },
  "15": { label: "In-seat Video Player/Library", icon: "VideoCamera" },
  "16": { label: "Lie-flat Seat", icon: "Seat" },
  "17": { label: "Additional Services", icon: "PlusCircle" },
  "18": { label: "Wi-Fi", icon: "Wifi" },
  "19": { label: "Lie-flat Seat First", icon: "Seat" },
  "20": { label: "Lie-flat Seat Business", icon: "Seat" },
  "21": { label: "Lie-flat Seat Premium Economy", icon: "Seat" },
  "22": { label: "110V AC Power", icon: "Plug" },
  "23": { label: "110V AC Power First", icon: "Plug" },
  "24": { label: "110V AC Power Business", icon: "Plug" },
  "25": { label: "110V AC Power Premium Economy", icon: "Plug" },
  "26": { label: "110V AC Power Economy", icon: "Plug" },
  "27": { label: "USB Power", icon: "Plug" },
  "28": { label: "USB Power First", icon: "Plug" },
  "29": { label: "USB Power Business", icon: "Plug" },
  "30": { label: "USB Power Premium Economy", icon: "Plug" },
  "31": { label: "USB Power Economy", icon: "Plug" },
  "99": { label: "Amenities subject to change", icon: "ExclamationCircle" }
};

const METROPOLITAN_AREAS = [
  { code: "LON", name: "London All Airports", country: "gb" },
  { code: "PAR", name: "Paris All Airports", country: "fr" },
  { code: "NYC", name: "New York City All Airports", country: "us" },
  { code: "CHI", name: "Chicago All Airports", country: "us" },
  { code: "WAS", name: "Washington All Airports", country: "us" },
  { code: "YTO", name: "Toronto All Airports", country: "ca" },
  { code: "YMQ", name: "Montreal All Airports", country: "ca" },
  { code: "BUE", name: "Buenos Aires All Airports", country: "ar" },
  { code: "RIO", name: "Rio de Janeiro All Airports", country: "br" },
  { code: "SAO", name: "Sao Paulo All Airports", country: "br" },
  { code: "ROM", name: "Rome All Airports", country: "it" },
  { code: "MIL", name: "Milan All Airports", country: "it" },
  { code: "TYO", name: "Tokyo All Airports", country: "jp" },
  { code: "OSA", name: "Osaka All Airports", country: "jp" },
  { code: "SEL", name: "Seoul All Airports", country: "kr" },
  { code: "BJS", name: "Beijing All Airports", country: "cn" },
  { code: "SHA", name: "Shanghai All Airports", country: "cn" },
  { code: "MOW", name: "Moscow All Airports", country: "ru" },
  { code: "STO", name: "Stockholm All Airports", country: "se" },
  { code: "CPH", name: "Copenhagen Area", country: "dk" }
];

const params = new URLSearchParams(window.location.search);
const bootConfig = {
  ...defaults,
  apiUrl: params.get("apiUrl") || defaults.apiUrl,
  proxyUrl: params.get("proxyUrl") || defaults.proxyUrl,
  emailUrl: params.get("emailUrl") || defaults.emailUrl,
  subscriptionKey: params.get("key") || defaults.subscriptionKey,
  result: params.get("result") || defaults.result,
  airline: params.get("airline") || defaults.airline,
  via: params.get("via") || defaults.via,
  from: params.get("from") || defaults.from,
  to: params.get("to") || defaults.to,
  date: params.get("date") || defaults.date
};

const assetUrl = (path) => {
  const base = new URL("./assets/", window.location.href);
  return new URL(String(path || "").replace(/^\/+/, ""), base).toString();
};

let lookupDataPromise = null;

function compactAirportLabel(label) {
  return String(label || "")
    .replace(/\bInternational Airport\b/gi, "")
    .replace(/\bInternational Airpo\b/gi, "")
    .replace(/\bInternational\b/gi, "")
    .replace(/\bAirport\b/gi, "")
    .replace(/\s{2,}/g, " ")
    .replace(/\s+\(/g, " (")
    .trim();
}

async function loadLookupData() {
  if (lookupDataPromise) return lookupDataPromise;

  lookupDataPromise = (async () => {
    try {
      const [airportsRes, equipmentRes, airlinesRes] = await Promise.all([
        fetch(assetUrl("airports.xml")),
        fetch(assetUrl("equipment.xml")),
        fetch(assetUrl("airlines.xml"))
      ]);
      if (!airportsRes.ok) throw new Error(`Failed to load airports.xml (${airportsRes.status})`);
      if (!equipmentRes.ok) throw new Error(`Failed to load equipment.xml (${equipmentRes.status})`);
      if (!airlinesRes.ok) throw new Error(`Failed to load airlines.xml (${airlinesRes.status})`);

      const [airportsXml, equipmentXml, airlinesXml] = await Promise.all([airportsRes.text(), equipmentRes.text(), airlinesRes.text()]);
      const airportsDoc = new DOMParser().parseFromString(airportsXml, "application/xml");
      const equipmentDoc = new DOMParser().parseFromString(equipmentXml, "application/xml");
      const airlinesDoc = new DOMParser().parseFromString(airlinesXml, "application/xml");

      const airportCountryByIata = new Map();
      const airportDisplayByIata = new Map();
      const airportSuggestions = [];
      const airportSuggestionSet = new Set();
      [...airportsDoc.getElementsByTagName("Airport")].forEach((node) => {
        const iata = (node.getAttribute("IATACode") || "").toUpperCase();
        const country = (node.getAttribute("Country") || "").toLowerCase();
        const name = (node.getAttribute("Name") || "").trim();
        if (iata && country) airportCountryByIata.set(iata, country);
        if (iata && name && !airportDisplayByIata.has(iata)) airportDisplayByIata.set(iata, name);
        if (iata) {
          const label = name ? `${name} (${iata})` : iata;
          const key = `${iata}|${label}`;
          if (!airportSuggestionSet.has(key)) {
            airportSuggestionSet.add(key);
            airportSuggestions.push({ code: iata, label });
          }
        }
      });

      METROPOLITAN_AREAS.forEach((metro) => {
        const code = String(metro.code || "").toUpperCase();
        const name = String(metro.name || "").trim();
        const country = String(metro.country || "").toLowerCase();
        if (!code || !name) return;

        if (!airportDisplayByIata.has(code)) {
          airportDisplayByIata.set(code, name);
        }
        if (country && !airportCountryByIata.has(code)) {
          airportCountryByIata.set(code, country);
        }

        const label = `${name} (${code})`;
        const key = `${code}|${label}`;
        if (!airportSuggestionSet.has(key)) {
          airportSuggestionSet.add(key);
          airportSuggestions.push({ code, label });
        }
      });

      const equipmentNameByCode = new Map();
      [...equipmentDoc.getElementsByTagName("Equipment")].forEach((node) => {
        const code = (node.getAttribute("IATACode") || "").toUpperCase();
        const name = node.getAttribute("Name") || "";
        if (code && name && !equipmentNameByCode.has(code)) equipmentNameByCode.set(code, name);
      });

      const airlineSuggestions = [{ code: "---", label: "---" }];
      const airlineSuggestionSet = new Set(["---|---"]);
      [...airlinesDoc.getElementsByTagName("Airline")].forEach((node) => {
        const code = (node.getAttribute("IATACode") || "").toUpperCase().trim();
        const name = (node.getAttribute("Name") || "").trim();
        if (!code) return;
        const label = name ? `${name} (${code})` : code;
        const key = `${code}|${label}`;
        if (airlineSuggestionSet.has(key)) return;
        airlineSuggestionSet.add(key);
        airlineSuggestions.push({ code, label });
      });

      return { airportCountryByIata, airportDisplayByIata, equipmentNameByCode, airportSuggestions, airlineSuggestions };
    } catch (err) {
      lookupDataPromise = null;
      throw err;
    }
  })();

  return lookupDataPromise;
}

function Icon({ name, className = "", size = 20 }) {
  const map = {
    Airplane: faPlane,
    AirplaneAlt02: faJetFighterUp,
    CalendarDate: faCalendarDays,
    Search: faMagnifyingGlass,
    SwapHorizontal: faRightLeft,
    ChevronDown: faChevronDown,
    ChevronUp: faChevronUp,
    VideoCamera: faVideo,
    Plug: faPlug,
    RestaurantMenu: faMugHot,
    Cup: faMugSaucer,
    CupStraw: faWineGlass,
    Telephone: faPhone,
    MusicNote: faMusic,
    Tv: faTv,
    CalendarCheck: faCalendarCheck,
    Bag: faBagShopping,
    BagX: faBagShopping,
    Cigarette: faBanSmoking,
    NoSmoking: faBanSmoking,
    Wifi: faWifi,
    Seat: faChair,
    Print: faPrint,
    Email: faEnvelope,
    Hotel: faBed,
    ChevronRight: faChevronRight,
    ChevronLeft: faChevronLeft,
    PlusCircle: faCirclePlus,
    ExclamationCircle: faCircleExclamation,
    QuestionCircle: faCircleQuestion
  };
  const icon = map[name] || faCircle;
  return <FontAwesomeIcon icon={icon} className={className} style={{ fontSize: `${size}px`, lineHeight: 1 }} />;
}

function extractIata(value) {
  const input = String(value || "").toUpperCase();
  const withParens = input.match(/\(([A-Z]{3})\)/);
  if (withParens) return withParens[1];
  const plain = input.match(/\b([A-Z]{3})\b/);
  return plain ? plain[1] : "";
}

function extractIataList(value) {
  const input = String(value || "");
  if (!input.trim()) return [];
  const fromParens = [...input.matchAll(/\(([A-Z]{3})\)/gi)].map((m) => m[1].toUpperCase());
  const fromTokens = input
    .split(/[,\s/;|]+/)
    .map((part) => extractIata(part))
    .filter(Boolean);
  return [...new Set([...fromParens, ...fromTokens])];
}

function extractAirlineCode(value) {
  const input = String(value || "").toUpperCase().trim();
  if (!input) return "";
  if (input === "---") return "---";
  const withParens = input.match(/\(([A-Z0-9]{2})\)/);
  if (withParens) return withParens[1];
  const plain = input.match(/\b([A-Z0-9]{2})\b/);
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

function shiftDate(value, days) {
  const raw = String(value || "").replace(/\//g, "-");
  const dt = new Date(`${raw}T00:00:00`);
  if (Number.isNaN(dt.getTime())) return value;
  dt.setDate(dt.getDate() + days);
  const local = new Date(dt.getTime() - dt.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 10);
}

function durationToLabel(iso) {
  const m = String(iso || "").match(/^PT(?:(\d+)H)?(?:(\d+)M)?$/);
  if (!m) return "N/A";
  const h = Number(m[1] || 0);
  const min = Number(m[2] || 0);
  return `${h}h ${min}min`;
}

function durationToMinutes(iso) {
  const m = String(iso || "").match(/^PT(?:(\d+)H)?(?:(\d+)M)?$/);
  if (!m) return Number.MAX_SAFE_INTEGER;
  const h = Number(m[1] || 0);
  const min = Number(m[2] || 0);
  return h * 60 + min;
}

function getWeekColumns(dateInput) {
  const base = new Date(`${String(dateInput || "").replace(/\//g, "-")}T00:00:00`);
  if (Number.isNaN(base.getTime())) return [];

  const dateStamps = [];
  for (let i = 0; i < 7; i += 1) {
    const d = new Date(base);
    d.setDate(base.getDate() + i);
    dateStamps.push(d);
  }

  const weekday = base.getDay(); // 0..6 (Sun..Sat)
  for (let i = 0; i < weekday; i += 1) {
    dateStamps.unshift(dateStamps.pop());
  }

  const keys = ["sun", "mon", "tue", "wed", "thu", "fri", "sat"];
  return dateStamps.map((d, idx) => ({
    key: keys[idx],
    index: idx,
    weekday: d.toLocaleDateString(undefined, { weekday: "short" }),
    month: d.toLocaleDateString(undefined, { month: "short" }),
    day: d.getDate(),
    isCurrent: idx === weekday
  }));
}

function mapFlightDaysToWeek(flightDays) {
  const raw = String(flightDays || "");
  if (!raw) return Array(7).fill(false);

  const chars = raw.split("");
  if (chars.length < 7) return Array(7).fill(false);

  // API order is typically Mon..Sun; shift so index 0 is Sunday.
  chars.unshift(chars.pop());
  return chars.slice(0, 7).map((ch) => ch !== ".");
}

function getInitialWeekdaySelection(flights, dateInput) {
  const columns = getWeekColumns(dateInput);
  const currentWeekdayIndex = columns.findIndex((col) => col.isCurrent);
  const initial = {};

  flights.forEach((flight) => {
    const availability = mapFlightDaysToWeek(flight.flightDays);
    let selected = -1;

    if (currentWeekdayIndex >= 0 && availability[currentWeekdayIndex]) {
      selected = currentWeekdayIndex;
    } else {
      selected = availability.findIndex(Boolean);
    }

    if (selected < 0) selected = currentWeekdayIndex >= 0 ? currentWeekdayIndex : 0;
    initial[flight.id] = selected;
  });

  return initial;
}

function formatWeekdayLabel(col) {
  if (!col) return "";
  return `${col.weekday}, ${col.month} ${col.day}`;
}

function formatDateLabel(isoLike) {
  const d = new Date(isoLike);
  if (Number.isNaN(d.getTime())) return isoLike || "";
  return d.toLocaleDateString(undefined, { weekday: "short", month: "short", day: "numeric" });
}

function formatTimeLabel(isoLike) {
  const d = new Date(isoLike);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", hour12: true });
}

function normalizeDayIndicator(value) {
  const text = String(value || "").trim();
  if (/^[+-]\d+$/.test(text)) {
    return text === "+0" || text === "-0" ? "" : text;
  }
  if (/^\d+$/.test(text)) {
    const n = Number(text);
    if (!Number.isFinite(n) || n <= 0) return "";
    return `+${n}`;
  }
  return "";
}

function TimeWithIndicator({ time, indicator }) {
  const dayShift = normalizeDayIndicator(indicator);
  if (!time) return null;

  return (
    <span className="inline-flex items-start gap-1">
      <span>{time}</span>
      {dayShift && <sup className="rounded border border-black px-1 text-[10px] font-semibold leading-none">{dayShift}</sup>}
    </span>
  );
}

function AirportFlag({ iataCode, airportCountryByIata }) {
  const iata = String(iataCode || "").toUpperCase();
  const country = airportCountryByIata.get(iata);
  if (!country) return null;

  return (
    <img
      src={assetUrl(`flags/${country}.png`)}
      alt={country}
      className="h-4 w-6 rounded-sm border border-black object-cover"
      loading="lazy"
    />
  );
}

function AirlineLogo({ airlineCode, airlineName }) {
  const [fallback, setFallback] = useState(false);
  const code = String(airlineCode || "").toUpperCase();
  const preferred = assetUrl(`logos/${code}.png`);
  const source = fallback || !code ? assetUrl("logos/default.png") : preferred;

  return (
    <img
      src={source}
      alt={airlineName || code || "Airline logo"}
      className="h-6 w-8 object-contain"
      loading="lazy"
      onError={() => setFallback(true)}
    />
  );
}

function resolveEquipmentName(code, equipmentNameByCode) {
  const raw = String(code || "").toUpperCase().trim();
  if (!raw) return "";
  if (!equipmentNameByCode || equipmentNameByCode.size === 0) return raw;

  if (equipmentNameByCode.has(raw)) {
    return equipmentNameByCode.get(raw);
  }

  const tokens = raw.split(/[^A-Z0-9]+/).filter(Boolean);
  for (const token of tokens) {
    if (equipmentNameByCode.has(token)) {
      return equipmentNameByCode.get(token);
    }
  }

  return raw;
}

function formatAirportLabel(node, codeAttr = "LocationCode", nameAttr = "FLSLocationName") {
  if (!node) return "";
  const code = node.getAttribute(codeAttr) || "";
  const name = node.getAttribute(nameAttr) || "";
  if (name && code) return `${name} (${code})`;
  return code || name || "";
}

function formatTerminalLabel(value) {
  const raw = String(value || "").trim();
  if (!raw) return "";
  return /^terminal/i.test(raw) ? raw : `Terminal ${raw}`;
}

function formatOperatedBy(value) {
  const raw = String(value || "").trim();
  if (!raw) return "";
  return raw.replace(/^Owned By:/i, "Operated by ").trim();
}

function formatOperatingAirlineNode(node) {
  if (!node) return "";
  const code = String(node.getAttribute("Code") || "").trim();
  const flightNumber = String(node.getAttribute("FlightNumber") || "").trim();
  const name = String(node.getAttribute("CompanyShortName") || "").trim();
  const flightCode = `${code}${flightNumber}`.trim();
  if (!name && !flightCode) return "";
  if (name && flightCode) return `Operated by ${name} (${flightCode})`;
  if (name) return `Operated by ${name}`;
  return `Operated by ${flightCode}`;
}

function joinMetaParts(parts) {
  return parts.filter(Boolean).join(" \u2022 ");
}

function parseMealCodes(raw) {
  return [...new Set(String(raw || "").toUpperCase().replace(/[^A-Z]/g, "").split("").filter(Boolean))];
}

function parseInflightServiceCodes(raw) {
  return [...new Set(String(raw || "").match(/\d{1,2}/g) || [])];
}

function buildAmenityData(mealsRaw, inflightRaw) {
  const items = [];
  const seenLabels = new Set();

  parseMealCodes(mealsRaw).forEach((code) => {
    const m = MEAL_CODE_MAP[code];
    if (m && !seenLabels.has(m.label)) {
      seenLabels.add(m.label);
      items.push({ code, label: m.label, icon: m.icon });
    }
  });

  parseInflightServiceCodes(inflightRaw).forEach((code) => {
    if (code === "9") return; // Non-smoking is universal; hide this indicator.
    const s = INFLIGHT_SERVICE_MAP[code];
    if (s && !seenLabels.has(s.label)) {
      seenLabels.add(s.label);
      items.push({ code, label: s.label, icon: s.icon });
    }
  });

  const icons = [];
  const seenIcons = new Set();
  items.forEach((item) => {
    if (!seenIcons.has(item.icon)) {
      seenIcons.add(item.icon);
      icons.push(item.icon);
    }
  });

  return { items, icons };
}

function resolveApiBase(apiUrl) {
  const normalized = String(apiUrl || "").replace(/\/$/, "");
  if (!normalized) return "";

  const isLocalDev = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";
  if (!isLocalDev) return normalized;

  try {
    const parsed = new URL(normalized);
    if (parsed.hostname === "services.flightlookup.com") {
      return "/__fst_proxy" + parsed.pathname.replace(/\/$/, "");
    }
  } catch {
    return normalized;
  }

  return normalized;
}

async function fetchFlights(config) {
  const from = extractIata(config.from);
  const to = extractIata(config.to);
  const ymd = toYmd(config.date);
  if (!from || !to || !ymd) throw new Error("From, To, and Date are required.");

  const airlineParam = extractAirlineCode(config.airline) || "---";
  const proxyUrl = String(config.proxyUrl || "").trim();
  let url;

  if (proxyUrl) {
    url = proxyUrl.startsWith("/") ? new URL(proxyUrl, window.location.origin) : new URL(proxyUrl);
    url.searchParams.set("from", from);
    url.searchParams.set("to", to);
    url.searchParams.set("date", ymd);
    url.searchParams.set("connection", config.connection);
    url.searchParams.set("sort", config.sort);
    url.searchParams.set("time", config.time);
    url.searchParams.set("airline", airlineParam);
    url.searchParams.set("language", config.language);
    url.searchParams.set("nofilter", config.nofilter);
    url.searchParams.set("compression", config.compression);
    url.searchParams.set("codeshare", config.showCodeshare);
    url.searchParams.set("interline", config.interline);
    url.searchParams.set("specificDate", config.specificDate);
    url.searchParams.set("apiUrl", config.apiUrl);
    url.searchParams.set("result", config.result || defaults.result);
  } else {
    if (!config.subscriptionKey) throw new Error("Missing subscription key.");
    const base = resolveApiBase(config.apiUrl);
    const requestPath = `${base}/${from}/${to}/${ymd}/`;
    url = base.startsWith("/") ? new URL(requestPath, window.location.origin) : new URL(requestPath);

    url.searchParams.set("7Day", config.specificDate === "Y" ? "N" : "Y");
    url.searchParams.set("Connection", config.connection);
    url.searchParams.set("TRC", "N");
    url.searchParams.set("Compression", config.compression);
    url.searchParams.set("Sort", config.sort);
    url.searchParams.set("Time", config.time);
    url.searchParams.set("Airline", airlineParam);
    url.searchParams.set("Language", config.language);
    url.searchParams.set("Nofilter", config.nofilter);
    url.searchParams.set("Interline", config.interline);
    url.searchParams.set("CodeShare", config.showCodeshare);
    url.searchParams.set("Results", config.result || defaults.result);
    url.searchParams.set("subscription-key", config.subscriptionKey);
  }

  console.log("[FST] API URL:", url.toString());
  const res = await fetch(url.toString(), { method: "GET", headers: { Accept: "application/xml,text/xml,*/*" } });
  if (!res.ok) throw new Error(`API request failed (${res.status})`);

  const text = await res.text();
  const doc = new DOMParser().parseFromString(text, "application/xml");
  const details = [...doc.getElementsByTagNameNS("*", "FlightDetails")];
  const responseFields = doc.getElementsByTagNameNS("*", "FLSResponseFields")[0];
  const meta = {
    originName: responseFields?.getAttribute("FLSOriginName") || extractIata(config.from) || "",
    originCode: responseFields?.getAttribute("FLSOriginCode") || extractIata(config.from) || "",
    destinationName: responseFields?.getAttribute("FLSDestinationName") || extractIata(config.to) || "",
    destinationCode: responseFields?.getAttribute("FLSDestinationCode") || extractIata(config.to) || "",
    totalResults: Number(responseFields?.getAttribute("FLSResultCount") || details.length || 0)
  };

  const flights = details.map((node, idx) => {
    const depIso = node.getAttribute("FLSDepartureDateTime") || "";
    const arrIso = node.getAttribute("FLSArrivalDateTime") || "";
    const flightType = (node.getAttribute("FLSFlightType") || "").toLowerCase();
    const legs = Number(node.getAttribute("FLSFlightLegs") || 1);
    const firstLeg = node.getElementsByTagNameNS("*", "FlightLegDetails")[0];
    const allLegs = [...node.getElementsByTagNameNS("*", "FlightLegDetails")];
    const lastLeg = allLegs[allLegs.length - 1];
    const firstDepAirportNode = firstLeg?.getElementsByTagNameNS("*", "DepartureAirport")[0];
    const lastArrAirportNode = lastLeg?.getElementsByTagNameNS("*", "ArrivalAirport")[0];
    const carrier = firstLeg?.getElementsByTagNameNS("*", "MarketingAirline")[0];
    const equipment = firstLeg?.getElementsByTagNameNS("*", "Equipment")[0];
    const carrierCode = carrier?.getAttribute("Code") || "";
    const flightNumber = firstLeg?.getAttribute("FlightNumber") || "";
    const airEquipType = equipment?.getAttribute("AirEquipType") || "";

    const segments = allLegs.map((leg) => {
      const depAirportNode = leg.getElementsByTagNameNS("*", "DepartureAirport")[0];
      const arrAirportNode = leg.getElementsByTagNameNS("*", "ArrivalAirport")[0];
      const mk = leg.getElementsByTagNameNS("*", "MarketingAirline")[0];
      const eq = leg.getElementsByTagNameNS("*", "Equipment")[0];
      const legCarrierCode = mk?.getAttribute("Code") || "";
      const legCarrierName = mk?.getAttribute("CompanyShortName") || legCarrierCode || "Airline";
      const legNumber = leg.getAttribute("FlightNumber") || "";
      const legType = eq?.getAttribute("AirEquipType") || "";
      const amenities = buildAmenityData(leg.getAttribute("FLSMeals") || "", leg.getAttribute("FLSInflightServices") || "");
      const operatingAirlineNode = leg.getElementsByTagNameNS("*", "OperatingAirline")[0];
      const operatedBy = formatOperatingAirlineNode(operatingAirlineNode) || formatOperatedBy(leg.getAttribute("FLSDOTDisclosure") || "");

      return {
        depIso: leg.getAttribute("DepartureDateTime") || "",
        arrIso: leg.getAttribute("ArrivalDateTime") || "",
        depTime: formatTimeLabel(leg.getAttribute("DepartureDateTime") || ""),
        arrTime: formatTimeLabel(leg.getAttribute("ArrivalDateTime") || ""),
        depDayIndicator: depAirportNode?.getAttribute("FLSDayIndicator") || "",
        arrDayIndicator: arrAirportNode?.getAttribute("FLSDayIndicator") || "",
        fromLabel: formatAirportLabel(depAirportNode),
        toLabel: formatAirportLabel(arrAirportNode),
        depTerminal: formatTerminalLabel(depAirportNode?.getAttribute("Terminal") || ""),
        arrTerminal: formatTerminalLabel(arrAirportNode?.getAttribute("Terminal") || ""),
        operatedBy,
        duration: durationToLabel(leg.getAttribute("JourneyDuration") || ""),
        airlineName: legCarrierName,
        airlineCode: legCarrierCode,
        flightNumber: legNumber,
        airEquipType: legType,
        airlineFlightInfo: legCarrierCode && legNumber && legType ? `${legCarrierCode}${legNumber}/${legType}` : "",
        amenityItems: amenities.items,
        amenityIcons: amenities.icons
      };
    });

    let stops = "Nonstop";
    const viaCodes = allLegs
      .slice(0, -1)
      .map((leg) => {
        const arr = leg.getElementsByTagNameNS("*", "ArrivalAirport")[0];
        return (arr?.getAttribute("LocationCode") || "").toUpperCase();
      })
      .filter(Boolean);
    const uniqueViaCodes = [...new Set(viaCodes)];
    const via = uniqueViaCodes.length ? uniqueViaCodes.join(", ") : "---";

    if (flightType !== "nonstop") {
      const count = Math.max(0, legs - 1);
      const viaText = uniqueViaCodes.length ? ` (${uniqueViaCodes.join(", ")})` : "";
      stops = `${count} stop${count === 1 ? "" : "s"}${viaText}`;
    }

    return {
      id: `f-${idx + 1}`,
      depIso,
      arrIso,
      depTime: formatTimeLabel(depIso),
      arrTime: formatTimeLabel(arrIso),
      depCode: firstDepAirportNode?.getAttribute("LocationCode") || node.getAttribute("FLSDepartureCode") || "",
      arrCode: lastArrAirportNode?.getAttribute("LocationCode") || node.getAttribute("FLSArrivalCode") || "",
      depDayIndicator: firstDepAirportNode?.getAttribute("FLSDayIndicator") || "",
      arrDayIndicator: lastArrAirportNode?.getAttribute("FLSDayIndicator") || "",
      depDate: formatDateLabel(depIso),
      arrDate: formatDateLabel(arrIso),
      duration: durationToLabel(node.getAttribute("TotalFlightTime") || ""),
      durationMinutes: durationToMinutes(node.getAttribute("TotalFlightTime") || ""),
      stops,
      via,
      flightDays: node.getAttribute("FLSFlightDays") || "",
      airline: carrier?.getAttribute("CompanyShortName") || carrier?.getAttribute("Code") || "Unknown Airline",
      airlineCode: carrierCode,
      flightNumber,
      airEquipType,
      airlineFlightInfo: carrierCode && flightNumber && airEquipType ? `${carrierCode}${flightNumber}/${airEquipType}` : "",
      segments
    };
  });

  return { flights, meta };
}

function App() {
  const [form, setForm] = useState(bootConfig);
  const [phase, setPhase] = useState("idle");
  const [error, setError] = useState("");
  const [flights, setFlights] = useState([]);
  const [summaryMeta, setSummaryMeta] = useState(null);
  const [expanded, setExpanded] = useState(null);
  const [openAmenity, setOpenAmenity] = useState(null);
  const [showAdvanced, setShowAdvanced] = useState(false);
  const [activeAutocomplete, setActiveAutocomplete] = useState(null);
  const [sortField, setSortField] = useState("departure_time");
  const [sortOrder, setSortOrder] = useState("asc");
  const [filterAircraft, setFilterAircraft] = useState("ALL");
  const [filterDeparture, setFilterDeparture] = useState("ALL");
  const [filterArrival, setFilterArrival] = useState("ALL");
  const [page, setPage] = useState(1);
  const [selectedDayByFlight, setSelectedDayByFlight] = useState({});
  const [viaInput, setViaInput] = useState("");
  const [selectedViaCodes, setSelectedViaCodes] = useState(extractIataList(bootConfig.via));
  const [appliedViaCodes, setAppliedViaCodes] = useState(extractIataList(bootConfig.via));
  const [showEmailModal, setShowEmailModal] = useState(false);
  const [emailRecipient, setEmailRecipient] = useState("");
  const [emailSending, setEmailSending] = useState(false);
  const [emailFeedback, setEmailFeedback] = useState("");
  const [printExpandedAll, setPrintExpandedAll] = useState(false);
  const expandTimerRef = useRef(null);
  const [lookupData, setLookupData] = useState({
    airportCountryByIata: new Map(),
    airportDisplayByIata: new Map(),
    equipmentNameByCode: new Map(),
    airportSuggestions: [],
    airlineSuggestions: []
  });

  const setField = (name, value) => setForm((s) => ({ ...s, [name]: value }));
  const setViaCodes = (codes) => {
    const unique = [...new Set((codes || []).map((c) => String(c || "").toUpperCase()).filter(Boolean))];
    setSelectedViaCodes(unique);
    setField("via", unique.join(","));
  };
  const onSpecificDateModeChange = (value) => {
    setField("specificDate", value);
    setPhase("idle");
    setError("");
    setFlights([]);
    setSummaryMeta(null);
    setExpanded(null);
    setOpenAmenity(null);
    setSelectedDayByFlight({});
    setPage(1);
  };
  const getAutocompleteOptions = (value) => {
    const all = lookupData.airportSuggestions || [];
    const term = String(value || "").trim().toLowerCase();
    if (!term) return all.slice(0, 8);
    const matches = all.filter((item) => item.label.toLowerCase().includes(term) || item.code.toLowerCase().includes(term));
    return matches
      .sort((a, b) => {
        const aCode = a.code.toLowerCase();
        const bCode = b.code.toLowerCase();
        const aStarts = aCode.startsWith(term) ? 1 : 0;
        const bStarts = bCode.startsWith(term) ? 1 : 0;
        if (aStarts !== bStarts) return bStarts - aStarts;

        const aHas = aCode.includes(term) ? 1 : 0;
        const bHas = bCode.includes(term) ? 1 : 0;
        if (aHas !== bHas) return bHas - aHas;

        return a.label.localeCompare(b.label);
      })
      .slice(0, 8);
  };
  const getAirlineOptions = (value) => {
    const all = lookupData.airlineSuggestions || [];
    const term = String(value || "").trim().toLowerCase();
    if (!term) return all.slice(0, 10);
    return all
      .filter((item) => item.label.toLowerCase().includes(term) || item.code.toLowerCase().includes(term))
      .sort((a, b) => {
        const aCode = a.code.toLowerCase();
        const bCode = b.code.toLowerCase();
        const aStarts = aCode.startsWith(term) ? 1 : 0;
        const bStarts = bCode.startsWith(term) ? 1 : 0;
        if (aStarts !== bStarts) return bStarts - aStarts;
        return a.label.localeCompare(b.label);
      })
      .slice(0, 10);
  };
  const airportDisplay = (code) => {
    const iata = String(code || "").toUpperCase();
    const name = lookupData.airportDisplayByIata.get(iata);
    return name ? `${name} (${iata})` : iata;
  };

  useEffect(() => {
    if (window.parent === window) return undefined;

    let raf = 0;
    const postHeight = () => {
      const nextHeight = Math.max(
        document.documentElement?.scrollHeight || 0,
        document.body?.scrollHeight || 0
      );
      window.parent.postMessage({ type: "FST_WIDGET_HEIGHT", height: nextHeight }, "*");
    };
    const queuePost = () => {
      if (raf) return;
      raf = window.requestAnimationFrame(() => {
        raf = 0;
        postHeight();
      });
    };

    queuePost();

    const resizeObserver = new ResizeObserver(queuePost);
    if (document.body) resizeObserver.observe(document.body);
    if (document.documentElement) resizeObserver.observe(document.documentElement);

    const mutationObserver = new MutationObserver(queuePost);
    mutationObserver.observe(document.body, { childList: true, subtree: true, attributes: true, characterData: true });

    window.addEventListener("resize", queuePost);
    window.addEventListener("load", queuePost);

    return () => {
      if (raf) window.cancelAnimationFrame(raf);
      resizeObserver.disconnect();
      mutationObserver.disconnect();
      window.removeEventListener("resize", queuePost);
      window.removeEventListener("load", queuePost);
    };
  }, []);

  useEffect(() => {
    if (window.parent === window) return;
    const nextHeight = Math.max(
      document.documentElement?.scrollHeight || 0,
      document.body?.scrollHeight || 0
    );
    window.parent.postMessage({ type: "FST_WIDGET_HEIGHT", height: nextHeight }, "*");
  }, [phase, flights.length, expanded, showAdvanced, error, page, sortField, sortOrder, selectedDayByFlight]);

  useEffect(() => {
    let ignore = false;
    loadLookupData()
      .then((data) => {
        if (!ignore) setLookupData(data);
      })
      .catch((err) => {
        console.warn("Flight lookup asset loading failed:", err);
      });
    return () => {
      ignore = true;
    };
  }, []);

  useEffect(() => {
    return () => {
      if (expandTimerRef.current) {
        clearTimeout(expandTimerRef.current);
      }
    };
  }, []);

  useEffect(() => {
    const handleAfterPrint = () => {
      setPrintExpandedAll(false);
      setExpanded(null);
      setOpenAmenity(null);
    };

    window.addEventListener("afterprint", handleAfterPrint);
    return () => {
      window.removeEventListener("afterprint", handleAfterPrint);
    };
  }, []);

  const reopenExpandedFlight = (flightId) => {
    if (expandTimerRef.current) clearTimeout(expandTimerRef.current);
    setExpanded(null);
    setOpenAmenity(null);
    expandTimerRef.current = setTimeout(() => {
      setExpanded(flightId);
      expandTimerRef.current = null;
    }, DAY_SWITCH_REOPEN_MS);
  };

  async function runSearch(searchForm = form) {
    setPhase("loading");
    setError("");
    setExpanded(null);
    setOpenAmenity(null);
    setPage(1);
    setSelectedDayByFlight({});
    setFilterAircraft("ALL");
    setFilterDeparture("ALL");
    setFilterArrival("ALL");
    const loaderDelay = new Promise((resolve) => setTimeout(resolve, LOADER_DURATION_MS));

    try {
      const [{ flights: result, meta }] = await Promise.all([fetchFlights(searchForm), loaderDelay]);
      setFlights(result);
      setSummaryMeta(meta);
      setAppliedViaCodes(extractIataList(searchForm.via || ""));
      setSelectedDayByFlight(getInitialWeekdaySelection(result, searchForm.date));
      setPhase("done");
      if (!result.length) setError("No flights found for this route/date.");
    } catch (err) {
      await loaderDelay;
      setFlights([]);
      setSummaryMeta(null);
      setAppliedViaCodes(extractIataList(searchForm.via || ""));
      setSelectedDayByFlight({});
      setPhase("done");
      setError(err.message.includes("Failed to fetch") ? "Request blocked (likely CORS). Use a server-side proxy for production embedding." : err.message);
    }
  }

  async function onSubmit(e) {
    e.preventDefault();
    await runSearch();
  }

  const PAGE_SIZE = 50;
  const collator = new Intl.Collator(undefined, { sensitivity: "base", numeric: true });
  const compareText = (a, b) => collator.compare(String(a || ""), String(b || ""));
  const compareTime = (a, b) => {
    const ta = new Date(a || "").getTime();
    const tb = new Date(b || "").getTime();
    const va = Number.isNaN(ta) ? Number.MAX_SAFE_INTEGER : ta;
    const vb = Number.isNaN(tb) ? Number.MAX_SAFE_INTEGER : tb;
    return va - vb;
  };
  const compareTuple = (a, b, fields) => {
    for (const field of fields) {
      let result = 0;
      if (field === "dep_time") result = compareTime(a.depIso, b.depIso);
      if (field === "arr_time") result = compareTime(a.arrIso, b.arrIso);
      if (field === "arr_airport") result = compareText(a.arrCode, b.arrCode);
      if (field === "airline") result = compareText(a.airline, b.airline);
      if (field === "duration") result = (a.durationMinutes || 0) - (b.durationMinutes || 0);
      if (result !== 0) return result;
    }
    return 0;
  };
  const sortFieldsByType = {
    departure_time: ["dep_time", "arr_airport", "airline"],
    airport: ["arr_airport", "dep_time", "airline"],
    airline: ["airline", "dep_time", "arr_airport"],
    arrival_time: ["arr_time", "arr_airport", "airline"],
    duration: ["duration", "dep_time", "arr_airport", "airline"]
  };
  const flightsByVia = appliedViaCodes.length
    ? flights.filter((flight) => {
      const flightViaCodes = String(flight.via || "")
        .split(",")
        .map((code) => code.trim().toUpperCase())
        .filter(Boolean);
      return appliedViaCodes.some((code) => flightViaCodes.includes(code));
    })
    : flights;
  const viaNoRoutingMatch = appliedViaCodes.length > 0 && flights.length > 0 && flightsByVia.length === 0;
  const aircraftOptions = [...new Set(flights.flatMap((flight) => flight.segments.map((segment) => segment.airEquipType)).filter(Boolean))];
  const departureOptions = [...new Set(flights.flatMap((flight) => flight.segments.map((segment) => extractIata(segment.fromLabel))).filter(Boolean))].sort(compareText);
  const arrivalOptions = [...new Set(flights.flatMap((flight) => flight.segments.map((segment) => extractIata(segment.toLabel))).filter(Boolean))].sort(compareText);
  const flightsByFilters = flightsByVia.filter((flight) => {
    const matchAircraft = filterAircraft === "ALL" || flight.segments.some((segment) => segment.airEquipType === filterAircraft);
    const matchDeparture = filterDeparture === "ALL" || flight.segments.some((segment) => extractIata(segment.fromLabel) === filterDeparture);
    const matchArrival = filterArrival === "ALL" || flight.segments.some((segment) => extractIata(segment.toLabel) === filterArrival);
    return matchAircraft && matchDeparture && matchArrival;
  });

  const sortedFlights = [...flightsByFilters].sort((a, b) => {
    const base = compareTuple(a, b, sortFieldsByType[sortField] || sortFieldsByType.departure_time);
    return sortOrder === "desc" ? -base : base;
  });
  const weekColumns = getWeekColumns(form.date);
  const isSevenDayMode = form.specificDate === "N";
  const totalPages = Math.max(1, Math.ceil(sortedFlights.length / PAGE_SIZE));
  const pageSafe = Math.min(page, totalPages);
  const pagedFlights = sortedFlights.slice((pageSafe - 1) * PAGE_SIZE, pageSafe * PAGE_SIZE);
  const renderedFlights = printExpandedAll ? sortedFlights : pagedFlights;
  const summaryDateLabel = new Date(`${form.date}T00:00:00`).toLocaleDateString(undefined, {
    weekday: "long",
    month: "long",
    day: "numeric",
    year: "numeric"
  });
  const hotelLink = summaryMeta
    ? `https://www.stay22.com/allez/booking?aid=cruisinaltitude&campaign=departure_board&address=${encodeURIComponent(summaryMeta.destinationName)}&checkin=${form.date}&checkout=${shiftDate(form.date, 1)}`
    : "";
  const emailPayload = summaryMeta
    ? {
        summary: {
          dateLabel: summaryDateLabel,
          originName: summaryMeta.originName,
          originCode: summaryMeta.originCode,
          destinationName: summaryMeta.destinationName,
          destinationCode: summaryMeta.destinationCode,
          totalResults: sortedFlights.length
        },
        results: sortedFlights.map((flight) => ({
          depTime: flight.depTime,
          depDayIndicator: flight.depDayIndicator,
          depCode: flight.depCode,
          depDate: flight.depDate,
          arrTime: flight.arrTime,
          arrDayIndicator: flight.arrDayIndicator,
          arrCode: flight.arrCode,
          arrDate: flight.arrDate,
          duration: flight.duration,
          stops: flight.stops,
          airline: flight.airline,
          segments: flight.segments.map((segment) => ({
            airlineName: segment.airlineName,
            airlineCode: segment.airlineCode,
            flightNumber: segment.flightNumber,
            airEquipType: resolveEquipmentName(segment.airEquipType, lookupData.equipmentNameByCode),
            operatedBy: segment.operatedBy,
            fromLabel: segment.fromLabel,
            toLabel: segment.toLabel,
            depTime: segment.depTime,
            depDayIndicator: segment.depDayIndicator,
            arrTime: segment.arrTime,
            arrDayIndicator: segment.arrDayIndicator,
            depTerminal: segment.depTerminal,
            arrTerminal: segment.arrTerminal,
            duration: segment.duration
          }))
        })),
        pageUrl: window.location.href
      }
    : null;

  async function runRelativeDaySearch(offset) {
    const nextDate = shiftDate(form.date, offset);
    const nextForm = { ...form, date: nextDate };
    setForm(nextForm);
    await runSearch(nextForm);
  }

  function handlePrintSchedule() {
    setPrintExpandedAll(true);
    setExpanded(null);
    setOpenAmenity(null);
    window.requestAnimationFrame(() => {
      window.setTimeout(() => {
        window.print();
      }, 120);
    });
  }

  async function handleSendEmail() {
    if (!bootConfig.emailUrl) {
      setEmailFeedback("Email endpoint is not configured.");
      return;
    }

    const trimmed = emailRecipient.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed)) {
      setEmailFeedback("Enter a valid email address.");
      return;
    }

    if (!emailPayload) {
      setEmailFeedback("There are no results to email.");
      return;
    }

    setEmailSending(true);
    setEmailFeedback("");

    try {
      const response = await fetch(bootConfig.emailUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          toEmail: trimmed,
          payload: emailPayload
        })
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.ok) {
        throw new Error(data.message || "Unable to send email.");
      }
      setEmailFeedback(`Schedule sent to ${trimmed}.`);
      setEmailSending(false);
      window.setTimeout(() => {
        setShowEmailModal(false);
        setEmailRecipient("");
        setEmailFeedback("");
      }, 900);
    } catch (err) {
      setEmailSending(false);
      setEmailFeedback(err.message || "Unable to send email.");
    }
  }

  return (
    <main className="fst-bg min-h-screen text-black">
      <div className="mx-auto max-w-6xl p-4 md:p-8">
        <section className="fst-glass mt-20 rounded-2xl p-3 shadow-[0_2px_10px_rgba(13,18,30,0.18)] md:mt-5 md:p-4">
          <form className="space-y-3" onSubmit={onSubmit}>
            <input type="hidden" name="showCodeshare" value={form.showCodeshare} />
            <input type="hidden" name="interline" value={form.interline} />
            <input type="hidden" name="specificDate" value={form.specificDate} />
            <div className="grid items-center gap-3 md:grid-cols-[minmax(0,1fr)_44px_minmax(0,1fr)_180px_156px_44px]">
              <div className="relative">
                <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-black">
                  <Icon name="Airplane" size={14} />
                </span>
                <input
                  className="h-12 w-full rounded-lg border border-black bg-white pl-9 pr-4 text-sm outline-none transition focus:outline-none"
                  value={form.from}
                  onChange={(e) => {
                    setField("from", e.target.value);
                    setActiveAutocomplete("from");
                  }}
                  onFocus={() => setActiveAutocomplete("from")}
                  onBlur={() => setTimeout(() => setActiveAutocomplete((v) => (v === "from" ? null : v)), 120)}
                  placeholder="From"
                />
                <div
                  className={`absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-lg border border-black bg-white shadow-lg transition-all duration-200 ease-out ${
                    activeAutocomplete === "from" ? "max-h-64 translate-y-0 opacity-100" : "max-h-0 -translate-y-1 opacity-0"
                  }`}
                >
                  {getAutocompleteOptions(form.from).map((option) => (
                    <button
                      key={`from-${option.code}-${option.label}`}
                      type="button"
                      className="block w-full border-b border-black/10 px-3 py-2 text-left text-sm text-black last:border-b-0 hover:bg-black/5"
                      onMouseDown={(e) => {
                        e.preventDefault();
                        setField("from", compactAirportLabel(option.label));
                        setActiveAutocomplete(null);
                      }}
                    >
                      {option.label}
                    </button>
                  ))}
                </div>
              </div>

              <button
                type="button"
                className="h-12 rounded-lg border border-black bg-white p-2 transition hover:bg-white"
                onClick={() => setForm((s) => ({ ...s, from: s.to, to: s.from }))}
              >
                <Icon name="SwapHorizontal" size={18} className="text-black" />
              </button>

              <div className="relative">
                <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-black">
                  <Icon name="AirplaneAlt02" size={14} />
                </span>
                <input
                  className="h-12 w-full rounded-lg border border-black bg-white pl-9 pr-4 text-sm outline-none transition focus:outline-none"
                  value={form.to}
                  onChange={(e) => {
                    setField("to", e.target.value);
                    setActiveAutocomplete("to");
                  }}
                  onFocus={() => setActiveAutocomplete("to")}
                  onBlur={() => setTimeout(() => setActiveAutocomplete((v) => (v === "to" ? null : v)), 120)}
                  placeholder="To"
                />
                <div
                  className={`absolute left-0 right-0 top-[calc(100%+4px)] z-30 overflow-hidden rounded-lg border border-black bg-white shadow-lg transition-all duration-200 ease-out ${
                    activeAutocomplete === "to" ? "max-h-64 translate-y-0 opacity-100" : "max-h-0 -translate-y-1 opacity-0"
                  }`}
                >
                  {getAutocompleteOptions(form.to).map((option) => (
                    <button
                      key={`to-${option.code}-${option.label}`}
                      type="button"
                      className="block w-full border-b border-black/10 px-3 py-2 text-left text-sm text-black last:border-b-0 hover:bg-black/5"
                      onMouseDown={(e) => {
                        e.preventDefault();
                        setField("to", compactAirportLabel(option.label));
                        setActiveAutocomplete(null);
                      }}
                    >
                      {option.label}
                    </button>
                  ))}
                </div>
              </div>

              <input
                type="date"
                className="h-12 w-full rounded-lg border border-black bg-white px-4 text-sm outline-none transition focus:outline-none"
                value={form.date}
                onChange={(e) => setField("date", e.target.value)}
              />

              <button className="group h-12 inline-flex items-center justify-center gap-2 rounded-xl border border-black bg-white px-5 text-sm font-bold text-black transition hover:scale-[1.01] hover:border-blue-600 hover:bg-blue-600 hover:text-white">
                <Icon name="Search" size={16} className="transition group-hover:rotate-6" />Search
              </button>
              <div className="relative flex items-center justify-center">
                {!showAdvanced && (
                  <div className="fst-advanced-hint pointer-events-none absolute -top-16 right-0 z-20 whitespace-nowrap rounded-2xl bg-neutral-200 px-4 py-2 text-center text-xs font-semibold text-black shadow-lg" aria-hidden="true">
                    Click Here For
                    <br />
                    Advanced Fields
                    <span className="fst-advanced-hint-arrow" />
                  </div>
                )}
                <button
                  type="button"
                  className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-black bg-white transition"
                  aria-label={showAdvanced ? "Hide advanced fields" : "Show advanced fields"}
                  onClick={() => setShowAdvanced((v) => !v)}
                >
                  <Icon name={showAdvanced ? "ChevronUp" : "ChevronDown"} size={16} />
                </button>
              </div>
            </div>

            <div
              className={`transition-all duration-300 ease-in-out ${
                showAdvanced ? "max-h-[980px] overflow-visible opacity-100 md:max-h-[520px]" : "max-h-0 overflow-hidden opacity-0"
              }`}
            >
              <div className="space-y-3 pt-1">
                <div className="grid gap-3 md:grid-cols-3">
                  <div className="relative">
                    <span className="mb-1 block text-xs uppercase tracking-wider text-black">Airline</span>
                    <input
                      className="w-full rounded-xl border border-black bg-white px-3 py-3 text-sm outline-none transition focus:outline-none"
                      value={form.airline}
                      onChange={(e) => {
                        setField("airline", e.target.value);
                        setActiveAutocomplete("airline");
                      }}
                      onFocus={() => setActiveAutocomplete("airline")}
                      onBlur={() => setTimeout(() => setActiveAutocomplete((v) => (v === "airline" ? null : v)), 120)}
                      placeholder="Airline"
                    />
                    <div
                      className={`absolute left-0 right-0 top-[calc(100%+4px)] z-40 overflow-hidden rounded-lg border border-black bg-white shadow-lg transition-all duration-200 ease-out ${
                        activeAutocomplete === "airline" ? "max-h-64 translate-y-0 opacity-100" : "max-h-0 -translate-y-1 opacity-0"
                      }`}
                    >
                      {getAirlineOptions(form.airline).map((option) => (
                        <button
                          key={`airline-${option.code}-${option.label}`}
                          type="button"
                          className="block w-full border-b border-black/10 px-3 py-2 text-left text-sm text-black last:border-b-0 hover:bg-black/5"
                          onMouseDown={(e) => {
                            e.preventDefault();
                            setField("airline", option.label);
                            setActiveAutocomplete(null);
                          }}
                        >
                          {option.label}
                        </button>
                      ))}
                    </div>
                  </div>

                  <label>
                    <span className="mb-1 text-xs uppercase tracking-wider text-black">Sort</span>
                    <select className="w-full rounded-xl border border-black bg-white px-3 py-3 text-sm" value={form.sort} onChange={(e) => setField("sort", e.target.value)}>
                      <option value="Departure">Departure</option>
                      <option value="Arrival">Arrival</option>
                      <option value="Duration">Duration</option>
                      <option value="Flights">Flights</option>
                    </select>
                  </label>

                  <label>
                    <span className="mb-1 text-xs uppercase tracking-wider text-black">Time</span>
                    <select className="w-full rounded-xl border border-black bg-white px-3 py-3 text-sm" value={form.time} onChange={(e) => setField("time", e.target.value)}>
                      <option value="ANY">Any</option>
                      <option value="AM">AM</option>
                      <option value="PM">PM</option>
                      <option value="NIGHT">Night</option>
                    </select>
                  </label>
                </div>

                <div className="grid gap-3 md:grid-cols-4">
                  <div className="relative">
                    <span className="mb-1 block text-xs uppercase tracking-wider text-black">Via</span>
                    {selectedViaCodes.length > 0 && (
                      <div className="mb-2 flex flex-wrap gap-2">
                        {selectedViaCodes.map((code) => (
                          <span key={`via-chip-${code}`} className="inline-flex items-center gap-1 rounded-full border border-black bg-white px-2 py-1 text-xs font-semibold text-black">
                            {airportDisplay(code)}
                            <button
                              type="button"
                              className="rounded-full px-1 leading-none hover:bg-black/10"
                              aria-label={`Remove ${code}`}
                              onClick={() => setViaCodes(selectedViaCodes.filter((c) => c !== code))}
                            >
                              ×
                            </button>
                          </span>
                        ))}
                      </div>
                    )}
                    <input
                      className="w-full rounded-xl border border-black bg-white px-3 py-3 text-sm outline-none transition focus:outline-none"
                      value={viaInput}
                      onChange={(e) => {
                        setViaInput(e.target.value);
                        setActiveAutocomplete("via");
                      }}
                      onFocus={() => setActiveAutocomplete("via")}
                      onBlur={() => setTimeout(() => setActiveAutocomplete((v) => (v === "via" ? null : v)), 120)}
                      placeholder="Add via airport"
                    />
                    <div
                      className={`absolute left-0 right-0 top-[calc(100%+4px)] z-40 overflow-hidden rounded-lg border border-black bg-white shadow-lg transition-all duration-200 ease-out ${
                        activeAutocomplete === "via" ? "max-h-64 translate-y-0 opacity-100" : "max-h-0 -translate-y-1 opacity-0"
                      }`}
                    >
                      {getAutocompleteOptions(viaInput).map((option) => (
                        <button
                          key={`via-${option.code}-${option.label}`}
                          type="button"
                          className="block w-full border-b border-black/10 px-3 py-2 text-left text-sm text-black last:border-b-0 hover:bg-black/5"
                          onMouseDown={(e) => {
                            e.preventDefault();
                            setViaCodes([...selectedViaCodes, option.code]);
                            setViaInput("");
                            setActiveAutocomplete(null);
                          }}
                        >
                          {option.label}
                        </button>
                      ))}
                    </div>
                  </div>

                  <label>
                    <span className="mb-1 text-xs uppercase tracking-wider text-black">Show Codeshare</span>
                    <select className="w-full rounded-xl border border-black bg-white px-3 py-3 text-sm" value={form.showCodeshare} onChange={(e) => setField("showCodeshare", e.target.value)}>
                      <option value="N">No</option>
                      <option value="Y">Yes</option>
                    </select>
                  </label>

                  <label>
                    <span className="mb-1 text-xs uppercase tracking-wider text-black">Interline</span>
                    <select className="w-full rounded-xl border border-black bg-white px-3 py-3 text-sm" value={form.interline} onChange={(e) => setField("interline", e.target.value)}>
                      <option value="N">No</option>
                      <option value="Y">Yes</option>
                    </select>
                  </label>

                  <label>
                    <span className="mb-1 text-xs uppercase tracking-wider text-black">Specific Date</span>
                    <select className="w-full rounded-xl border border-black bg-white px-3 py-3 text-sm" value={form.specificDate} onChange={(e) => onSpecificDateModeChange(e.target.value)}>
                      <option value="Y">Yes</option>
                      <option value="N">No (7-Day)</option>
                    </select>
                  </label>
                </div>
              </div>
            </div>
          </form>
        </section>

        {phase === "loading" && (
          <section className="mt-6 p-4 md:p-6">
            <p className="mb-4 text-center text-lg font-semibold">Searching flights...</p>
            <div className="fst-loading-track">
              <span className="fst-loading-line"></span>
              <span className="fst-pulse-dot fst-pulse-dot-start"></span>
              <span className="fst-pulse-dot fst-pulse-dot-end"></span>
              <span className="fst-plane"><img src={assetUrl("plane_loader.svg")} alt="" className="h-9 w-9 object-contain" /></span>
            </div>
          </section>
        )}

        {phase === "done" && (
          <section className="fst-glass mt-6 p-4 md:p-6">
            {summaryMeta && (
              <div className="mb-5 space-y-4">
                <div className="grid gap-4 rounded-[28px] bg-[#dfe1ff] p-5 shadow-[0_8px_24px_rgba(13,18,30,0.12)] md:grid-cols-[1.3fr_1fr_auto] md:items-center">
                  <div>
                    <h2 className="text-2xl font-bold text-black">{summaryDateLabel}</h2>
                    <p className="text-sm text-black">From: {summaryMeta.originName} ({summaryMeta.originCode})</p>
                    <p className="text-sm text-black">To: {summaryMeta.destinationName} ({summaryMeta.destinationCode})</p>
                    <p className="text-sm text-black">Total Results: {sortedFlights.length}</p>
                  </div>

                  <div className="grid gap-3 md:grid-cols-2">
                    <button type="button" className="inline-flex items-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-semibold text-blue-700 shadow-sm" onClick={handlePrintSchedule}>
                      <Icon name="Print" size={14} />
                      <span>Print Schedule</span>
                    </button>
                    <button type="button" className="inline-flex items-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-semibold text-blue-700 shadow-sm" onClick={() => { setShowEmailModal(true); setEmailFeedback(""); }}>
                      <Icon name="Email" size={14} />
                      <span>Email Schedule</span>
                    </button>
                    <a className="inline-flex items-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-semibold text-black shadow-sm md:col-span-2" href={hotelLink} target="_blank" rel="noreferrer">
                      <Icon name="Hotel" size={14} />
                      <span>Book Hotel in {summaryMeta.destinationName}</span>
                    </a>
                  </div>

                  <div className="grid gap-2">
                    <button type="button" className="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-500 px-4 py-3 text-sm font-semibold text-white shadow-sm" onClick={() => runRelativeDaySearch(1)}>
                      <span>Next Day</span>
                      <Icon name="ChevronRight" size={12} />
                      <Icon name="ChevronRight" size={12} />
                    </button>
                    <button type="button" className="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-500 px-4 py-3 text-sm font-semibold text-white shadow-sm" onClick={() => runRelativeDaySearch(-1)}>
                      <Icon name="ChevronLeft" size={12} />
                      <Icon name="ChevronLeft" size={12} />
                      <span>Prev Day</span>
                    </button>
                  </div>
                </div>

                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                  <div className="flex flex-col gap-3 md:flex-row md:items-center">
                    <span className="text-sm font-medium text-black">Filter By:</span>
                    <select className="rounded-lg border border-black bg-white px-3 py-2 text-sm" value={filterAircraft} onChange={(e) => { setFilterAircraft(e.target.value); setPage(1); }}>
                      <option value="ALL">Aircraft</option>
                      {aircraftOptions.map((code) => (
                        <option key={code} value={code}>{resolveEquipmentName(code, lookupData.equipmentNameByCode)}</option>
                      ))}
                    </select>
                    <select className="rounded-lg border border-black bg-white px-3 py-2 text-sm" value={filterDeparture} onChange={(e) => { setFilterDeparture(e.target.value); setPage(1); }}>
                      <option value="ALL">Departure</option>
                      {departureOptions.map((code) => (
                        <option key={code} value={code}>{code}</option>
                      ))}
                    </select>
                    <select className="rounded-lg border border-black bg-white px-3 py-2 text-sm" value={filterArrival} onChange={(e) => { setFilterArrival(e.target.value); setPage(1); }}>
                      <option value="ALL">Arrival</option>
                      {arrivalOptions.map((code) => (
                        <option key={code} value={code}>{code}</option>
                      ))}
                    </select>
                  </div>

                  <div className="flex flex-wrap items-center gap-2">
                    <span className="text-sm font-medium text-black">Sort By:</span>
                    <select
                      className="rounded-lg border border-black bg-white px-3 py-2 text-sm"
                      value={sortField}
                      onChange={(e) => {
                        setSortField(e.target.value);
                        setPage(1);
                      }}
                    >
                      <option value="departure_time">Departure Time</option>
                      <option value="airport">Airport</option>
                      <option value="airline">Airline</option>
                      <option value="arrival_time">Arrival Time</option>
                      <option value="duration">Duration</option>
                    </select>
                    <select
                      className="rounded-lg border border-black bg-white px-3 py-2 text-sm"
                      value={sortOrder}
                      onChange={(e) => {
                        setSortOrder(e.target.value);
                        setPage(1);
                      }}
                    >
                      <option value="asc">Ascending</option>
                      <option value="desc">Descending</option>
                    </select>
                  </div>
                </div>
              </div>
            )}
            {error && (
              <div className="mb-4 rounded-xl border border-red-600 bg-red-50 px-4 py-3 text-base font-semibold text-red-700">
                {error}
              </div>
            )}
            {viaNoRoutingMatch && (
              <div className="mb-4 rounded-xl border border-red-600 bg-red-50 px-4 py-3 text-base font-semibold text-red-700">
                There are no routings that include your via point
              </div>
            )}

            <div className="space-y-5">
              {renderedFlights.map((f) => {
                const availableWeekdays = isSevenDayMode ? mapFlightDaysToWeek(f.flightDays) : [];
                const defaultIndex = availableWeekdays.findIndex(Boolean);
                const selectedWeekdayIndex = isSevenDayMode
                  ? (selectedDayByFlight[f.id] ?? (defaultIndex >= 0 ? defaultIndex : 0))
                  : -1;
                const selectedWeekdayColumn = isSevenDayMode ? weekColumns[selectedWeekdayIndex] : null;
                const displayDate = isSevenDayMode ? formatWeekdayLabel(selectedWeekdayColumn) : f.depDate;
                const isOpen = printExpandedAll || expanded === f.id;

                return (
                <article key={f.id} className="rounded-2xl bg-white shadow-[0_2px_10px_rgba(13,18,30,0.18)]">
                  {isSevenDayMode && weekColumns.length === 7 && (
                    <div className="px-4 pt-4">
                      <div className="fst-day-rail" style={selectedWeekdayIndex >= 0 ? { "--fst-active-index": selectedWeekdayIndex } : undefined}>
                        {selectedWeekdayIndex >= 0 && <span className="fst-day-indicator" aria-hidden="true"></span>}
                        {weekColumns.map((day) => {
                          const available = !!availableWeekdays[day.index];
                          const active = day.index === selectedWeekdayIndex;

                          return (
                            <button
                              key={`${f.id}-${day.key}`}
                              type="button"
                              disabled={!available}
                              className={`fst-day-btn ${available ? "is-available" : "is-disabled"} ${active ? "is-active" : ""}`}
                              onClick={(e) => {
                                e.stopPropagation();
                                if (!available) return;
                                setSelectedDayByFlight((prev) => ({ ...prev, [f.id]: day.index }));
                                reopenExpandedFlight(f.id);
                              }}
                            >
                              <span className="fst-day-btn-week">{day.weekday}</span>
                              <span className="fst-day-btn-date">{day.month} {day.day}</span>
                            </button>
                          );
                        })}
                      </div>
                    </div>
                  )}

                  <button className="grid w-full gap-4 p-4 text-left md:grid-cols-[1fr_auto]" onClick={() => setExpanded((x) => (x === f.id ? null : f.id))}>
                    <div>
                      <div className="mb-2 flex flex-wrap gap-2">
                        <span className="rounded-full border border-black bg-white px-2 py-1 text-[11px] font-semibold text-black">{f.stops}</span>
                      </div>

                      <div className="grid gap-3 sm:grid-cols-4">
                        <div>
                          <p className="text-lg font-bold"><TimeWithIndicator time={f.depTime} indicator={f.depDayIndicator} /></p>
                          <p className="mt-1 flex items-center gap-2 text-sm font-semibold text-black">
                            <AirportFlag iataCode={f.depCode} airportCountryByIata={lookupData.airportCountryByIata} />
                            <span>{airportDisplay(f.depCode)}</span>
                          </p>
                          <p className="text-xs text-black">{displayDate}</p>
                        </div>
                        <div><p className="text-sm font-semibold text-black">{f.duration}</p><p className="text-xs text-black">{f.stops}</p></div>
                        <div>
                          <p className="text-lg font-bold"><TimeWithIndicator time={f.arrTime} indicator={f.arrDayIndicator} /></p>
                          <p className="mt-1 flex items-center gap-2 text-sm font-semibold text-black">
                            <AirportFlag iataCode={f.arrCode} airportCountryByIata={lookupData.airportCountryByIata} />
                            <span>{airportDisplay(f.arrCode)}</span>
                          </p>
                          <p className="text-xs text-black">{displayDate}</p>
                        </div>
                        <div>
                          <div className="flex items-start gap-2">
                            <AirlineLogo airlineCode={f.airlineCode} airlineName={f.airline} />
                            <div>
                              <p className="text-sm font-semibold text-black">{f.airline}</p>
                              {!!(f.segments?.length) && (
                                <div className="mt-1 space-y-1">
                                  {f.segments.map((seg, idx) => {
                                    const segFlight = `${seg.airlineCode || ""}${seg.flightNumber || ""}`.trim();
                                    const segEquipment = resolveEquipmentName(seg.airEquipType, lookupData.equipmentNameByCode);
                                    const info = [segFlight, segEquipment].filter(Boolean).join(" / ");
                                    return (
                                      <div key={`${f.id}-summary-seg-${idx}`} className="flex flex-wrap items-center justify-between gap-2 text-xs text-black">
                                        <span>{info || "Flight details unavailable"}</span>
                                        {!!seg.operatedBy && (
                                          <span className="rounded-full border border-blue-300 bg-blue-50 px-2 py-[2px] text-[10px] font-semibold text-blue-700">
                                            {seg.operatedBy}
                                          </span>
                                        )}
                                      </div>
                                    );
                                  })}
                                </div>
                              )}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-3">
                      <Icon name="ChevronDown" className={`transition ${isOpen ? "rotate-180" : ""}`} />
                    </div>
                  </button>

                  <div className={`fst-drawer ${isOpen ? "is-open" : "is-closed"}`}>
                    <div className="fst-drawer-inner border-t border-gray-300 bg-white px-6 pb-6 pt-5 text-sm text-black">
                      <div className="rounded-2xl border border-gray-300 bg-white">
                        <div className="flex items-center justify-between border-b border-gray-300 px-5 py-4">
                          <p className="text-[1.05rem] font-semibold">Depart • {displayDate || f.depDate}</p>
                          <p className="text-[1.05rem]">{f.duration}</p>
                        </div>

                        <div className="space-y-3 p-4">
                          {f.segments?.map((seg, idx) => (
                            <React.Fragment key={`${f.id}-seg-${idx}`}>
                              <div className="rounded-xl border border-gray-300 bg-white p-4">
                                <div className="mb-3 flex items-center justify-between gap-3">
                                  <div className="flex items-end gap-2">
                                    <AirlineLogo airlineCode={seg.airlineCode} airlineName={seg.airlineName} />
                                    <div className="flex flex-wrap items-center gap-2">
                                      <span className="text-sm text-black">{seg.airlineName}</span>
                                      {!!(seg.airlineCode && seg.flightNumber) && <span className="text-sm text-black">{`${seg.airlineCode}${seg.flightNumber}`}</span>}
                                      {!!seg.operatedBy && (
                                        <span className="rounded-full border border-blue-300 bg-blue-50 px-2 py-[2px] text-[10px] font-semibold text-blue-700">
                                          {seg.operatedBy}
                                        </span>
                                      )}
                                      {!!seg.airEquipType && (
                                      <span className="rounded-md border border-gray-400 px-3 py-1 text-xs text-black">
                                          {resolveEquipmentName(seg.airEquipType, lookupData.equipmentNameByCode)}
                                        </span>
                                      )}
                                    </div>
                                  </div>

                                  {seg.amenityIcons.length > 0 && (
                                    <div className="relative">
                                      <button
                                        type="button"
                                        onClick={() => setOpenAmenity((prev) => (prev === `${f.id}-${idx}` ? null : `${f.id}-${idx}`))}
                                        className="flex items-center gap-2 rounded-full border border-gray-400 bg-white px-3 py-1 text-black"
                                      >
                                        {seg.amenityIcons.map((iconName) => <Icon key={`${f.id}-${idx}-${iconName}`} name={iconName} size={14} />)}
                                        <Icon name={openAmenity === `${f.id}-${idx}` ? "ChevronUp" : "ChevronDown"} size={14} />
                                      </button>

                                      {openAmenity === `${f.id}-${idx}` && (
                                        <div className="absolute right-0 top-[calc(100%+8px)] z-20 min-w-[320px] rounded-3xl border border-gray-300 bg-white p-4 text-black shadow-2xl">
                                          <div className="space-y-2">
                                            {seg.amenityItems.map((item) => (
                                              <div key={`${f.id}-${idx}-${item.code}-${item.label}`} className="flex items-center gap-3">
                                                <Icon name={item.icon} size={16} />
                                                <span className="text-[1.05rem]">{item.label}</span>
                                              </div>
                                            ))}
                                          </div>
                                        </div>
                                      )}
                                    </div>
                                  )}
                                </div>

                                <div className="grid grid-cols-[72px_1fr] gap-2">
                                  <div className="relative h-[126px]">
                                    <div className="absolute left-[14px] top-3 h-[42px] w-px bg-gray-400"></div>
                                    <div className="absolute left-[14px] bottom-3 h-[42px] w-px bg-gray-400"></div>
                                    <div className="absolute left-[9px] top-0 h-3 w-3 rounded-full border border-gray-400 bg-white"></div>
                                    <div className="absolute left-[9px] bottom-0 h-3 w-3 rounded-full border border-gray-400 bg-white"></div>
                                    <div className="absolute left-[-6px] top-1/2 -translate-y-1/2 bg-white text-black">
                                      <img src={assetUrl("plane.svg")} alt="" className="h-[40px] w-[40px] object-contain" />
                                    </div>
                                  </div>

                                  <div className="space-y-3">
                                    <div className="grid grid-cols-[140px_1fr] items-start gap-3">
                                    <p className="whitespace-nowrap text-2xl font-bold leading-none"><TimeWithIndicator time={seg.depTime} indicator={seg.depDayIndicator} /></p>
                                    <div>
                                      <p className="text-[1.02rem] text-black">{seg.fromLabel}</p>
                                      {seg.depTerminal && (
                                        <p className="text-xs text-black">{seg.depTerminal}</p>
                                      )}
                                    </div>
                                  </div>

                                    <div className="grid grid-cols-[140px_1fr] items-center gap-3">
                                    <p className="whitespace-nowrap text-base font-medium leading-none text-black">{seg.duration}</p>
                                    <p></p>
                                  </div>

                                    <div className="grid grid-cols-[140px_1fr] items-end gap-3">
                                    <p className="whitespace-nowrap text-2xl font-bold leading-none"><TimeWithIndicator time={seg.arrTime} indicator={seg.arrDayIndicator} /></p>
                                    <div>
                                      <p className="text-[1.02rem] text-black">{seg.toLabel}</p>
                                      {seg.arrTerminal && (
                                        <p className="text-xs text-black">{seg.arrTerminal}</p>
                                      )}
                                    </div>
                                  </div>
                                  </div>
                                </div>
                              </div>

                            </React.Fragment>
                          ))}
                        </div>
                      </div>
                    </div>
                  </div>
                </article>
                );
              })}
            </div>

            {!printExpandedAll && sortedFlights.length > PAGE_SIZE && (
              <div className="mt-4 flex items-center justify-between gap-3">
                <button
                  type="button"
                  className="rounded-lg border border-black bg-white px-3 py-2 text-sm disabled:opacity-40"
                  disabled={pageSafe <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Previous
                </button>
                <p className="text-sm">
                  Page {pageSafe} of {totalPages}
                </p>
                <button
                  type="button"
                  className="rounded-lg border border-black bg-white px-3 py-2 text-sm disabled:opacity-40"
                  disabled={pageSafe >= totalPages}
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                >
                  Next
                </button>
              </div>
            )}
          </section>
        )}

        {showEmailModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/35 px-4">
            <div className="w-full max-w-md rounded-2xl bg-white p-5 shadow-[0_16px_48px_rgba(13,18,30,0.24)]">
              <div className="mb-4 flex items-start justify-between gap-4">
                <div>
                  <h3 className="text-lg font-bold text-black">Email Schedule</h3>
                  <p className="mt-1 text-sm text-black/70">Send the current filtered results using `feedback@passrider.com`.</p>
                </div>
                <button
                  type="button"
                  className="rounded-full border border-black px-3 py-1 text-sm font-semibold text-black"
                  onClick={() => {
                    if (emailSending) return;
                    setShowEmailModal(false);
                    setEmailFeedback("");
                  }}
                >
                  Close
                </button>
              </div>

              <label className="block">
                <span className="mb-1 block text-xs uppercase tracking-wider text-black">Recipient Email</span>
                <input
                  type="email"
                  className="w-full rounded-xl border border-black bg-white px-3 py-3 text-sm outline-none transition focus:outline-none"
                  placeholder="name@example.com"
                  value={emailRecipient}
                  onChange={(e) => setEmailRecipient(e.target.value)}
                  disabled={emailSending}
                />
              </label>

              {emailFeedback && (
                <div className={`mt-3 rounded-xl px-4 py-3 text-sm font-semibold ${emailFeedback.startsWith("Schedule sent") ? "border border-green-600 bg-green-50 text-green-700" : "border border-red-600 bg-red-50 text-red-700"}`}>
                  {emailFeedback}
                </div>
              )}

              <div className="mt-4 flex justify-end gap-3">
                <button
                  type="button"
                  className="rounded-xl border border-black px-4 py-2 text-sm font-semibold text-black"
                  onClick={() => {
                    if (emailSending) return;
                    setShowEmailModal(false);
                    setEmailFeedback("");
                  }}
                >
                  Cancel
                </button>
                <button
                  type="button"
                  className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                  onClick={handleSendEmail}
                  disabled={emailSending}
                >
                  <Icon name="Email" size={14} />
                  <span>{emailSending ? "Sending..." : "Send"}</span>
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </main>
  );
}

createRoot(document.getElementById("fst-app")).render(<App />);
