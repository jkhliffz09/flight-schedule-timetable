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
  const attrs = {
    apiUrl: "data-apiurl",
    proxyUrl: "data-proxyurl",
    emailUrl: "data-emailurl",
    key: "data-key",
    result: "data-result",
    from: "data-from",
    to: "data-to",
    date: "data-date"
  };

  Object.keys(attrs).forEach(function (name) {
    const value = script?.getAttribute(attrs[name]);
    if (value) params.set(name, value);
  });

  const iframe = document.createElement("iframe");
  iframe.src = host + "/index.html" + (params.toString() ? "?" + params.toString() : "");
  iframe.style.width = "100%";
  iframe.style.height = height + "px";
  iframe.style.minHeight = height + "px";
  iframe.style.border = "0";
  iframe.style.borderRadius = "16px";
  iframe.loading = "lazy";
  iframe.referrerPolicy = "no-referrer-when-downgrade";
  iframe.title = "Flight Schedule Widget";
  iframe.scrolling = "no";

  container.appendChild(iframe);

  function onMessage(event) {
    if (!iframe.contentWindow || event.source !== iframe.contentWindow) return;
    const data = event.data || {};
    if (data.type !== "FST_WIDGET_HEIGHT") return;
    const next = Number(data.height || 0);
    if (!Number.isFinite(next) || next <= 0) return;
    iframe.style.height = Math.ceil(next) + "px";
  }

  window.addEventListener("message", onMessage, false);
})();
