(function () {
  const script = document.currentScript;
  const host = (script?.getAttribute("data-host") || "").replace(/\/$/, "");
  const height = script?.getAttribute("data-height") || "860";

  if (!host) {
    console.error("[FST Widget] Missing data-host on embed.js script tag.");
    return;
  }

  const targetSelector = script?.getAttribute("data-target") || "";
  const mount = targetSelector ? document.querySelector(targetSelector) : null;
  const container = mount || document.createElement("div");

  if (!mount && script?.parentNode) {
    script.parentNode.insertBefore(container, script.nextSibling);
  }

  const params = new URLSearchParams();
  ["apiUrl", "key", "from", "to", "date"].forEach(function (name) {
    const value = script?.getAttribute("data-" + name.toLowerCase());
    if (value) params.set(name, value);
  });

  const iframe = document.createElement("iframe");
  iframe.src = host + "/index.html" + (params.toString() ? "?" + params.toString() : "");
  iframe.style.width = "100%";
  iframe.style.minHeight = height + "px";
  iframe.style.border = "0";
  iframe.style.borderRadius = "16px";
  iframe.loading = "lazy";
  iframe.referrerPolicy = "no-referrer-when-downgrade";
  iframe.title = "Flight Schedule Widget";

  container.appendChild(iframe);
})();
