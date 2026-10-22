/**
 * Mounts the hosted Nalu chat widget (<my-chatbot-widget>) on the page.
 *
 * The widget's access token, and the chat token Nalu derives from it, expire.
 * When that happens the chat stops working, so the widget is replaced with a
 * freshly configured one (on expiry, or when the tab becomes visible again).
 */
(function () {
  "use strict";

  const settings = window.NaluAIConfig || {};
  const WIDGET_TAG = "my-chatbot-widget";
  const MAX_TIMEOUT_MS = 2147483647;
  const MIN_REFRESH_MS = 60 * 1000;

  if (!settings.configUrl || window.NaluAI) {
    return;
  }

  let current = null;
  let pending = null;
  let refreshTimer = null;
  const scripts = {};

  const loadScript = (src) => {
    if (!scripts[src]) {
      scripts[src] = new Promise((resolve, reject) => {
        const script = document.createElement("script");
        script.src = src;
        script.async = true;
        script.addEventListener("load", () => resolve(), { once: true });
        script.addEventListener(
          "error",
          () => {
            delete scripts[src];
            reject(new Error("Failed to load the Nalu widget script"));
          },
          { once: true }
        );
        document.head.appendChild(script);
      });
    }

    return scripts[src];
  };

  const fetchConfig = async () => {
    const response = await fetch(settings.configUrl, {
      credentials: "same-origin",
      cache: "no-store",
    });

    if (!response.ok) {
      throw new Error(`Widget config request failed (${response.status})`);
    }

    return response.json();
  };

  const isExpired = () => Boolean(current && current.refreshAt && Date.now() >= current.refreshAt);

  const refreshIfExpired = () => {
    if (isExpired() && !document.hidden) {
      mount(current.options);
    }
  };

  // Deadline in the browser's clock, from the server's relative lifetime, so a
  // skewed visitor clock can't make a fresh token look expired. The floor stops
  // a refresh loop if the server ever hands out a token that is about to expire.
  const refreshDeadline = (expiresIn) =>
    typeof expiresIn === "number" ? Date.now() + Math.max(expiresIn * 1000, MIN_REFRESH_MS) : null;

  const scheduleRefresh = (refreshAt) => {
    clearTimeout(refreshTimer);

    if (refreshAt) {
      refreshTimer = setTimeout(refreshIfExpired, Math.min(refreshAt - Date.now(), MAX_TIMEOUT_MS));
    }
  };

  const mount = (options) => {
    if (pending) {
      return pending;
    }

    pending = (async () => {
      const config = await fetchConfig();
      await loadScript(config.scriptUrl);

      // Attributes go on before the element is connected: the widget reads them in connectedCallback.
      const widget = document.createElement(WIDGET_TAG);
      widget.dataset.naluAiWidget = "1";
      widget.setAttribute("api-url", config.apiUrl);
      widget.setAttribute("access-token", config.accessToken);

      const width = options.width || config.width;
      const height = options.height || config.height;

      if (width) widget.setAttribute("width", width);
      if (height) widget.setAttribute("height", height);

      if (current && current.element.isConnected) {
        current.element.replaceWith(widget);
      } else {
        document.body.appendChild(widget);
      }

      current = { element: widget, options, refreshAt: refreshDeadline(config.expiresIn) };
      scheduleRefresh(current.refreshAt);
    })()
      .catch((error) => {
        console.error("Nalu AI:", error);
      })
      .finally(() => {
        pending = null;
      });

    return pending;
  };

  const start = () => {
    // The script is only enqueued when the chatbot should show: in automatic mode,
    // or on a page with the [nalu_ai_chatbot] shortcode, which may override the size.
    const placeholder = document.querySelector("[data-nalu-ai-chatbot]");

    mount({
      width: placeholder ? placeholder.dataset.width || "" : "",
      height: placeholder ? placeholder.dataset.height || "" : "",
    });
  };

  window.NaluAI = { version: "2.0.0" };

  document.addEventListener("visibilitychange", refreshIfExpired);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start, { once: true });
  } else {
    start();
  }
})();
