/* =========================================================
   ZENTIC - main script (all pages)
   ========================================================= */
(function () {
  "use strict";

  var CONTACT = {
    email: "zainalisahab80@gmail.com",
    whatsapp: "923053698083",
    msg: "Hi Zentic, I want to discuss a design project.",
    phoneIntl: "+92 305 3698083",
    phoneLocal: "0305 3698083",
    socials: {}
  };

  /* Contact details + social links are managed from the admin dashboard
     (Settings tab). They are loaded from settings.php and applied to every page. */
  function applySettings(s) {
    if (!s) return;
    CONTACT.email = s.email || CONTACT.email;
    CONTACT.whatsapp = s.whatsapp || CONTACT.whatsapp;
    CONTACT.msg = s.wa_message || CONTACT.msg;
    CONTACT.phoneIntl = s.phone_intl || CONTACT.phoneIntl;
    CONTACT.phoneLocal = s.phone_local || CONTACT.phoneLocal;
    CONTACT.socials = s.socials || {};

    document.querySelectorAll('a[href^="mailto:"]').forEach(function (a) {
      var q = a.getAttribute("href").split("?")[1];
      a.setAttribute("href", "mailto:" + CONTACT.email + (q ? "?" + q : ""));
      var b = a.querySelector("b");
      if (b && b.textContent.indexOf("@") > -1) b.textContent = CONTACT.email;
      else if (!a.children.length && a.textContent.indexOf("@") > -1) a.textContent = CONTACT.email;
    });
    document.querySelectorAll('a[href^="https://wa.me/"]').forEach(function (a) {
      a.setAttribute("href", "https://wa.me/" + CONTACT.whatsapp + "?text=" + encodeURIComponent(CONTACT.msg));
    });
    var telDigits = CONTACT.phoneIntl.replace(/\D+/g, "");
    document.querySelectorAll('a[href^="tel:"]').forEach(function (a) {
      if (telDigits) a.setAttribute("href", "tel:+" + telDigits);
      if (!a.children.length && /^\+?\d/.test(a.textContent.trim())) a.textContent = CONTACT.phoneIntl;
    });
    document.querySelectorAll("[data-phone-intl]").forEach(function (el) { el.textContent = CONTACT.phoneIntl; });
    document.querySelectorAll("[data-phone-local]").forEach(function (el) { el.textContent = CONTACT.phoneLocal; });
    buildFooter();
  }

  /* ---------- Mobile menu ---------- */
  var toggle = document.getElementById("menuToggle");
  var nav = document.getElementById("mainNav");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("open");
      toggle.classList.toggle("open", open);
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    nav.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        nav.classList.remove("open");
        toggle.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  /* ---------- Footer year ---------- */
  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = new Date().getFullYear();
  });

  /* ---------- Reveal on scroll ---------- */
  var reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); }
      });
    }, { threshold: 0.12 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add("in"); });
  }

  /* ---------- Helpers ---------- */
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function itemHTML(item, index, isLogo) {
    var label = isLogo ? (item.client || "LOGO") : (item.client ? item.client + " · " + (item.category || "Design") : (item.category || "Design"));
    return '<button type="button" class="g-item" data-index="' + index + '" aria-label="Open ' + esc(item.title) + '">' +
      '<div class="ph"><span>' + (isLogo ? "Z" : "Z®") + '</span><small>' + esc(item.title) + '</small></div>' +
      '<img src="' + esc(item.img) + '" alt="' + esc(item.title) + '" loading="lazy">' +
      '<div class="cap"><b>' + esc(item.title) + '</b><span>' + esc(label) + '</span></div>' +
      '</button>';
  }

  /* hide <img> if file is missing (placeholder stays visible) */
  function wireImageFallback(root) {
    root.querySelectorAll("img").forEach(function (img) {
      var item = img.closest(".g-item");
      function failed() { img.style.display = "none"; if (item) item.classList.add("noimg"); }
      img.addEventListener("error", failed);
      if (img.complete && img.naturalWidth === 0) failed();
    });
  }

  /* ---------- Lightbox ---------- */
  var lb = document.getElementById("lightbox");
  var lbList = [];
  var lbIndex = 0;

  function lbRender() {
    if (!lb) return;
    var item = lbList[lbIndex];
    if (!item) return;
    var fig = lb.querySelector("figure");
    fig.innerHTML = "";
    var img = new Image();
    img.alt = item.title;
    img.onload = function () { fig.insertBefore(img, fig.firstChild); };
    img.onerror = function () {
      var ph = document.createElement("div");
      ph.className = "lb-ph";
      ph.textContent = "Z®";
      fig.insertBefore(ph, fig.firstChild);
    };
    img.src = item.img;
    var cap = document.createElement("figcaption");
    var capLabel = item.client ? (item.category ? item.client + " · " + item.category : item.client) : (item.category || "Logo");
    cap.innerHTML = "<b>" + esc(item.title) + "</b><span>" + esc(capLabel) + "</span>";
    fig.appendChild(cap);
  }
  function lbOpen(list, i) {
    if (!lb) return;
    lbList = list; lbIndex = i;
    lbRender();
    lb.classList.add("open");
    document.body.style.overflow = "hidden";
  }
  function lbClose() {
    if (!lb) return;
    lb.classList.remove("open");
    document.body.style.overflow = "";
  }
  function lbStep(d) {
    lbIndex = (lbIndex + d + lbList.length) % lbList.length;
    lbRender();
  }
  if (lb) {
    lb.querySelector(".lb-close").addEventListener("click", lbClose);
    lb.querySelector(".lb-prev").addEventListener("click", function () { lbStep(-1); });
    lb.querySelector(".lb-next").addEventListener("click", function () { lbStep(1); });
    lb.addEventListener("click", function (e) { if (e.target === lb) lbClose(); });
    document.addEventListener("keydown", function (e) {
      if (!lb.classList.contains("open")) return;
      if (e.key === "Escape") lbClose();
      if (e.key === "ArrowLeft") lbStep(-1);
      if (e.key === "ArrowRight") lbStep(1);
    });
  }

  /* ---------- Pictures managed from the admin panel (list.php) ----------
     If list.php works, its list is the single source of truth (add/delete
     in admin.php). If it is not available (e.g. opened as plain files),
     the fallback list in js/gallery-data.js is used. */
  function loadAdminItems(cb) {
    if (!window.fetch) return;
    fetch("list.php", { cache: "no-store" })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (d && Array.isArray(d.gallery) && Array.isArray(d.logos)) cb({ gallery: d.gallery, logos: d.logos });
      })
      .catch(function () {});
  }

  /* ---------- Gallery page ---------- */
  var galleryGrid = document.getElementById("galleryGrid");
  if (galleryGrid) {
    var all = (window.ZENTIC_GALLERY || []).slice();
    var filtersEl = document.getElementById("filters");
    var emptyEl = document.getElementById("galleryEmpty");
    var current = "All";

    var cats = ["All"];
    function collectCats() {
      cats = ["All"];
      all.forEach(function (g) {
        if (g.category && cats.indexOf(g.category) === -1) cats.push(g.category);
      });
    }
    collectCats();

    function renderGallery() {
      var list = all.filter(function (g) { return current === "All" || g.category === current; });
      galleryGrid.innerHTML = list.map(function (g, i) { return itemHTML(g, i, false); }).join("");
      wireImageFallback(galleryGrid);
      galleryGrid.querySelectorAll(".g-item").forEach(function (btn) {
        btn.addEventListener("click", function () {
          lbOpen(list, parseInt(btn.getAttribute("data-index"), 10));
        });
      });
      if (emptyEl) emptyEl.style.display = list.length ? "none" : "block";
    }

    function renderFilters() {
      if (!filtersEl) return;
      filtersEl.innerHTML = cats.map(function (c) {
        return '<button type="button" class="filter-btn' + (c === current ? " active" : "") + '" data-cat="' + esc(c) + '">' + esc(c) + "</button>";
      }).join("");
      filtersEl.querySelectorAll(".filter-btn").forEach(function (b) {
        b.addEventListener("click", function () {
          current = b.getAttribute("data-cat");
          filtersEl.querySelectorAll(".filter-btn").forEach(function (x) { x.classList.toggle("active", x === b); });
          renderGallery();
        });
      });
    }
    renderFilters();
    renderGallery();

    loadAdminItems(function (extra) {
      all = extra.gallery.slice();
      if (current !== "All" && all.every(function (g) { return g.category !== current; })) current = "All";
      collectCats();
      renderFilters();
      renderGallery();
    });
  }

  /* ---------- Logo grid ---------- */
  var logoGrid = document.getElementById("logoGrid");
  if (logoGrid) {
    var logos = (window.ZENTIC_LOGOS || []).slice();
    function renderLogos() {
      logoGrid.innerHTML = logos.map(function (l, i) { return itemHTML(l, i, true); }).join("");
      wireImageFallback(logoGrid);
      logoGrid.querySelectorAll(".g-item").forEach(function (btn) {
        btn.addEventListener("click", function () {
          lbOpen(logos, parseInt(btn.getAttribute("data-index"), 10));
        });
      });
    }
    renderLogos();

    loadAdminItems(function (extra) {
      logos = extra.logos.slice();
      renderLogos();
    });
  }

  /* ---------- Home: our clients (logos) ---------- */
  var clientsGrid = document.getElementById("clientsGrid");
  if (clientsGrid) {
    var clientsSec = document.getElementById("clientsSection");
    var clientList = (window.ZENTIC_LOGOS || []).slice();
    function renderClients() {
      if (clientsSec) clientsSec.style.display = clientList.length ? "" : "none";
      clientsGrid.innerHTML = clientList.map(function (c, i) {
        return '<button type="button" class="client" data-index="' + i + '" aria-label="Open ' + esc(c.title) + '">' +
          '<span class="client-logo"><img src="' + esc(c.img) + '" alt="' + esc(c.title) + '" loading="lazy"></span>' +
          '<b>' + esc(c.client || c.title) + '</b></button>';
      }).join("");
      clientsGrid.querySelectorAll("img").forEach(function (img) {
        img.addEventListener("error", function () { var c = img.closest(".client"); if (c) c.style.display = "none"; });
      });
      clientsGrid.querySelectorAll(".client").forEach(function (btn) {
        btn.addEventListener("click", function () { lbOpen(clientList, parseInt(btn.getAttribute("data-index"), 10)); });
      });
    }
    renderClients();
    loadAdminItems(function (extra) { clientList = extra.logos.slice(); renderClients(); });
  }

  /* ---------- Home: latest work preview ---------- */
  var homeWork = document.getElementById("homeWork");
  if (homeWork) {
    var latest = (window.ZENTIC_GALLERY || []).slice(0, 3);
    function renderHomeWork() {
      homeWork.innerHTML = latest.map(function (g, i) { return itemHTML(g, i, false); }).join("");
      wireImageFallback(homeWork);
      homeWork.querySelectorAll(".g-item").forEach(function (btn) {
        btn.addEventListener("click", function () {
          lbOpen(latest, parseInt(btn.getAttribute("data-index"), 10));
        });
      });
    }
    renderHomeWork();

    loadAdminItems(function (extra) {
      latest = extra.gallery.slice(0, 3);
      renderHomeWork();
    });
  }

  /* ---------- Contact form ---------- */
  var form = document.getElementById("contactForm");
  if (form) {
    var statusEl = document.getElementById("formStatus");

    function setErr(input, msg) {
      var wrap = input.closest(".field");
      var err = wrap.querySelector(".err");
      if (msg) { input.classList.add("invalid"); err.textContent = msg; err.classList.add("show"); }
      else { input.classList.remove("invalid"); err.classList.remove("show"); }
    }

    function validate() {
      var ok = true;
      var name = form.elements["name"];
      var email = form.elements["email"];
      var msg = form.elements["message"];

      if (name.value.trim().length < 2) { setErr(name, "Please enter your name."); ok = false; } else setErr(name, "");
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim())) { setErr(email, "Please enter a valid email."); ok = false; } else setErr(email, "");
      if (msg.value.trim().length < 5) { setErr(msg, "Please write a short message."); ok = false; } else setErr(msg, "");
      return ok;
    }

    function buildText() {
      var f = form.elements;
      var lines = [
        "Hello Zentic,",
        "",
        "Name: " + f["name"].value.trim(),
        "Email: " + f["email"].value.trim()
      ];
      if (f["phone"].value.trim()) lines.push("Phone: " + f["phone"].value.trim());
      lines.push("Service: " + f["service"].value);
      lines.push("", f["message"].value.trim());
      return lines.join("\n");
    }

    function show(msg) {
      statusEl.textContent = msg;
      statusEl.classList.add("show");
    }

    document.getElementById("sendWhatsApp").addEventListener("click", function () {
      if (!validate()) return;
      var url = "https://wa.me/" + CONTACT.whatsapp + "?text=" + encodeURIComponent(buildText());
      window.open(url, "_blank", "noopener");
      show("Opening WhatsApp - just press send there.");
    });

    document.getElementById("sendEmail").addEventListener("click", function () {
      if (!validate()) return;
      var subject = "Zentic Design Project - " + form.elements["service"].value;
      window.location.href = "mailto:" + CONTACT.email +
        "?subject=" + encodeURIComponent(subject) +
        "&body=" + encodeURIComponent(buildText());
      show("Opening your email app - just press send there.");
    });

    form.addEventListener("submit", function (e) { e.preventDefault(); });
    form.querySelectorAll("input,textarea").forEach(function (el) {
      el.addEventListener("input", function () { if (el.classList.contains("invalid")) validate(); });
    });
  }

  /* ---------- Footer: icons ---------- */
  var ICON = {
    whatsapp: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm0 18.15c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 4.54 0 8.24 3.7 8.24 8.24s-3.7 8.24-8.24 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.78.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.23-.16-.48-.29z"/></svg>',
    mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
    phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>',
    facebook: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 8.5V7c0-.8.2-1.2 1.3-1.2H17V3h-2.6C11.6 3 10.5 4.7 10.5 7v1.5H8v3h2.5V21H14v-9.5h2.6l.4-3z"/></svg>',
    instagram: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>',
    youtube: '<svg viewBox="0 0 24 24" fill="currentColor" fill-rule="evenodd" aria-hidden="true"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2C2 8.8 2 12 2 12s0 3.2.4 4.8a2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8c.4-1.6.4-4.8.4-4.8s0-3.2-.4-4.8zM10 15V9l5.2 3z"/></svg>',
    tiktok: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.6 3c.3 2.3 1.6 3.7 3.9 3.9v3.1c-1.4.1-2.7-.3-3.9-1.1v5.9c0 3.7-3 6.2-6.3 5.9-3-.3-5.1-2.8-4.9-5.8.2-3.3 3.1-5.5 6.4-5v3.3c-1.9-.5-3.4.9-3.3 2.5.1 1.5 1.5 2.5 3 2.1 1-.3 1.6-1.2 1.6-2.3V3z"/></svg>',
    linkedin: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.5 9h3.7v11H4.5zM6.3 3.5a2.1 2.1 0 1 1 0 4.2 2.1 2.1 0 0 1 0-4.2zM10 9h3.5v1.5c.6-1 1.8-1.8 3.5-1.8 3.4 0 4 2.2 4 5.1V20h-3.7v-5.4c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9V20H10z"/></svg>',
    google: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M20.5 12.2H12M20.5 12.2A8.5 8.5 0 1 1 18 6"/></svg>',
    yandex: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><text x="12" y="18.5" text-anchor="middle" font-size="19" font-weight="800" font-family="Arial, sans-serif">Я</text></svg>'
  };
  var PLATFORMS = [
    ["google", "Google"], ["yandex", "Yandex"], ["tiktok", "TikTok"],
    ["facebook", "Facebook"], ["instagram", "Instagram"], ["youtube", "YouTube"]
  ];

  function buildFooter() {
    var footer = document.querySelector(".site-footer");
    if (!footer) return;
    footer.querySelectorAll(".footer-social, .brands").forEach(function (n) { n.remove(); });

    /* contact + social icon buttons under the footer text */
    var links = [
      { k: "whatsapp", label: "WhatsApp", url: "https://wa.me/" + CONTACT.whatsapp + "?text=" + encodeURIComponent(CONTACT.msg) },
      { k: "mail", label: "Email", url: "mailto:" + CONTACT.email },
      { k: "phone", label: "Call", url: "tel:+" + CONTACT.phoneIntl.replace(/\D+/g, "") }
    ];
    ["facebook", "instagram", "tiktok", "youtube", "linkedin"].forEach(function (k) {
      var u = CONTACT.socials && CONTACT.socials[k];
      if (u) links.push({ k: k, label: k.charAt(0).toUpperCase() + k.slice(1), url: u, ext: true });
    });
    var firstCol = footer.querySelector(".footer-grid > div:first-child");
    if (firstCol) {
      var soc = document.createElement("div");
      soc.className = "footer-social";
      soc.innerHTML = links.map(function (l) {
        var ext = (l.ext || l.k === "whatsapp") ? ' target="_blank" rel="noopener noreferrer"' : "";
        return '<a class="soc soc-' + l.k + '" href="' + esc(l.url) + '"' + ext + ' aria-label="' + l.label + '" title="' + l.label + '">' + ICON[l.k] + "</a>";
      }).join("");
      firstCol.appendChild(soc);
    }

    /* platforms strip with icons */
    var footerBottom = footer.querySelector(".footer-bottom");
    if (footerBottom) {
      var strip = document.createElement("div");
      strip.className = "brands";
      strip.innerHTML = '<span class="brands-label">Platforms we design for</span><ul class="brands-list">' +
        PLATFORMS.map(function (p) {
          return '<li class="plat plat-' + p[0] + '"><span class="pi">' + ICON[p[0]] + "</span>" + p[1] + "</li>";
        }).join("") + "</ul>";
      footerBottom.parentNode.insertBefore(strip, footerBottom);
    }
  }
  buildFooter();
  if (window.fetch) {
    fetch("settings.php", { cache: "no-store" })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d && d.email) applySettings(d); })
      .catch(function () {});
  }

  /* ---------- Live ratings (ratings.php) ---------- */
  var rvList = document.getElementById("rvList");
  if (rvList) {
    var rvPicked = 0;
    var rvPick = document.getElementById("rvPick");
    var rvForm = document.getElementById("rvForm");
    var rvMsg = document.getElementById("rvMsg");

    function starString(n) {
      var out = "";
      for (var i = 1; i <= 5; i++) out += '<span class="' + (i <= Math.round(n) ? "on" : "") + '">★</span>';
      return out;
    }
    function ago(t) {
      if (!t) return "";
      var d = Math.floor(Date.now() / 1000) - t;
      if (d < 60) return "just now";
      if (d < 3600) return Math.floor(d / 60) + " min ago";
      if (d < 86400) return Math.floor(d / 3600) + " hour" + (Math.floor(d / 3600) > 1 ? "s" : "") + " ago";
      if (d < 86400 * 30) return Math.floor(d / 86400) + " day" + (Math.floor(d / 86400) > 1 ? "s" : "") + " ago";
      return new Date(t * 1000).toLocaleDateString();
    }
    function renderRatings(d) {
      if (!d) return;
      var count = d.count || 0;
      document.getElementById("rvAvg").textContent = count ? Number(d.average).toFixed(1) : "–";
      document.getElementById("rvStars").innerHTML = starString(d.average || 0);
      document.getElementById("rvCount").textContent = count ? "Based on " + count + " rating" + (count > 1 ? "s" : "") : "No ratings yet - be the first!";
      var dist = d.dist || [0, 0, 0, 0, 0];
      var bars = "";
      for (var s = 5; s >= 1; s--) {
        var pct = count ? Math.round((dist[s - 1] / count) * 100) : 0;
        bars += '<div class="rv-bar"><span>' + s + '★</span><i><b style="width:' + pct + '%"></b></i><em>' + (dist[s - 1] || 0) + "</em></div>";
      }
      document.getElementById("rvBars").innerHTML = bars;
      rvList.innerHTML = (d.items || []).map(function (r) {
        return '<article class="rv-item"><div class="rv-av">' + esc((r.name || "A").charAt(0).toUpperCase()) + '</div><div class="rv-body">' +
          '<div class="rv-top"><b>' + esc(r.name || "Anonymous") + '</b><span class="rv-s">' + starString(r.rating) + '</span><small>' + esc(ago(r.time)) + "</small></div>" +
          (r.comment ? "<p>" + esc(r.comment) + "</p>" : "") + "</div></article>";
      }).join("");
    }
    function loadRatings() {
      if (!window.fetch) return;
      fetch("ratings.php", { cache: "no-store" })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(renderRatings)
        .catch(function () {});
    }
    renderRatings({ count: 0, average: 0, dist: [0, 0, 0, 0, 0], items: [] });
    loadRatings();
    setInterval(function () { if (!document.hidden) loadRatings(); }, 30000);

    function paintPick(v) {
      rvPick.querySelectorAll("button").forEach(function (b) {
        b.classList.toggle("on", parseInt(b.getAttribute("data-v"), 10) <= v);
      });
    }
    rvPick.querySelectorAll("button").forEach(function (b) {
      var v = parseInt(b.getAttribute("data-v"), 10);
      b.addEventListener("click", function () { rvPicked = v; paintPick(v); rvMsg.textContent = ""; });
      b.addEventListener("mouseenter", function () { paintPick(v); });
      b.addEventListener("mouseleave", function () { paintPick(rvPicked); });
    });

    rvForm.addEventListener("submit", function (e) {
      e.preventDefault();
      rvMsg.className = "rv-msg";
      if (!rvPicked) { rvMsg.textContent = "Please tap the stars to choose a rating."; rvMsg.classList.add("bad"); return; }
      var fd = new FormData(rvForm);
      fd.append("rating", rvPicked);
      var btn = rvForm.querySelector("button[type=submit]");
      btn.disabled = true;
      fetch("ratings.php", { method: "POST", body: fd })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          btn.disabled = false;
          if (res.ok && res.j && res.j.ok) {
            renderRatings(res.j);
            rvForm.reset(); rvPicked = 0; paintPick(0);
            rvMsg.textContent = "Thank you! Your rating is live. ★";
            rvMsg.classList.add("good");
          } else {
            rvMsg.textContent = (res.j && res.j.error) || "Could not send your rating. Please try again.";
            rvMsg.classList.add("bad");
          }
        })
        .catch(function () {
          btn.disabled = false;
          rvMsg.textContent = "Could not send your rating right now. Please try again.";
          rvMsg.classList.add("bad");
        });
    });
  }

  /* ---------- Floating chat widget (sends to WhatsApp) ---------- */
  (function () {
    var root = document.createElement("div");
    root.className = "zc";
    root.innerHTML =
      '<div class="zc-panel" id="zcPanel" role="dialog" aria-label="Chat with Zentic" aria-hidden="true">' +
        '<div class="zc-head"><div class="zc-av">Z</div>' +
          '<div class="zc-who"><b>Zentic Support</b><small><i></i>Online · replies within a few hours</small></div>' +
          '<button type="button" class="zc-x" aria-label="Close chat">✕</button></div>' +
        '<div class="zc-body" id="zcBody">' +
          '<div class="zc-msg">Hi 👋 Welcome to <b>Zentic</b>! Tell us what you need and we will reply on WhatsApp.</div>' +
          '<div class="zc-chips">' +
            '<button type="button">Logo Design</button><button type="button">Brand Identity</button>' +
            '<button type="button">Social Media Design</button><button type="button">Poster / Flyer</button>' +
          '</div>' +
        '</div>' +
        '<form class="zc-form" id="zcForm"><textarea rows="2" maxlength="500" placeholder="Type your message..." aria-label="Your message"></textarea>' +
          '<button type="submit" aria-label="Send on WhatsApp"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M3 20.5v-6.8l9.5-1.7L3 10.3V3.5L22 12 3 20.5z"/></svg></button></form>' +
        '<div class="zc-foot">Opens WhatsApp · or <a href="mailto:' + CONTACT.email + '?subject=Zentic%20Design%20Project">email us</a></div>' +
      '</div>' +
      '<button type="button" class="zc-btn" aria-label="Open chat" aria-expanded="false" aria-controls="zcPanel"><svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#fff" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12z"/><path d="M8.5 11.5h7M8.5 14.5h4.5" /></svg></button>';
    document.body.appendChild(root);

    var panel = root.querySelector(".zc-panel");
    var btn = root.querySelector(".zc-btn");
    var ta = root.querySelector("textarea");
    var body = root.querySelector("#zcBody");

    function setOpen(o) {
      root.classList.toggle("open", o);
      document.body.classList.toggle("zc-open", o);
      panel.setAttribute("aria-hidden", o ? "false" : "true");
      btn.setAttribute("aria-expanded", o ? "true" : "false");
      btn.setAttribute("aria-label", o ? "Close chat" : "Open chat");
      if (o) setTimeout(function () { ta.focus(); }, 200);
    }
    btn.addEventListener("click", function () { setOpen(!root.classList.contains("open")); });
    root.querySelector(".zc-x").addEventListener("click", function () { setOpen(false); btn.focus(); });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && root.classList.contains("open") && !(lb && lb.classList.contains("open"))) setOpen(false);
    });

    root.querySelectorAll(".zc-chips button").forEach(function (c) {
      c.addEventListener("click", function () {
        ta.value = "Hi Zentic, I need " + c.textContent + ".";
        ta.focus();
      });
    });

    root.querySelector("#zcForm").addEventListener("submit", function (e) {
      e.preventDefault();
      var msg = ta.value.trim();
      if (!msg) { ta.focus(); ta.classList.add("shake"); setTimeout(function () { ta.classList.remove("shake"); }, 400); return; }
      var b = document.createElement("div");
      b.className = "zc-msg me";
      b.textContent = msg;
      body.appendChild(b);
      var ok = document.createElement("div");
      ok.className = "zc-msg";
      ok.textContent = "Opening WhatsApp - just press send there. ✅";
      body.appendChild(ok);
      body.scrollTop = body.scrollHeight;
      window.open("https://wa.me/" + CONTACT.whatsapp + "?text=" + encodeURIComponent(msg), "_blank", "noopener");
      ta.value = "";
    });
  })();
})();
