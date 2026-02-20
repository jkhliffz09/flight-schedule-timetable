<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="fst-shell not-prose">
  <div class="fst-backdrop"></div>
  <section class="fst-panel mx-auto w-full max-w-[1260px] rounded-[28px] bg-white/95 p-3 sm:p-6">
    <h2 class="fst-mobile-title mb-4 text-center text-4xl font-bold text-white">Flight Schedules</h2>

    <form class="fst-form" data-fst-form>
      <div class="mb-4 flex flex-wrap gap-2 sm:mb-6">
        <button type="button" class="fst-trip-btn is-active rounded-full px-5 py-3 text-base font-semibold" data-trip="oneway">One-way</button>
        <button type="button" class="fst-trip-btn rounded-full px-5 py-3 text-base font-semibold" data-trip="roundtrip">Round-trip</button>
      </div>

      <div class="hidden grid-cols-12 gap-3 md:grid">
        <label class="fst-field col-span-4">
          <span class="fst-label">From</span>
          <input type="text" name="from" value="Manila, Philippines (MNL)" class="fst-input" required>
        </label>

        <button type="button" class="fst-switch col-span-1" aria-label="Swap origin and destination" data-fst-swap>
          <span class="gridicons-sync"></span>
        </button>

        <label class="fst-field col-span-4">
          <span class="fst-label">To</span>
          <input type="text" name="to" value="Hong Kong International Airport, Hong Kong (HKG)" class="fst-input" required>
        </label>

        <div class="fst-field col-span-3">
          <span class="fst-label">Depart</span>
          <div class="flex items-center gap-2">
            <button type="button" class="fst-date-step" data-step="-1" aria-label="Previous day">&#8249;</button>
            <input type="date" name="departDate" value="2026-02-20" class="fst-input">
            <button type="button" class="fst-date-step" data-step="1" aria-label="Next day">&#8250;</button>
          </div>
        </div>
      </div>

      <div class="md:hidden">
        <div class="space-y-0 overflow-hidden rounded-2xl border border-slate-200 bg-white">
          <label class="fst-mobile-field">
            <span class="gridicons-location"></span>
            <div>
              <span class="fst-mobile-label">From</span>
              <input type="text" name="fromMobile" value="Manila, Philippines" class="fst-mobile-input" required>
            </div>
          </label>

          <label class="fst-mobile-field relative">
            <span class="gridicons-location"></span>
            <div>
              <span class="fst-mobile-label">To</span>
              <input type="text" name="toMobile" value="Hong Kong" class="fst-mobile-input" required>
            </div>
            <button type="button" class="fst-mobile-swap" aria-label="Swap origin and destination" data-fst-swap>
              <span class="gridicons-shuffle"></span>
            </button>
          </label>

          <div class="grid grid-cols-2">
            <label class="fst-mobile-field">
              <span class="gridicons-calendar"></span>
              <div>
                <span class="fst-mobile-label">Departure Date</span>
                <input type="date" name="departDateMobile" value="2026-02-20" class="fst-mobile-input">
              </div>
            </label>
            <label class="fst-mobile-field border-l border-slate-200">
              <span class="gridicons-calendar"></span>
              <div>
                <span class="fst-mobile-label">Return Date</span>
                <input type="date" name="returnDateMobile" class="fst-mobile-input">
              </div>
            </label>
          </div>

          <label class="fst-mobile-field">
            <span class="gridicons-user"></span>
            <div>
              <span class="fst-mobile-label">Passengers & Cabin Class</span>
              <div class="flex gap-2">
                <select name="adultsMobile" class="fst-mobile-select">
                  <option>1 Adult</option>
                  <option>2 Adults</option>
                  <option>3 Adults</option>
                </select>
                <select name="cabinMobile" class="fst-mobile-select">
                  <option>Economy</option>
                  <option>Premium Economy</option>
                  <option>Business</option>
                </select>
              </div>
            </div>
          </label>

          <label class="fst-mobile-field">
            <span class="gridicons-credit-card"></span>
            <div>
              <span class="fst-mobile-label">Payment Types</span>
              <select name="paymentMobile" class="fst-mobile-select w-full">
                <option>Visa / Mastercard</option>
                <option>All payment types</option>
                <option>Card only</option>
              </select>
            </div>
          </label>

          <label class="fst-mobile-field justify-between">
            <div class="flex items-center gap-3">
              <span class="gridicons-airplane"></span>
              <span class="text-[1.05rem] font-semibold text-slate-800">Direct flights only</span>
            </div>
            <input type="checkbox" name="directOnlyMobile" class="fst-toggle">
          </label>
        </div>
      </div>

      <div class="mt-5 hidden items-center gap-6 md:flex">
        <label class="inline-flex items-center gap-3 text-2xl font-medium text-slate-800">
          <input type="checkbox" name="directOnly" class="h-7 w-7 rounded border-slate-300">
          Direct flights only
        </label>
        <div class="ml-auto flex items-center gap-5 text-xl text-slate-800">
          <select name="adults" class="fst-inline-select">
            <option>1 Adult</option>
            <option>2 Adults</option>
            <option>3 Adults</option>
          </select>
          <select name="cabin" class="fst-inline-select">
            <option>Economy</option>
            <option>Premium Economy</option>
            <option>Business</option>
          </select>
          <select name="paymentType" class="fst-inline-select">
            <option>2 Payment Types</option>
            <option>All Payment Types</option>
          </select>
        </div>
        <button type="submit" class="fst-search-btn">Search</button>
      </div>

      <div class="mt-4 md:hidden">
        <button type="submit" class="fst-search-btn w-full">Search</button>
      </div>

      <details class="fst-advanced mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
        <summary class="cursor-pointer text-lg font-semibold text-slate-800">Old Form Fields (Advanced Filters)</summary>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
          <label class="fst-small-field">
            <span>Sort By</span>
            <select name="sortBy"><option>Departure Time</option><option>Cheapest</option><option>Duration</option></select>
          </label>
          <label class="fst-small-field">
            <span>Airlines</span>
            <select name="airlines"><option>All</option><option>Cebu Pacific</option><option>Philippine Airlines</option></select>
          </label>
          <label class="fst-small-field">
            <span>Time</span>
            <select name="time"><option>Any Time</option><option>Morning</option><option>Afternoon</option><option>Evening</option></select>
          </label>
          <label class="fst-small-field">
            <span>Stops</span>
            <select name="stops"><option>Nonstop</option><option>1 Stop</option><option>2+ Stops</option></select>
          </label>
          <label class="fst-small-field md:col-span-2">
            <span>Via</span>
            <input type="text" name="via" placeholder="VIA">
          </label>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
          <fieldset class="fst-radio-group">
            <legend>Specific Date</legend>
            <label><input type="radio" name="specificDate" value="yes" checked> Yes</label>
            <label><input type="radio" name="specificDate" value="no"> No</label>
          </fieldset>
          <fieldset class="fst-radio-group">
            <legend>Show Codeshare</legend>
            <label><input type="radio" name="codeshare" value="yes"> Yes</label>
            <label><input type="radio" name="codeshare" value="no" checked> No</label>
          </fieldset>
          <fieldset class="fst-radio-group">
            <legend>Interline</legend>
            <label><input type="radio" name="interline" value="yes"> Yes</label>
            <label><input type="radio" name="interline" value="no" checked> No</label>
          </fieldset>
        </div>
      </details>
    </form>
  </section>

  <section class="fst-results hidden" data-fst-results>
    <div class="fst-loading hidden" data-fst-loading>
      <p class="mb-5 text-[2.25rem] font-medium text-slate-900">We found <span data-fst-total>67</span> flights</p>
      <div class="fst-loading-track">
        <span class="fst-pulse fst-pulse-start"></span>
        <span class="fst-track-line"></span>
        <span class="fst-plane gridicons-airplane"></span>
        <span class="fst-pulse fst-pulse-end"></span>
      </div>
    </div>

    <div class="fst-results-content hidden" data-fst-content>
      <div class="fst-day-strip" data-fst-day-strip></div>
      <div class="mt-6 space-y-4" data-fst-list></div>
    </div>
  </section>
</div>
