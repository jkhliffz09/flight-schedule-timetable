(function () {
  "use strict";

  const defaultFlights = [
    {
      id: 1,
      depTime: "08:20 DVO",
      depDate: "25 Feb (Wed)",
      arrTime: "11:50 HKG",
      arrDate: "25 Feb (Wed)",
      duration: "03h 30min",
      stops: "Direct flight",
      airline: "Cebu Pacific Air",
      price: 285,
      tags: ["Recommended", "Fastest"],
      fare: "Hand baggage",
      layover: "None"
    },
    {
      id: 2,
      depTime: "04:25 DVO",
      depDate: "25 Feb (Wed)",
      arrTime: "11:00 HKG",
      arrDate: "25 Feb (Wed)",
      duration: "06h 35min",
      stops: "1 stop",
      airline: "Cebu Pacific Air",
      price: 353,
      tags: [],
      fare: "Hand baggage",
      layover: "MNL - 2h 20m"
    },
    {
      id: 3,
      depTime: "2:45 pm DVO",
      depDate: "Fri, Feb 20",
      arrTime: "9:45 pm HKG",
      arrDate: "Fri, Feb 20",
      duration: "7h 00m",
      stops: "1 stop",
      airline: "Philippine Airlines",
      price: 17611,
      tags: ["Best"],
      fare: "Economy Value",
      layover: "Change planes in Manila (MNL) - 2h 20m"
    }
  ];

  const dayBuckets = [
    { day: "24 Feb", price: "313 USD" },
    { day: "25 Feb", price: "266 USD", active: true },
    { day: "26 Feb", price: "183 USD" },
    { day: "27 Feb", price: "147 USD" },
    { day: "28 Feb", price: "Find" },
    { day: "1 Mar", price: "144 USD" },
    { day: "2 Mar", price: "169 USD" }
  ];

  function initWidget(shell) {
    const form = shell.querySelector("[data-fst-form]");
    const resultsRoot = shell.querySelector("[data-fst-results]");
    const loading = shell.querySelector("[data-fst-loading]");
    const content = shell.querySelector("[data-fst-content]");
    const list = shell.querySelector("[data-fst-list]");
    const dayStrip = shell.querySelector("[data-fst-day-strip]");
    const total = shell.querySelector("[data-fst-total]");

    if (!form || !resultsRoot || !loading || !content || !list || !dayStrip || !total) {
      return;
    }

    bindTripButtons(form);
    bindSwap(form);
    bindDateStep(form);

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      resultsRoot.classList.remove("hidden");
      content.classList.add("hidden");
      loading.classList.remove("hidden");

      const payload = collectPayload(form);

      window.setTimeout(function () {
        fetchFlights(payload)
          .then(function (data) {
            const flights = Array.isArray(data.flights) && data.flights.length > 0 ? data.flights : defaultFlights;
            total.textContent = String(data.total || flights.length || 0);
            loading.classList.add("hidden");
            renderDayStrip(dayStrip, list, flights);
            renderResultList(list, flights);
            content.classList.remove("hidden");
            resultsRoot.scrollIntoView({ behavior: "smooth", block: "start" });
          })
          .catch(function () {
            total.textContent = "67";
            loading.classList.add("hidden");
            renderDayStrip(dayStrip, list, defaultFlights);
            renderResultList(list, defaultFlights);
            content.classList.remove("hidden");
          });
      }, (window.FlightScheduleTimetableConfig && window.FlightScheduleTimetableConfig.loadingDelay) || 1700);
    });
  }

  function fetchFlights(payload) {
    const config = window.FlightScheduleTimetableConfig || {};
    if (!config.ajaxUrl || !config.nonce) {
      return Promise.resolve({ flights: defaultFlights, total: 67 });
    }

    const params = new URLSearchParams();
    params.set("action", "fst_search");
    params.set("nonce", config.nonce);
    params.set("payload", JSON.stringify(payload));

    return fetch(config.ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: params.toString()
    })
      .then(function (res) {
        if (!res.ok) {
          throw new Error("Search request failed");
        }
        return res.json();
      })
      .then(function (json) {
        if (!json || !json.success || !json.data) {
          return { flights: defaultFlights, total: 67 };
        }
        return json.data;
      });
  }

  function collectPayload(form) {
    const getVal = function (selector, fallback) {
      const el = form.querySelector(selector);
      if (!el) {
        return fallback;
      }
      return typeof el.value === "string" && el.value !== "" ? el.value : fallback;
    };

    const checkedVal = function (name, fallback) {
      const el = form.querySelector('input[name="' + name + '"]:checked');
      return el ? el.value : fallback;
    };

    const directDesktop = form.querySelector('input[name="directOnly"]');
    const directMobile = form.querySelector('input[name="directOnlyMobile"]');

    return {
      from: getVal('input[name="from"]', getVal('input[name="fromMobile"]', "")),
      to: getVal('input[name="to"]', getVal('input[name="toMobile"]', "")),
      departDate: getVal('input[name="departDate"]', getVal('input[name="departDateMobile"]', "")),
      returnDate: getVal('input[name="returnDateMobile"]', ""),
      sortBy: getVal('select[name="sortBy"]', "Departure Time"),
      airlines: getVal('select[name="airlines"]', "All"),
      time: getVal('select[name="time"]', "Any Time"),
      stops: getVal('select[name="stops"]', "Nonstop"),
      via: getVal('input[name="via"]', ""),
      specificDate: checkedVal("specificDate", "yes"),
      codeshare: checkedVal("codeshare", "no"),
      interline: checkedVal("interline", "no"),
      directOnly: !!((directDesktop && directDesktop.checked) || (directMobile && directMobile.checked))
    };
  }

  function bindTripButtons(form) {
    const buttons = form.querySelectorAll("[data-trip]");
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        buttons.forEach(function (el) {
          el.classList.remove("is-active");
        });
        btn.classList.add("is-active");
      });
    });
  }

  function bindSwap(form) {
    const swaps = form.querySelectorAll("[data-fst-swap]");
    swaps.forEach(function (swapButton) {
      swapButton.addEventListener("click", function () {
        const desktopFrom = form.querySelector('input[name="from"]');
        const desktopTo = form.querySelector('input[name="to"]');
        const mobileFrom = form.querySelector('input[name="fromMobile"]');
        const mobileTo = form.querySelector('input[name="toMobile"]');

        if (desktopFrom && desktopTo) {
          const tempDesktop = desktopFrom.value;
          desktopFrom.value = desktopTo.value;
          desktopTo.value = tempDesktop;
        }

        if (mobileFrom && mobileTo) {
          const tempMobile = mobileFrom.value;
          mobileFrom.value = mobileTo.value;
          mobileTo.value = tempMobile;
        }
      });
    });
  }

  function bindDateStep(form) {
    const dateInput = form.querySelector('input[name="departDate"]');
    if (!dateInput) {
      return;
    }

    const buttons = form.querySelectorAll("[data-step]");
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        if (!dateInput.value) {
          return;
        }

        const step = Number(btn.getAttribute("data-step"));
        const current = new Date(dateInput.value + "T00:00:00");
        current.setDate(current.getDate() + step);
        dateInput.value = current.toISOString().slice(0, 10);
      });
    });
  }

  function renderDayStrip(container, list, flights) {
    container.innerHTML = "";
    const left = document.createElement("button");
    left.type = "button";
    left.className = "fst-day-nav";
    left.textContent = "<";
    container.appendChild(left);

    dayBuckets.forEach(function (item, index) {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "fst-day-item" + (item.active ? " is-active" : "");
      button.innerHTML = '<strong class="text-[1.1rem]">' + escapeHtml(item.day) + '</strong><span class="text-slate-600">' + escapeHtml(item.price) + "</span>";
      button.addEventListener("click", function () {
        const all = container.querySelectorAll(".fst-day-item");
        all.forEach(function (el) {
          el.classList.remove("is-active");
        });
        button.classList.add("is-active");
        const orderedFlights = index % 2 === 0 ? flights.slice().reverse() : flights;
        renderResultList(list, orderedFlights);
      });
      container.appendChild(button);
    });

    const right = document.createElement("button");
    right.type = "button";
    right.className = "fst-day-nav";
    right.textContent = ">";
    container.appendChild(right);
  }

  function renderResultList(container, flights) {
    container.innerHTML = "";
    flights.forEach(function (flight) {
      const card = document.createElement("article");
      card.className = "fst-result";

      const summary = document.createElement("div");
      summary.className = "fst-result-summary";
      summary.innerHTML = buildSummaryHTML(flight);
      summary.addEventListener("click", function () {
        card.classList.toggle("is-expanded");
      });

      const details = document.createElement("div");
      details.className = "fst-result-details";
      details.innerHTML = buildDetailsHTML(flight);

      card.appendChild(summary);
      card.appendChild(details);
      container.appendChild(card);
    });
  }

  function buildSummaryHTML(flight) {
    const chips = (flight.tags || [])
      .map(function (tag) {
        return '<span class="fst-chip">' + escapeHtml(tag) + "</span>";
      })
      .join("");

    return [
      '<div class="fst-summary-main">',
      '<div class="mb-1">' + chips + "</div>",
      '<div class="fst-summary-grid">',
      '<div><p class="text-[2rem] font-bold text-slate-900">' + escapeHtml(flight.depTime || "") + '</p><p class="text-[1.65rem] text-slate-700">' + escapeHtml(flight.depDate || "") + "</p></div>",
      '<div class="text-center"><p class="text-[1.8rem] font-semibold text-slate-800">' + escapeHtml(flight.duration || "") + '</p><div class="mt-2 h-[2px] bg-cyan-300"></div><p class="mt-2 text-[1.6rem] text-slate-700">' + escapeHtml(flight.stops || "") + "</p></div>",
      '<div><p class="text-[2rem] font-bold text-slate-900">' + escapeHtml(flight.arrTime || "") + '</p><p class="text-[1.65rem] text-slate-700">' + escapeHtml(flight.arrDate || "") + "</p></div>",
      '<div class="flex items-center gap-2 text-[1.7rem] text-slate-800"><span class="gridicons-airplane"></span><span>' + escapeHtml(flight.airline || "") + "</span></div>",
      "</div>",
      "</div>",
      '<aside class="fst-summary-price">',
      '<p class="text-[2.9rem] font-extrabold text-slate-900">' + formatPrice(flight.price) + ' <span class="text-slate-500">USD</span></p>',
      '<p class="text-[1.15rem] text-slate-500">Price per 1 passenger, for one way</p>',
      '<button type="button" class="fst-select-btn">Choose</button>',
      "</aside>"
    ].join("");
  }

  function buildDetailsHTML(flight) {
    return [
      '<div class="mb-3 flex items-center justify-between text-[1.2rem] font-semibold text-slate-800">',
      '<span>Depart - ' + escapeHtml(flight.depDate || "") + "</span>",
      '<span>' + escapeHtml(flight.duration || "") + "</span>",
      "</div>",
      '<div class="fst-leg">',
      '<div class="mb-2 flex items-center justify-between gap-2 text-[1.05rem] text-slate-600">',
      '<span>' + escapeHtml((flight.airline || "Airline") + ' 1806') + "</span>",
      '<span class="rounded border border-slate-300 px-2 py-1">Airbus A320</span>',
      "</div>",
      '<div class="grid gap-3 md:grid-cols-[120px_1fr]">',
      '<div class="text-[1.35rem] font-bold text-slate-900">' + escapeHtml(flight.depTime || "") + '<br><span class="font-normal text-slate-500">' + escapeHtml(flight.duration || "") + "</span></div>",
      '<div class="text-[1.12rem] text-slate-700">' + escapeHtml(flight.depTime || "") + "<br>to<br>" + escapeHtml(flight.arrTime || "") + "</div>",
      "</div>",
      "</div>",
      '<div class="mt-3 text-[1.05rem] text-slate-600">' + escapeHtml(flight.layover || "") + "</div>",
      '<div class="mt-3 flex items-center justify-end gap-3">',
      '<span class="text-[1.05rem] text-slate-500">2 deals from</span>',
      '<strong class="text-[1.6rem] text-slate-900">' + formatPrice(flight.price) + " USD</strong>",
      '<button type="button" class="fst-select-btn">Select</button>',
      "</div>"
    ].join("");
  }

  function formatPrice(price) {
    const value = Number(price || 0);
    if (!Number.isFinite(value) || value <= 0) {
      return "N/A";
    }
    return value.toLocaleString();
  }

  function escapeHtml(input) {
    return String(input)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".fst-shell").forEach(initWidget);
  });
})();
