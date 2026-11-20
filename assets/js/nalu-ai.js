/**
 * Mounts the hosted Nalu chat widget (<my-chatbot-widget>) on the page: the floating
 * chat, and an inline chat wherever [nalu_ai_chatbot mode="inline"] is placed.
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

  const scripts = {};
  const mounts = [];

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

  const fetchConfig = async (agent) => {
    const url = new URL(settings.configUrl, window.location.href);

    if (agent) {
      url.searchParams.set("agent", agent);
    }

    const response = await fetch(url.toString(), {
      credentials: "same-origin",
      cache: "no-store",
    });

    if (!response.ok) {
      throw new Error(`Widget config request failed (${response.status})`);
    }

    return response.json();
  };

  // Deadline in the browser's clock, from the server's relative lifetime, so a
  // skewed visitor clock can't make a fresh token look expired. The floor stops
  // a refresh loop if the server ever hands out a token that is about to expire.
  const refreshDeadline = (expiresIn) =>
    typeof expiresIn === "number" ? Date.now() + Math.max(expiresIn * 1000, MIN_REFRESH_MS) : null;

  /**
   * One chat on the page. `options`: { agent, mode: "floating" | "inline", width,
   * height, container } where `container` is the inline chat's placeholder.
   */
  const createMount = (options) => {
    const state = { element: null, refreshAt: null, timer: null, pending: null };

    const isExpired = () => Boolean(state.element && state.refreshAt && Date.now() >= state.refreshAt);

    const refreshIfExpired = () => {
      if (isExpired() && !document.hidden) {
        mount();
      }
    };

    const scheduleRefresh = () => {
      clearTimeout(state.timer);

      if (state.refreshAt) {
        state.timer = setTimeout(refreshIfExpired, Math.min(state.refreshAt - Date.now(), MAX_TIMEOUT_MS));
      }
    };

    const mount = () => {
      if (state.pending) {
        return state.pending;
      }

      state.pending = (async () => {
        const config = await fetchConfig(options.agent);
        await loadScript(config.scriptUrl);

        // Attributes go on before the element is connected: the widget reads them in connectedCallback.
        const widget = document.createElement(WIDGET_TAG);
        widget.dataset.naluAiWidget = "1";
        widget.setAttribute("api-url", config.apiUrl);
        widget.setAttribute("access-token", config.accessToken);

        if (options.mode === "inline") {
          widget.setAttribute("mode", "inline");
        }

        // An inline chat fills its content column unless the shortcode caps its width.
        const width = options.width || (options.mode === "inline" ? "" : config.width);
        const height = options.height || config.height;

        if (width) widget.setAttribute("width", width);
        if (height) widget.setAttribute("height", height);

        if (state.element && state.element.isConnected) {
          state.element.replaceWith(widget);
        } else if (options.container) {
          options.container.replaceChildren(widget);
        } else {
          document.body.appendChild(widget);
        }

        state.element = widget;
        state.refreshAt = refreshDeadline(config.expiresIn);
        scheduleRefresh();
      })()
        .catch((error) => {
          console.error("Nalu AI:", error);
        })
        .finally(() => {
          state.pending = null;
        });

      return state.pending;
    };

    return { mount, refreshIfExpired };
  };

  const optionsFrom = (placeholder) => ({
    agent: placeholder.dataset.agent || "",
    mode: placeholder.dataset.mode === "inline" ? "inline" : "floating",
    width: placeholder.dataset.width || "",
    height: placeholder.dataset.height || "",
    container: placeholder.dataset.mode === "inline" ? placeholder : null,
  });

  const start = () => {
    // The script is only enqueued when a chatbot should show: in automatic mode, or on a
    // page with the [nalu_ai_chatbot] shortcode.
    const placeholders = Array.from(document.querySelectorAll("[data-nalu-ai-chatbot]")).map(optionsFrom);
    const inline = placeholders.filter((options) => options.mode === "inline");
    // One floating chat at most: two would sit on top of each other.
    const floating = placeholders.find((options) => options.mode === "floating");

    if (floating) {
      mounts.push(createMount(floating));
    } else if (settings.autoFloating && inline.length === 0) {
      mounts.push(createMount({ agent: "", mode: "floating", width: "", height: "", container: null }));
    }

    inline.forEach((options) => mounts.push(createMount(options)));
    mounts.forEach((chat) => chat.mount());
  };

  window.NaluAI = { loaded: true };

  document.addEventListener("visibilitychange", () => {
    mounts.forEach((chat) => chat.refreshIfExpired());
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start, { once: true });
  } else {
    start();
  }
})();
