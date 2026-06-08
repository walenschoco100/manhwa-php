const storageKeys = {
  bookmarks: "manhwa-portal-bookmarks",
  legacyBookmarks: "komiknusa-bookmarks",
  ageOk: "manhwa-portal-age-ok",
  legacyAgeOk: "komiknusa-age-ok",
  theme: "manhwa-portal-theme",
  legacyTheme: "komiknusa-theme",
  readChapters: "manhwa-portal-read-chapters"
};

const defaultBrandAssets = {
  logo: "/assets/brand/default-logo.svg",
  favicon: "/assets/brand/default-favicon.svg"
};

const themePaletteVars = {
  bg: "--bg",
  surface: "--surface",
  panel: "--panel",
  text: "--text",
  muted: "--muted",
  line: "--line",
  accent: "--accent",
  accent2: "--accent-2",
  gold: "--gold"
};

const state = {
  query: "",
  genre: "all",
  status: "all",
  type: "all",
  sort: "updated",
  view: "grid",
  catalogPage: 1,
  quickFilter: "all",
  popularRange: "today",
  recommendType: "Manhwa",
  featuredIndex: 0,
  detailSlug: "",
  detailChapterQuery: "",
  detailChapterPage: 1,
  detailChapterSort: "desc",
  detailChapterRange: "all",
  readerPageIndex: 0,
  readerAutoNavigated: false,
  settings: {},
  comics: [],
  bookmarks: new Set(JSON.parse(localStorage.getItem(storageKeys.bookmarks) || localStorage.getItem(storageKeys.legacyBookmarks) || "[]")),
  readChapters: new Set(JSON.parse(localStorage.getItem(storageKeys.readChapters) || "[]"))
};

const els = {
  ageGate: document.querySelector("#ageGate"),
  enterSite: document.querySelector("#enterSite"),
  homeView: document.querySelector("#homeView"),
  detailPage: document.querySelector("#detailPage"),
  readerPage: document.querySelector("#readerPage"),
  siteFooter: document.querySelector("#siteFooter"),
  searchForm: document.querySelector("#searchForm"),
  searchInput: document.querySelector("#searchInput"),
  mobileSearchToggle: document.querySelector("#mobileSearchToggle"),
  searchSuggestions: document.querySelector("#searchSuggestions"),
  genreFilter: document.querySelector("#genreFilter"),
  statusFilter: document.querySelector("#statusFilter"),
  typeFilter: document.querySelector("#typeFilter"),
  sortFilter: document.querySelector("#sortFilter"),
  resetFilters: document.querySelector("#resetFilters"),
  latestList: document.querySelector("#latestList"),
  sourceLatestList: document.querySelector("#sourceLatestList"),
  typeTabs: document.querySelector("#typeTabs"),
  quickFilterTabs: document.querySelector("#quickFilterTabs"),
  genreRail: document.querySelector("#genreRail"),
  popularTabs: document.querySelector("#popularTabs"),
  homePagination: document.querySelector("#homePagination"),
  featuredHero: document.querySelector("#featuredHero"),
  heroManhwaList: document.querySelector("#heroManhwaList"),
  comicGrid: document.querySelector("#comicGrid"),
  rankingList: document.querySelector("#rankingList"),
  bookmarkList: document.querySelector("#bookmarkList"),
  resultCount: document.querySelector("#resultCount"),
  totalTitles: document.querySelector("#totalTitles"),
  totalChapters: document.querySelector("#totalChapters"),
  totalOngoing: document.querySelector("#totalOngoing"),
  footerText: document.querySelector("#footerText"),
  themeToggle: document.querySelector("#themeToggle"),
  viewTabs: document.querySelector(".view-tabs")
};

const colors = [
  ["#e25d4f", "#2c6b8f"],
  ["#44b0a7", "#43336f"],
  ["#e8bd64", "#7c3034"],
  ["#6da1d7", "#212734"],
  ["#d879a4", "#24433e"],
  ["#9ccf74", "#40354d"],
  ["#f09155", "#23505a"],
  ["#c7a7ff", "#4b3d29"]
];

init();

async function init() {
  if (localStorage.getItem(storageKeys.ageOk) === "yes" || localStorage.getItem(storageKeys.legacyAgeOk) === "yes") {
    els.ageGate.classList.add("hidden");
  }

  if (localStorage.getItem(storageKeys.theme) === "light" || localStorage.getItem(storageKeys.legacyTheme) === "light") {
    document.documentElement.classList.add("light");
  }

  bindEvents();
  renderLoadingShell();
  await loadSettings();
  await loadData();
  hydrateFilters();
  applySettings();
  renderShared();
  route();
}

function bindEvents() {
  els.enterSite.addEventListener("click", () => {
    localStorage.setItem(storageKeys.ageOk, "yes");
    els.ageGate.classList.add("hidden");
  });

  els.searchForm?.addEventListener("submit", event => {
    event.preventDefault();
    const rawQuery = els.searchInput?.value.trim() || "";
    state.query = rawQuery.toLowerCase();
    state.catalogPage = 1;
    hideSearchSuggestions();
    setMobileSearchOpen(false);
    navigate(rawQuery ? `/search?q=${encodeURIComponent(rawQuery)}` : "/#update");
  });

  els.mobileSearchToggle?.addEventListener("click", () => {
    const isOpen = document.body.classList.toggle("mobile-search-open");
    els.mobileSearchToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    if (isOpen) requestAnimationFrame(() => els.searchInput?.focus());
    if (!isOpen) hideSearchSuggestions();
  });

  els.searchInput?.addEventListener("input", event => {
    renderSearchSuggestions(event.target.value);
  });

  els.searchInput?.addEventListener("focus", event => {
    renderSearchSuggestions(event.target.value);
  });

  els.genreFilter.addEventListener("change", event => {
    state.genre = event.target.value;
    state.catalogPage = 1;
    renderCatalog();
  });

  els.statusFilter.addEventListener("change", event => {
    state.status = event.target.value;
    state.catalogPage = 1;
    renderCatalog();
  });

  els.typeFilter.addEventListener("change", event => {
    state.type = event.target.value;
    state.catalogPage = 1;
    renderCatalog();
  });

  els.sortFilter.addEventListener("change", event => {
    state.sort = event.target.value;
    state.catalogPage = 1;
    renderCatalog();
  });

  els.resetFilters.addEventListener("click", () => {
    state.query = "";
    state.genre = "all";
    state.status = "all";
    state.type = "all";
    state.sort = "updated";
    state.catalogPage = 1;
    if (els.searchInput) els.searchInput.value = "";
    els.genreFilter.value = "all";
    els.statusFilter.value = "all";
    els.typeFilter.value = "all";
    els.sortFilter.value = "updated";
    renderCatalog();
  });

  els.popularTabs.addEventListener("click", event => {
    const button = event.target.closest("[data-range]");
    if (!button) return;
    state.popularRange = button.dataset.range;
    els.popularTabs.querySelectorAll("button").forEach(item => item.classList.toggle("active", item === button));
    renderRanking();
  });

  els.themeToggle?.addEventListener("click", () => {
    document.documentElement.classList.toggle("light");
    localStorage.setItem(storageKeys.theme, document.documentElement.classList.contains("light") ? "light" : "dark");
  });

  els.viewTabs?.addEventListener("click", event => {
    const button = event.target.closest("[data-view-mode]");
    if (!button) return;
    state.view = button.dataset.viewMode === "compact" ? "compact" : "grid";
    state.catalogPage = 1;
    els.viewTabs.querySelectorAll("[data-view-mode]").forEach(item => {
      item.classList.toggle("active", item === button);
    });
    renderCatalog();
  });

  els.quickFilterTabs?.addEventListener("click", event => {
    const button = event.target.closest("[data-quick-filter]");
    if (!button) return;
    state.quickFilter = button.dataset.quickFilter || "all";
    state.catalogPage = 1;
    els.quickFilterTabs.querySelectorAll("[data-quick-filter]").forEach(item => {
      item.classList.toggle("active", item === button);
    });
    renderCatalog();
  });

  document.addEventListener("click", event => {
    const bookmark = event.target.closest("[data-bookmark]");
    if (bookmark) {
      event.preventDefault();
      event.stopPropagation();
      toggleBookmark(bookmark.dataset.bookmark);
      return;
    }

    const pageButton = event.target.closest("[data-page-number], [data-page-action]");
    if (pageButton) {
      const totalPages = Math.max(1, Math.ceil(getFilteredComics().length / catalogPageSize()));
      if (pageButton.dataset.pageNumber) {
        state.catalogPage = Number(pageButton.dataset.pageNumber) || 1;
      } else if (pageButton.dataset.pageAction === "prev") {
        state.catalogPage = Math.max(1, state.catalogPage - 1);
      } else if (pageButton.dataset.pageAction === "next") {
        state.catalogPage = Math.min(totalPages, state.catalogPage + 1);
      }
      renderCatalog();
      document.querySelector("#update")?.scrollIntoView({ behavior: "smooth", block: "start" });
      return;
    }

    const detailPageButton = event.target.closest("[data-detail-page-number], [data-detail-page-action]");
    if (detailPageButton) {
      const comic = findComic(state.detailSlug);
      const totalPages = Math.max(1, Math.ceil(filteredDetailChapters(comic).length / detailChapterPageSize()));
      if (detailPageButton.dataset.detailPageNumber) {
        state.detailChapterPage = Number(detailPageButton.dataset.detailPageNumber) || 1;
      } else if (detailPageButton.dataset.detailPageAction === "prev") {
        state.detailChapterPage = Math.max(1, state.detailChapterPage - 1);
      } else if (detailPageButton.dataset.detailPageAction === "next") {
        state.detailChapterPage = Math.min(totalPages, state.detailChapterPage + 1);
      }
      if (comic) renderDetailPage(comic.slug);
      document.querySelector(".chapter-panel")?.scrollIntoView({ behavior: "smooth", block: "start" });
      return;
    }

    const rangeButton = event.target.closest("[data-chapter-range]");
    if (rangeButton) {
      state.detailChapterRange = rangeButton.dataset.chapterRange || "all";
      state.detailChapterPage = 1;
      const comic = findComic(state.detailSlug);
      if (comic) renderDetailPage(comic.slug);
      return;
    }

    const chapterReset = event.target.closest("[data-chapter-reset]");
    if (chapterReset) {
      const comic = findComic(state.detailSlug);
      state.detailChapterQuery = "";
      state.detailChapterPage = 1;
      if (comic) renderDetailPage(comic.slug);
      return;
    }

    const retryImage = event.target.closest("[data-retry-image]");
    if (retryImage) {
      const wrap = retryImage.closest(".reader-image-wrap");
      const image = wrap?.querySelector("img");
      if (image) {
        retryImage.hidden = true;
        wrap.classList.remove("failed", "loaded");
        image.src = `${image.src.split("?retry=")[0]}?retry=${Date.now()}`;
      }
      return;
    }

    const routeLink = event.target.closest("a[data-route]");
    if (routeLink) {
      const url = new URL(routeLink.href);
      if (url.origin === window.location.origin) {
        event.preventDefault();
        hideSearchSuggestions();
        navigate(`${url.pathname}${url.search}${url.hash}`);
      }
    }

    if (!event.target.closest(".shin-search")) {
      hideSearchSuggestions();
    }

    if (event.target.closest(".reader-images img, .reader-image-wrap")) {
      showReaderControls();
    }
  });

  document.addEventListener("change", event => {
    const chapterSelect = event.target.closest("[data-reader-select]");
    if (chapterSelect?.value) {
      navigate(chapterSelect.value);
      return;
    }

    const chapterSort = event.target.closest("[data-chapter-sort]");
    if (chapterSort) {
      state.detailChapterSort = chapterSort.value === "asc" ? "asc" : "desc";
      state.detailChapterPage = 1;
      const comic = findComic(state.detailSlug);
      if (comic) renderDetailPage(comic.slug);
      return;
    }

  });

  document.addEventListener("input", event => {
    const input = event.target.closest("[data-chapter-search]");
    if (!input) return;
    state.detailChapterQuery = input.value.trim();
    state.detailChapterPage = 1;
    const comic = findComic(state.detailSlug);
    if (comic) renderDetailPage(comic.slug);
    requestAnimationFrame(() => {
      const nextInput = document.querySelector("[data-chapter-search]");
      nextInput?.focus();
      nextInput?.setSelectionRange(nextInput.value.length, nextInput.value.length);
    });
  });

  window.addEventListener("popstate", route);
}

async function loadData() {
  const response = await fetch("/data/manhwa.json");
  state.comics = await response.json();
}

function renderLoadingShell() {
  if (els.featuredHero) {
    els.featuredHero.innerHTML = `<div class="skeleton-hero"></div>`;
  }
  if (els.latestList) {
    els.latestList.innerHTML = Array.from({ length: 6 }, () => `<div class="skeleton-card"></div>`).join("");
  }
  if (els.comicGrid) {
    els.comicGrid.innerHTML = Array.from({ length: catalogPageSize() }, () => `<div class="skeleton-update"></div>`).join("");
  }
  if (els.rankingList) {
    els.rankingList.innerHTML = Array.from({ length: 6 }, () => `<li class="skeleton-card"></li>`).join("");
  }
}

async function loadSettings() {
  try {
    const response = await fetch("/api/settings");
    const data = await response.json();
    if (response.ok && data.ok) state.settings = data.settings || {};
  } catch {
    state.settings = {};
  }
}

function applySettings() {
  const settings = state.settings;
  const siteTitle = settings.siteTitle || "ManhwaLanded - Portal Manhwa";
  const logoText = settings.headerLogoText || siteTitle.split("-")[0].trim() || "MANHWALANDED";
  const logoUrl = settings.logoUrl || defaultBrandAssets.logo;
  const faviconUrl = settings.faviconUrl || defaultBrandAssets.favicon;
  const brandLogoMode = settings.brandLogoMode || "image-text";

  document.title = siteTitle;
  document.body.classList.toggle("brand-logo-image-only", brandLogoMode === "image-only");
  document.body.classList.toggle("brand-logo-text-only", brandLogoMode === "text-only");
  applyThemePalette(settings.themePalette || {});
  setMeta("description", settings.metaDescription || "");
  setMeta("keywords", settings.metaKeywords || "");
  setMeta("og:title", siteTitle, "property");
  setMeta("og:description", settings.metaDescription || "", "property");
  setMeta("og:image", settings.ogImageUrl || logoUrl, "property");
  setCanonical(settings.canonicalUrl || "");
  setFavicon(faviconUrl);

  document.querySelectorAll(".shin-brand").forEach(brand => {
    const logo = brand.querySelector(".shin-logo");
    const text = brand.querySelector(".shin-brand-text");
    brand.classList.toggle("brand-image-only", brandLogoMode === "image-only");
    brand.classList.toggle("brand-text-only", brandLogoMode === "text-only");
    if (text) text.textContent = logoText;
    if (!logo) return;
    if (logoUrl && brandLogoMode !== "text-only") {
      logo.classList.add("has-logo-image");
      logo.innerHTML = `<img src="${escapeHtml(logoUrl)}" alt="">`;
    } else {
      logo.classList.remove("has-logo-image");
      logo.textContent = logoText.slice(0, 1) || "K";
    }
  });

  if (els.footerText) {
    els.footerText.innerHTML = settings.footerText || `Copyright ©2026 ${escapeHtml(logoText)}. All rights reserved.`;
  }
}

function applyThemePalette(palette = {}) {
  Object.entries(themePaletteVars).forEach(([key, variable]) => {
    const value = String(palette[key] || "").trim();
    if (/^#[0-9a-f]{6}$/i.test(value)) {
      document.documentElement.style.setProperty(variable, value);
    }
  });
}

function setMeta(name, content, attrName = "name") {
  if (!content) return;
  let meta = document.querySelector(`meta[${attrName}="${name}"]`);
  if (!meta) {
    meta = document.createElement("meta");
    meta.setAttribute(attrName, name);
    document.head.append(meta);
  }
  meta.content = content;
}

function setCanonical(href) {
  if (!href) return;
  let link = document.querySelector(`link[rel="canonical"]`);
  if (!link) {
    link = document.createElement("link");
    link.rel = "canonical";
    document.head.append(link);
  }
  link.href = href.replace(/\/$/, "") + window.location.pathname;
}

function renderSchema(data) {
  document.querySelector("#pageSchema")?.remove();
  if (!data || state.settings.schemaEnabled === false) return;
  const script = document.createElement("script");
  script.id = "pageSchema";
  script.type = "application/ld+json";
  script.textContent = JSON.stringify(data);
  document.head.append(script);
}

function setFavicon(href) {
  if (!href) return;
  let link = document.querySelector("link[rel='icon']");
  if (!link) {
    link = document.createElement("link");
    link.rel = "icon";
    document.head.append(link);
  }
  link.href = href;
}

function siteName() {
  return state.settings.headerLogoText || state.settings.siteTitle?.split("-")[0]?.trim() || "ManhwaLanded";
}

function comicTypeLabel(comic) {
  return String(comic?.type || "manga").toLowerCase();
}

function updatePageSeo({ title, description, image = "" }) {
  if (title) document.title = title;
  if (description) {
    setMeta("description", description);
    setMeta("og:description", description, "property");
  }
  if (title) setMeta("og:title", title, "property");
  if (image || state.settings.ogImageUrl || state.settings.logoUrl) {
    setMeta("og:image", image || state.settings.ogImageUrl || state.settings.logoUrl || defaultBrandAssets.logo, "property");
  }
  setCanonical(state.settings.canonicalUrl || "");
}

function detailSeoDescription(comic) {
  return `Baca ${comicTypeLabel(comic)} ${comic.title} bahasa Indonesia di ${siteName()}. Lihat daftar chapter lengkap, genre, status, dan update terbaru.`;
}

function readerSeoDescription(comic, chapter) {
  return `Baca ${comicTypeLabel(comic)} ${comic.title} ${cleanChapterTitle(chapter.title)} bahasa Indonesia di ${siteName()}. Nikmati chapter lengkap dengan gambar yang sudah tersimpan.`;
}

function hydrateFilters() {
  const genres = [...new Set(state.comics.flatMap(comic => comic.genres))].sort((a, b) => a.localeCompare(b));
  els.genreFilter.innerHTML = `<option value="all">Semua genre</option>${genres.map(genre => `<option value="${escapeHtml(genre)}">${escapeHtml(genre)}</option>`).join("")}`;
}

function renderShared() {
  renderStats();
  renderTypeTabs();
  renderGenreRail();
  renderFeatured();
  renderLatest();
  renderSourceLatest();
  renderRanking();
  renderCatalog();
  renderBookmarks();
}

function route() {
  const parts = window.location.pathname.split("/").filter(Boolean).map(decodeURIComponent);

  if (parts[0] === "search") {
    renderSearchPage(new URLSearchParams(window.location.search).get("q") || "");
    return;
  }

  if (parts[0] === "manga" && parts[1]) {
    renderDetailPage(parts[1]);
    return;
  }

  if (parts[0] === "read" && parts[1]) {
    renderReaderPage(parts[1], parts[2]);
    return;
  }

  if (parts.length === 1) {
    const match = findComicByChapterSlug(parts[0]);
    if (match) {
      renderReaderPage(match.comic.slug, match.chapterSlug);
      return;
    }
  }

  showView("home");
  updateCatalogHeading("Update");
  if (state.query) {
    state.query = "";
    state.catalogPage = 1;
    if (els.searchInput) els.searchInput.value = "";
    renderCatalog();
  }
  updateNav();
  updatePageSeo({
    title: state.settings.siteTitle || "ManhwaLanded - Portal Manhwa",
    description: state.settings.metaDescription || "Baca manhwa, manga, dan manhua bahasa Indonesia dengan update chapter terbaru.",
    image: state.settings.ogImageUrl || state.settings.logoUrl || defaultBrandAssets.logo
  });
  if (window.location.hash) {
    requestAnimationFrame(() => document.querySelector(window.location.hash)?.scrollIntoView({ behavior: "smooth", block: "start" }));
  }
}

function navigate(path) {
  const next = new URL(path, window.location.origin);
  if (`${next.pathname}${next.search}${next.hash}` !== `${window.location.pathname}${window.location.search}${window.location.hash}`) {
    history.pushState({}, "", `${next.pathname}${next.search}${next.hash}`);
  }
  route();
  if (!next.hash) window.scrollTo({ top: 0, behavior: "smooth" });
}

function showView(view) {
  els.homeView.hidden = view !== "home";
  els.detailPage.hidden = view !== "detail";
  els.readerPage.hidden = view !== "reader";
  els.siteFooter.hidden = view === "reader";
  document.body.classList.toggle("is-reader", view === "reader");
  if (view !== "reader") window.removeEventListener("scroll", handleReaderScroll);
  if (view === "home") renderSchema(null);
}

function updateNav() {
  const nav = window.location.pathname === "/" || window.location.pathname === "/index.html"
    ? (window.location.hash || "#home").replace("#", "")
    : "";
  document.querySelectorAll(".bottom-nav a").forEach(link => {
    link.classList.toggle("active", link.dataset.nav === nav || (!nav && link.dataset.nav === "home"));
  });
}

function renderStats() {
  if (els.totalTitles) els.totalTitles.textContent = state.comics.length;
  if (els.totalChapters) els.totalChapters.textContent = state.comics.reduce((sum, comic) => sum + comic.chapters.length, 0);
  if (els.totalOngoing) els.totalOngoing.textContent = state.comics.filter(comic => comic.status === "Ongoing").length;
}

function renderFeatured() {
  const items = featuredComics();
  const featured = items[0];

  if (!featured) {
    els.featuredHero.innerHTML = `<div class="empty-state wide">Belum ada manhwa populer untuk hero.</div>`;
    return;
  }

  state.featuredIndex = 0;
  const chapter = featured.chapters?.[0];
  const chapterLabel = chapter ? cleanChapterTitle(chapter.title).replace(/^Chapter\s*/i, "Chapter: ") : "Chapter: 1";
  const readHref = mangaPath(featured);
  const heroImage = heroImageFor(featured);
  const heroGenre = (featured.genres || []).find(Boolean) || featured.type || "Manhwa";
  const tags = [...new Set([...(featured.genres || []), featured.type].filter(Boolean))]
    .slice(0, 6)
    .map(tag => `<span>${escapeHtml(tag)}</span>`)
    .join("");
  const synopsis = featured.synopsis || "Buka chapter terbaru dan lanjutkan membaca koleksi yang sudah tersimpan di website ini.";

  els.featuredHero.innerHTML = `
    <div class="home-featured-shell">
      <article class="home-featured-hero" style="--hero-image: url('${escapeHtml(assetSrc(heroImage))}')">
        <a class="home-featured-visual" href="${mangaPath(featured)}" data-route aria-label="Buka ${escapeHtml(featured.title)}"></a>
        <div class="home-featured-copy">
          <p class="chapter-kicker">${escapeHtml(chapterLabel)}</p>
          <p class="home-featured-title">${escapeHtml(featured.title)}</p>
          <div class="home-featured-meta">
            <span class="hero-rating">★ ${escapeHtml(String(featured.rating || "8.6"))}</span>
            <span class="hero-genre">${escapeHtml(heroGenre)}</span>
          </div>
          <p class="home-featured-synopsis">${escapeHtml(synopsis)}</p>
          <div class="home-featured-tags">${tags}</div>
          <a class="start-reading" href="${readHref}" data-route>Start Reading &rarr;</a>
        </div>
      </article>
    </div>
  `;
  renderHeroManhwa(items);
}

function renderHeroManhwa(items = featuredComics()) {
  if (!els.heroManhwaList) return;
  const active = items[state.featuredIndex]?.slug;
  const manhwa = items
    .filter(comic => comic.slug !== active)
    .slice(0, 4);
  els.heroManhwaList.innerHTML = manhwa.length ? manhwa.map(comic => {
    const chapter = comic.chapters?.[0];
    const image = comic.cover || comic.image || heroImageFor(comic);
    return `
      <a class="hero-manhwa-item" href="${mangaPath(comic)}" data-route>
        <span class="hero-manhwa-cover">
          ${image ? `<img src="${escapeHtml(assetSrc(image))}" alt="">` : `<span>${escapeHtml(comic.title.slice(0, 1))}</span>`}
        </span>
        <span class="hero-manhwa-copy">
          <strong>${escapeHtml(comic.title)}</strong>
          <small>${chapter ? escapeHtml(cleanChapterTitle(chapter.title)) : `${comic.chapters?.length || 0} chapter`}</small>
        </span>
      </a>
    `;
  }).join("") : `<div class="empty-state compact">Belum ada manhwa.</div>`;
}

function featuredComics() {
  const manual = comicsBySlugs(state.settings.heroSlugs);
  const ranked = rankByRange(
    state.comics.filter(comic => comic.type === "Manhwa" && (comic.cover || comic.image || comic.chapters?.some(chapter => chapter.images?.length)) && comic.chapters?.length),
    "all"
  );
  const source = state.settings.heroMode === "manual" && manual.length
    ? [...manual, ...ranked.filter(comic => !manual.some(item => item.slug === comic.slug))]
    : ranked;
  return source
    .slice(0, 7);
}

function heroImageFor(comic) {
  return comic.cover || comic.image || comic.chapters?.find(chapter => chapter.images?.length)?.images?.[5] || "";
}

function renderLatest() {
  const manual = comicsBySlugs(state.settings.recommendSlugs);
  const latest = (state.settings.recommendMode === "manual" && manual.length ? manual : seededShuffle(state.comics
    .filter(comic => comic.type === "Manhwa" && (comic.cover || comic.image))))
    .slice(0, 8);

  els.latestList.innerHTML = latest.length
    ? latest.map((comic, index) => renderRecommendItem(comic, index)).join("")
    : `<div class="empty-state wide">Belum ada rekomendasi Manhwa.</div>`;
}

function seededShuffle(items) {
  const daySeed = Math.floor(Date.now() / 86400000);
  return [...items]
    .map((item, index) => ({
      item,
      rank: seededNumber(`${item.slug || item.title}-${daySeed}-${index}`)
    }))
    .sort((a, b) => a.rank - b.rank)
    .map(entry => entry.item);
}

function seededNumber(value) {
  let hash = 2166136261;
  for (let index = 0; index < value.length; index += 1) {
    hash ^= value.charCodeAt(index);
    hash = Math.imul(hash, 16777619);
  }
  return hash >>> 0;
}

function renderSourceLatest() {
  const latest = [...state.comics]
    .sort((a, b) => new Date(b.updatedAt) - new Date(a.updatedAt))
    .slice(0, 10);

  els.sourceLatestList.innerHTML = latest.map((comic, index) => renderShelfItem(comic, index)).join("");
}

function renderRanking() {
  const manual = comicsBySlugs(state.settings.popularSlugs);
  const popularSource = state.settings.popularMode === "manual" && manual.length ? manual : state.comics;
  const popular = rankByRange(popularSource, state.popularRange)
    .slice(0, 8);

  els.rankingList.innerHTML = popular.length ? popular.map((comic, index) => `
    <li class="popular-card" style="--cover-a: ${colors[index % colors.length][0]}; --cover-b: ${colors[index % colors.length][1]}">
      <a href="${mangaPath(comic)}" data-route>
        <span class="cover" aria-hidden="true">
          ${coverMarkup(comic)}
          <span class="rank-badge">${index + 1}</span>
          <span class="country-dot">${countryFor(comic.type)}</span>
          <span class="cover-title">${escapeHtml(comic.title)}</span>
        </span>
        <strong>${escapeHtml(comic.title)}</strong>
      </a>
    </li>
  `).join("") : `<li class="empty-state wide">Belum ada data populer.</li>`;
}

function renderTypeTabs() {
  els.typeTabs.innerHTML = "";
}

function renderGenreRail() {
  const genres = [...new Set(state.comics.flatMap(comic => comic.genres))].sort((a, b) => a.localeCompare(b)).slice(0, 18);
  els.genreRail.innerHTML = [
    `<button class="${state.genre === "all" ? "active" : ""}" type="button" data-genre-value="all">Semua genre</button>`,
    ...genres.map(genre => `<button class="${state.genre === genre ? "active" : ""}" type="button" data-genre-value="${escapeHtml(genre)}">${escapeHtml(genre)}</button>`)
  ].join("");

  els.genreRail.querySelectorAll("[data-genre-value]").forEach(button => {
    button.addEventListener("click", () => {
      state.genre = button.dataset.genreValue;
      state.catalogPage = 1;
      els.genreFilter.value = state.genre;
      renderGenreRail();
      renderCatalog();
      navigate("/#update");
    });
  });
}

function renderCatalog() {
  const comics = getFilteredComics();
  const pageSize = catalogPageSize();
  const totalPages = Math.max(1, Math.ceil(comics.length / pageSize));
  state.catalogPage = Math.min(Math.max(1, state.catalogPage), totalPages);
  const pageComics = comics.slice((state.catalogPage - 1) * pageSize, state.catalogPage * pageSize);
  els.resultCount.textContent = comics.length;
  els.comicGrid.classList.toggle("compact", state.view === "compact");

  if (comics.length === 0) {
    els.comicGrid.innerHTML = `<div class="empty-state wide"><strong>Tidak ada judul ditemukan.</strong><span>Coba ubah filter cepat, genre, status, atau tipe.</span></div>`;
    renderHomePagination(0);
    return;
  }

  els.comicGrid.innerHTML = pageComics.map((comic, index) => renderComicCard(comic, index)).join("");
  renderHomePagination(totalPages);
}

function renderSearchPage(rawQuery) {
  const query = String(rawQuery || "").trim();
  showView("home");
  state.query = query.toLowerCase();
  state.catalogPage = 1;
  state.quickFilter = "all";
  if (els.searchInput) els.searchInput.value = query;
  els.quickFilterTabs?.querySelectorAll("[data-quick-filter]").forEach(item => {
    item.classList.toggle("active", item.dataset.quickFilter === "all");
  });
  updateCatalogHeading(query ? `Hasil pencarian: ${query}` : "Hasil Pencarian");
  renderCatalog();
  updateNav();
  updatePageSeo({
    title: query ? `Hasil pencarian ${query} - ${siteName()}` : `Hasil Pencarian - ${siteName()}`,
    description: query ? `Daftar komik yang cocok dengan pencarian ${query}.` : `Cari judul manhwa, manga, dan manhua di ${siteName()}.`,
    image: state.settings.ogImageUrl || state.settings.logoUrl || defaultBrandAssets.logo
  });
  requestAnimationFrame(() => document.querySelector("#update")?.scrollIntoView({ behavior: "smooth", block: "start" }));
}

function updateCatalogHeading(text = "Update") {
  const heading = document.querySelector("#update .section-head h2");
  if (heading) heading.textContent = text;
}

function renderSearchSuggestions(rawQuery) {
  if (!els.searchSuggestions) return;
  const query = String(rawQuery || "").trim().toLowerCase();
  if (query.length < 2) {
    hideSearchSuggestions();
    return;
  }
  const matches = state.comics
    .filter(comic => searchHaystack(comic).includes(query))
    .sort((a, b) => {
      const aTitle = a.title.toLowerCase().startsWith(query) ? 0 : 1;
      const bTitle = b.title.toLowerCase().startsWith(query) ? 0 : 1;
      return aTitle - bTitle || new Date(b.updatedAt) - new Date(a.updatedAt);
    })
    .slice(0, 7);

  els.searchSuggestions.hidden = false;
  els.searchSuggestions.innerHTML = matches.length ? `
    ${matches.map(comic => {
      const chapter = comic.chapters?.[0];
      return `
        <a class="search-suggestion-item" href="${mangaPath(comic)}" data-route>
          <span class="search-suggestion-cover">${coverMarkup(comic)}</span>
          <span>
            <strong>${escapeHtml(comic.title)}</strong>
            <small>${escapeHtml(comic.type || "Manhwa")} · ${chapter ? escapeHtml(cleanChapterTitle(chapter.title)) : `${comic.chapters?.length || 0} chapter`}</small>
          </span>
        </a>
      `;
    }).join("")}
    <button class="search-suggestion-submit" type="submit">Lihat semua hasil untuk "${escapeHtml(rawQuery.trim())}"</button>
  ` : `<div class="search-suggestion-empty">Tidak ada judul yang cocok.</div>`;
}

function hideSearchSuggestions() {
  if (!els.searchSuggestions) return;
  els.searchSuggestions.hidden = true;
  els.searchSuggestions.innerHTML = "";
}

function setMobileSearchOpen(isOpen) {
  document.body.classList.toggle("mobile-search-open", isOpen);
  els.mobileSearchToggle?.setAttribute("aria-expanded", isOpen ? "true" : "false");
}

function catalogPageSize() {
  return state.view === "compact" ? 12 : 18;
}

function renderHomePagination(totalPages) {
  if (!els.homePagination) return;
  if (totalPages <= 1) {
    els.homePagination.innerHTML = "";
    return;
  }

  const pages = Array.from({ length: totalPages }, (_, index) => index + 1)
    .filter(page => page === 1 || page === totalPages || Math.abs(page - state.catalogPage) <= 1);
  const buttons = [];
  let previous = 0;
  pages.forEach(page => {
    if (previous && page - previous > 1) buttons.push(`<span>...</span>`);
    buttons.push(`<button class="${page === state.catalogPage ? "active" : ""}" type="button" data-page-number="${page}">${page}</button>`);
    previous = page;
  });

  els.homePagination.innerHTML = `
    <button type="button" data-page-action="prev" ${state.catalogPage <= 1 ? "disabled" : ""}>‹</button>
    ${buttons.join("")}
    <button type="button" data-page-action="next" ${state.catalogPage >= totalPages ? "disabled" : ""}>›</button>
  `;
}

function getFilteredComics() {
  return [...state.comics]
    .filter(comic => {
      const haystack = searchHaystack(comic);
      return (
        (!state.query || haystack.includes(state.query)) &&
        (state.genre === "all" || comic.genres.includes(state.genre)) &&
        (state.status === "all" || comic.status === state.status) &&
        (state.type === "all" || comic.type === state.type) &&
        (
          state.quickFilter === "all" ||
          comic.type === state.quickFilter ||
          comic.status === state.quickFilter
        )
      );
    })
    .sort((a, b) => {
      if (state.sort === "rating") return b.rating - a.rating;
      if (state.sort === "title") return a.title.localeCompare(b.title);
      if (state.sort === "chapters") return b.chapters.length - a.chapters.length;
      return new Date(b.updatedAt) - new Date(a.updatedAt);
    });
}

function searchHaystack(comic) {
  return [comic.title, comic.author, comic.type, comic.status, (comic.genres || []).join(" ")].join(" ").toLowerCase();
}

function renderComicCard(comic, index) {
  const [coverA, coverB] = colors[index % colors.length];
  const bookmarked = state.bookmarks.has(comic.slug);
  const coverImage = comic.cover || comic.image || "";

  return `
    <article class="comic-card update-card" style="--cover-a: ${coverA}; --cover-b: ${coverB}">
      <a class="cover ${coverImage ? "has-image" : ""}" href="${mangaPath(comic)}" data-route aria-label="Buka ${escapeHtml(comic.title)}">
        ${coverMarkup(comic)}
        <span class="country-dot">${countryFor(comic.type)}</span>
        <span class="cover-title">${escapeHtml(comic.title)}</span>
      </a>
      <div class="comic-info">
        <div>
          <h3><span class="title-badge">UP</span><a href="${mangaPath(comic)}" data-route>${escapeHtml(comic.title)}</a></h3>
        </div>
        <div class="chapter-pair">
          ${comic.chapters.slice(0, state.view === "compact" ? 3 : 2).map(chapter => `
            <a href="${readerPath(comic, comic.chapters.indexOf(chapter))}" data-route>
              <span>${escapeHtml(cleanChapterTitle(chapter.title))}</span>
              <small>${escapeHtml(shortChapterDate(chapter.date || chapter.title))}</small>
            </a>
          `).join("")}
        </div>
        <div class="card-actions">
          <a class="details-button" href="${mangaPath(comic)}" data-route>Lihat chapter</a>
          <button class="bookmark-button" data-bookmark="${escapeHtml(comic.slug)}" type="button">${bookmarked ? "Tersimpan" : "Bookmark"}</button>
        </div>
      </div>
    </article>
  `;
}

function renderPosterOnlyItem(comic, index) {
  const [coverA, coverB] = colors[index % colors.length];
  const coverImage = comic.cover || comic.image || "";
  return `
    <a class="poster-only-item" href="${mangaPath(comic)}" data-route style="--cover-a: ${coverA}; --cover-b: ${coverB}" aria-label="Buka ${escapeHtml(comic.title)}">
      <span class="cover ${coverImage ? "has-image" : ""}" aria-hidden="true">
        ${coverMarkup(comic)}
        <span class="rank-badge mini">${index + 1}</span>
        <span class="country-dot">${countryFor(comic.type)}</span>
        <span class="cover-title">${escapeHtml(comic.title)}</span>
      </span>
    </a>
  `;
}

function renderRecommendItem(comic, index) {
  const [coverA, coverB] = colors[index % colors.length];
  const coverImage = comic.cover || comic.image || "";
  return `
    <a class="recommend-card" href="${mangaPath(comic)}" data-route style="--cover-a: ${coverA}; --cover-b: ${coverB}" aria-label="Buka ${escapeHtml(comic.title)}">
      <span class="cover ${coverImage ? "has-image" : ""}" aria-hidden="true">
        ${coverMarkup(comic)}
        <span class="title-badge hot-badge">HOT</span>
        <span class="country-dot">${countryFor(comic.type)}</span>
        <span class="cover-title">${escapeHtml(comic.title)}</span>
      </span>
    </a>
  `;
}

function qualityBadges(comic) {
  const badges = [];
  const updatedRecently = recentComic(comic, 14);
  if (/completed/i.test(comic.status || "")) badges.push(`<span class="title-badge completed-badge">Completed</span>`);
  if (updatedRecently) badges.push(`<span class="title-badge new-badge">New Chapter</span>`);
  if (Number(comic.views || 0) > 0 || Number(comic.rating || 0) >= 8) badges.push(`<span class="title-badge hot-badge">Hot</span>`);
  badges.push(`<span class="title-badge updated-badge">Updated</span>`);
  return badges.slice(0, 3);
}

function recentComic(comic, days = 14) {
  const dates = [
    comic.updatedAt ? new Date(comic.updatedAt) : null,
    ...(comic.chapters || []).slice(0, 3).map(chapter => parseChapterDate(chapter.date || chapter.title))
  ].filter(date => date && !Number.isNaN(date.getTime()));
  if (!dates.length) return false;
  const latest = Math.max(...dates.map(date => date.getTime()));
  return Date.now() - latest <= days * 864e5;
}

function renderShelfItem(comic, index) {
  const [coverA, coverB] = colors[index % colors.length];
  const coverImage = comic.cover || comic.image || "";
  return `
    <a class="shelf-item" href="${mangaPath(comic)}" data-route style="--cover-a: ${coverA}; --cover-b: ${coverB}">
      <span class="cover ${coverImage ? "has-image" : ""}" aria-hidden="true">
        ${coverMarkup(comic)}
        <span class="rank-badge mini">${index + 1}</span>
        <span class="country-dot">${countryFor(comic.type)}</span>
        <span class="cover-title">${escapeHtml(comic.title)}</span>
      </span>
      <strong>${escapeHtml(comic.title)}</strong>
      <small>${escapeHtml(cleanChapterTitle(comic.chapter || comic.chapters[0]?.title || `${comic.chapters.length} chapter`))}</small>
    </a>
  `;
}

function renderDetailPage(slug) {
  const comic = findComic(slug);
  if (!comic) {
    renderMissingPage("Manga tidak ditemukan.", "Kembali ke katalog");
    return;
  }

  if (state.detailSlug !== comic.slug) {
    state.detailSlug = comic.slug;
    state.detailChapterQuery = "";
    state.detailChapterPage = 1;
    state.detailChapterSort = "desc";
    state.detailChapterRange = "all";
  }

  const firstReadable = comic.chapters.findIndex(chapter => chapter.images?.length);
  const oldestReadable = [...comic.chapters].map((chapter, index) => ({ chapter, index })).reverse().find(({ chapter }) => chapter.images?.length)?.index ?? firstReadable;
  const coverImage = comic.cover || comic.image || "";
  const siteNameValue = siteName();
  const chapters = filteredDetailChapters(comic);
  const totalPages = Math.max(1, Math.ceil(chapters.length / detailChapterPageSize()));
  state.detailChapterPage = Math.min(Math.max(1, state.detailChapterPage), totalPages);
  const pageChapters = chapters.slice((state.detailChapterPage - 1) * detailChapterPageSize(), state.detailChapterPage * detailChapterPageSize());

  showView("detail");
  updateNav();
  updatePageSeo({
    title: `${comic.title} - ${siteNameValue}`,
    description: detailSeoDescription(comic),
    image: assetSrc(coverImage)
  });
  renderSchema({
    "@context": "https://schema.org",
    "@type": "Book",
    "name": comic.title,
    "genre": comic.genres,
    "image": assetSrc(coverImage),
    "url": window.location.href
  });
  els.detailPage.innerHTML = `
    <section class="series-hero" style="--hero-image: url('${escapeHtml(assetSrc(coverImage))}')">
      <a class="back-dot" href="/" data-route aria-label="Kembali"></a>
      <div class="series-cover">${coverMarkup(comic)}</div>
      <div class="series-copy">
        <h1>${escapeHtml(comic.title)}</h1>
        <div class="series-actions">
          ${oldestReadable >= 0 ? `<a class="primary" href="${readerPath(comic, oldestReadable)}" data-route>Mulai Baca</a>` : ""}
          ${firstReadable >= 0 ? `<a class="secondary" href="${readerPath(comic, firstReadable)}" data-route>Baca Chapter Terbaru</a>` : ""}
        </div>
      </div>
    </section>

    <section class="series-body">
      <p class="series-synopsis">${escapeHtml(comic.synopsis || "Belum ada sinopsis.")}</p>
      <div class="series-meta-grid">
        <div><strong>Genre</strong><span>${comic.genres.slice(0, 4).map(genre => `<em>${escapeHtml(genre)}</em>`).join("")}</span></div>
        <div><strong>Type</strong><span><em>${escapeHtml(comic.type)}</em></span></div>
      </div>
    </section>

    <section class="chapter-panel">
      <div class="chapter-panel-head">
        <h2>Chapter ${escapeHtml(comic.title)}</h2>
      </div>
      <div class="chapter-toolbar">
        <label>
          <span aria-hidden="true">⌕</span>
          <input data-chapter-search type="search" value="${escapeHtml(state.detailChapterQuery)}" placeholder="Cari Chapter, Contoh: 69 atau 76" autocomplete="off">
        </label>
        <select data-chapter-sort aria-label="Urutan chapter">
          <option value="desc" ${state.detailChapterSort === "desc" ? "selected" : ""}>Terbaru ke lama</option>
          <option value="asc" ${state.detailChapterSort === "asc" ? "selected" : ""}>Lama ke terbaru</option>
        </select>
      </div>
      ${renderChapterRanges(comic)}
      <div class="chapter-grid">
        ${pageChapters.length ? pageChapters.map(({ chapter, index }) => renderChapterRow(comic, chapter, index)).join("") : `<div class="chapter-empty">Chapter tidak ditemukan.</div>`}
      </div>
      ${renderDetailPagination(totalPages)}
    </section>
  `;
}

function renderChapterRow(comic, chapter, index) {
  const readable = chapter.images?.length;
  const meta = releaseDateLabel(chapter.date || chapter.title) || `${readable || 0} halaman`;
  const read = isChapterRead(comic, chapter);
  const endBadge = isEndingChapter(comic, chapter) ? `<em class="chapter-end-badge">END</em>` : "";
  const content = `
    <span class="chapter-copy">
      <strong>${escapeHtml(cleanChapterTitle(chapter.title))}${endBadge ? ` ${endBadge}` : ""}</strong>
      <small>${escapeHtml(meta || `${readable || 0} halaman`)}</small>
    </span>
  `;

  return readable
    ? `<a class="chapter-tile ${read ? "is-read" : ""}" href="${readerPath(comic, index)}" data-route>${content}</a>`
    : `<div class="chapter-tile disabled">${content}</div>`;
}

function isEndingChapter(comic, chapter) {
  if (/\bend\b/i.test(chapter.title || "")) return true;
  if (!/completed/i.test(comic.status || "")) return false;
  const current = chapterNumber(chapter.title);
  const highest = Math.max(...comic.chapters.map(item => chapterNumber(item.title)).filter(Boolean));
  return Boolean(current && highest && current === highest);
}

function filteredDetailChapters(comic) {
  if (!comic) return [];
  const query = state.detailChapterQuery.toLowerCase();
  return comic.chapters
    .map((chapter, index) => ({ chapter, index }))
    .filter(({ chapter }) => {
      if (!query) return true;
      const haystack = [
        chapter.title,
        cleanChapterTitle(chapter.title),
        chapterSubtitle(comic, chapter),
        chapter.date
      ].join(" ").toLowerCase();
      return haystack.includes(query);
    })
    .filter(({ chapter }) => {
      if (state.detailChapterRange === "all") return true;
      const [min, max] = state.detailChapterRange.split("-").map(Number);
      const number = chapterNumber(chapter.title);
      return number >= min && number <= max;
    })
    .sort((a, b) => {
      const numberA = chapterNumber(a.chapter.title);
      const numberB = chapterNumber(b.chapter.title);
      return state.detailChapterSort === "asc" ? numberA - numberB : numberB - numberA;
    });
}

function detailChapterPageSize() {
  return 24;
}

function renderChapterRanges(comic) {
  const numbers = comic.chapters.map(chapter => chapterNumber(chapter.title)).filter(Boolean);
  if (numbers.length < 50) return "";
  const min = Math.floor(Math.min(...numbers) / 50) * 50 + 1;
  const max = Math.ceil(Math.max(...numbers) / 50) * 50;
  const ranges = [`<button class="${state.detailChapterRange === "all" ? "active" : ""}" type="button" data-chapter-range="all">Semua</button>`];
  for (let start = min; start <= max; start += 50) {
    const end = start + 49;
    const value = `${start}-${end}`;
    ranges.push(`<button class="${state.detailChapterRange === value ? "active" : ""}" type="button" data-chapter-range="${value}">${start}-${end}</button>`);
  }
  return `<div class="chapter-range-tabs" aria-label="Range chapter">${ranges.join("")}</div>`;
}

function renderDetailPagination(totalPages) {
  if (totalPages <= 1) return "";
  const pages = Array.from({ length: totalPages }, (_, index) => index + 1)
    .filter(page => page === 1 || page === totalPages || Math.abs(page - state.detailChapterPage) <= 1);
  const buttons = [];
  let previous = 0;
  pages.forEach(page => {
    if (previous && page - previous > 1) buttons.push(`<span>...</span>`);
    buttons.push(`<button class="${page === state.detailChapterPage ? "active" : ""}" type="button" data-detail-page-number="${page}">${page}</button>`);
    previous = page;
  });

  return `
    <nav class="chapter-pagination" aria-label="Pagination chapter">
      <button type="button" data-detail-page-action="prev" ${state.detailChapterPage <= 1 ? "disabled" : ""}>‹</button>
      ${buttons.join("")}
      <button type="button" data-detail-page-action="next" ${state.detailChapterPage >= totalPages ? "disabled" : ""}>›</button>
    </nav>
  `;
}

function renderReaderPage(slug, chapterSlug) {
  const comic = findComic(slug);
  const chapterIndex = findChapterIndex(comic, chapterSlug);
  const chapter = comic?.chapters?.[chapterIndex];

  if (!comic || !chapter) {
    renderMissingPage("Chapter tidak ditemukan.", "Kembali ke katalog");
    return;
  }

  showView("reader");
  updateNav();
  state.readerPageIndex = 0;
  state.readerAutoNavigated = false;
  markChapterRead(comic, chapter);
  els.readerPage.classList.add("reader-controls-visible");
  updatePageSeo({
    title: formatReaderTitle(comic, chapter),
    description: readerSeoDescription(comic, chapter),
    image: assetSrc(comic.cover || comic.image || chapter.images?.[0] || "")
  });
  renderSchema({
    "@context": "https://schema.org",
    "@type": "Chapter",
    "name": formatReaderTitle(comic, chapter),
    "isPartOf": { "@type": "Book", "name": comic.title },
    "url": window.location.href
  });
  const previousChapter = chapterIndex < comic.chapters.length - 1 ? comic.chapters[chapterIndex + 1] : null;
  const nextChapter = chapterIndex > 0 ? comic.chapters[chapterIndex - 1] : null;
  const title = formatReaderTitle(comic, chapter);
  els.readerPage.innerHTML = `
    <nav class="reader-floating-nav" aria-label="Navigasi reader">
      ${previousChapter ? `<a class="reader-nav-icon" href="${readerPath(comic, chapterIndex + 1)}" data-route aria-label="Chapter sebelumnya">‹</a>` : `<span class="reader-nav-icon disabled">‹</span>`}
      <a class="reader-nav-title" href="${mangaPath(comic)}" data-route aria-label="List chapter">${escapeHtml(comic.title)}</a>
      ${nextChapter ? `<a class="reader-nav-icon" href="${readerPath(comic, chapterIndex - 1)}" data-route aria-label="Chapter selanjutnya">›</a>` : `<span class="reader-nav-icon disabled">›</span>`}
      <span class="reader-nav-chapter">${escapeHtml(cleanChapterTitle(chapter.title).replace(/^Chapter\s*/i, "Ch "))}</span>
      <a class="reader-nav-icon home" href="/" data-route aria-label="Home">⌂</a>
    </nav>
    <div class="reader-images">
      ${chapter.images?.length ? chapter.images.map((image, index) => `
        <figure class="reader-image-wrap" data-reader-page="${index}">
          <span class="image-loader">Memuat halaman ${index + 1}...</span>
          <img src="${escapeHtml(assetSrc(image))}" alt="${escapeHtml(title)} halaman ${index + 1}" loading="${index < 2 ? "eager" : "lazy"}">
          <button class="image-retry" type="button" data-retry-image hidden>Ulangi gambar</button>
        </figure>
      `).join("") : `<div class="reader-empty">Chapter ini belum punya image lokal.</div>`}
    </div>
  `;
  setupReaderRuntime(comic, chapterIndex);
}

function setupReaderRuntime(comic, chapterIndex) {
  wireReaderImages();
  preloadChapter(comic, chapterIndex + 1);
  showReaderControls();
  window.removeEventListener("scroll", handleReaderScroll);
  window.addEventListener("scroll", handleReaderScroll, { passive: true });
}

function wireReaderImages() {
  document.querySelectorAll(".reader-image-wrap").forEach(wrap => {
    wrap.addEventListener("click", showReaderControls);
  });
  document.querySelectorAll(".reader-image-wrap img").forEach(image => {
    const wrap = image.closest(".reader-image-wrap");
    const retry = wrap?.querySelector("[data-retry-image]");
    if (image.complete && image.naturalWidth) wrap?.classList.add("loaded");
    image.addEventListener("load", () => {
      wrap?.classList.add("loaded");
      wrap?.classList.remove("failed");
      if (retry) retry.hidden = true;
    });
    image.addEventListener("error", () => {
      wrap?.classList.add("failed");
      if (retry) retry.hidden = false;
    });
  });
}

let readerControlsTimer = 0;
function showReaderControls() {
  els.readerPage.classList.add("reader-controls-visible");
  clearTimeout(readerControlsTimer);
  readerControlsTimer = window.setTimeout(() => {
    els.readerPage.classList.remove("reader-controls-visible");
  }, 3200);
}

function handleReaderScroll() {
  if (!document.body.classList.contains("is-reader")) return;
  if (state.readerAutoNavigated) return;
  const next = document.querySelector(".reader-floating-nav a[aria-label='Chapter sebelumnya']");
  if (!next) return;
  const nearBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 80;
  if (!nearBottom) return;
  state.readerAutoNavigated = true;
  window.setTimeout(() => navigate(new URL(next.href).pathname), 650);
}

function preloadChapter(comic, chapterIndex) {
  const chapter = comic.chapters?.[chapterIndex];
  if (!chapter?.images?.length) return;
  chapter.images.slice(0, 3).forEach(src => {
    const link = document.createElement("link");
    link.rel = "preload";
    link.as = "image";
    link.href = assetSrc(src);
    document.head.appendChild(link);
  });
}

function chapterThumb(comic, chapter) {
  return chapterThumbCandidates(comic, chapter)[0] || "";
}

function chapterThumbCandidates(comic, chapter) {
  if (chapter.thumbnail) {
    const rest = chapter.images?.length ? chapter.images : [comic.cover || comic.image || ""];
    return [chapter.thumbnail, ...rest].filter(Boolean);
  }
  if (!chapter.images?.length) return [comic.cover || comic.image || ""].filter(Boolean);
  const lastIndex = chapter.images.length - 1;
  const candidates = [
    Math.floor(lastIndex * .32),
    Math.floor(lastIndex * .44),
    Math.floor(lastIndex * .56),
    Math.floor(lastIndex * .68),
    Math.floor(lastIndex * .78),
    12,
    8,
    6,
    4,
    2
  ];
  return [...new Set(candidates
    .map(index => chapter.images[Math.min(Math.max(index, 0), lastIndex)])
    .filter(Boolean))]
    .slice(0, 8);
}

function renderMissingPage(message, action) {
  showView("detail");
  updateNav();
  document.title = `Tidak ditemukan - ${state.settings.headerLogoText || "ManhwaLanded"}`;
  els.detailPage.innerHTML = `
    <section class="empty-page">
      <p class="eyebrow">404</p>
      <h1>${escapeHtml(message)}</h1>
      <a class="primary" href="/" data-route>${escapeHtml(action)}</a>
    </section>
  `;
}

async function enhanceChapterThumbs() {
  const images = [...document.querySelectorAll(".chapter-thumb img[data-thumb-candidates]")];
  await Promise.all(images.map(async image => {
    let candidates = [];
    try {
      candidates = JSON.parse(image.dataset.thumbCandidates || "[]");
    } catch {
      candidates = [image.src];
    }
    const best = await pickBestThumbnail(candidates, 112 / 68);
    if (!best) return;
    image.src = best.src;
    image.style.objectPosition = `50% ${best.position}%`;
  }));
}

async function pickBestThumbnail(candidates, targetRatio) {
  const unique = [...new Set(candidates)].filter(Boolean).slice(0, 8);
  const scored = await Promise.all(unique.map(async src => {
    const image = await loadProbeImage(src).catch(() => null);
    if (!image?.naturalWidth || !image?.naturalHeight) return null;
    return scoreThumbnailImage(image, src, targetRatio);
  }));
  return scored.filter(Boolean).sort((a, b) => b.score - a.score)[0] || null;
}

function loadProbeImage(src) {
  return new Promise((resolve, reject) => {
    const image = document.createElement("img");
    image.decoding = "async";
    image.onload = () => resolve(image);
    image.onerror = reject;
    image.src = src;
  });
}

function scoreThumbnailImage(image, src, targetRatio) {
  const width = image.naturalWidth;
  const height = image.naturalHeight;
  const cropPositions = [.18, .28, .38, .5, .62, .74, .86];
  const canvas = document.createElement("canvas");
  const sampleWidth = 56;
  const sampleHeight = Math.max(1, Math.round(sampleWidth / targetRatio));
  canvas.width = sampleWidth;
  canvas.height = sampleHeight;
  const context = canvas.getContext("2d", { willReadFrequently: true });
  if (!context) return null;

  let best = null;
  cropPositions.forEach(position => {
    const crop = cropBox(width, height, targetRatio, position);
    context.clearRect(0, 0, sampleWidth, sampleHeight);
    context.drawImage(image, crop.x, crop.y, crop.width, crop.height, 0, 0, sampleWidth, sampleHeight);
    const pixels = context.getImageData(0, 0, sampleWidth, sampleHeight).data;
    const stats = imageStats(pixels);
    const blankPenalty = stats.darkRatio * 95 + stats.whiteRatio * 48;
    const brightnessPenalty = Math.abs(stats.brightness - 128) * .2;
    const score = (stats.saturation * 1.4) + (stats.contrast * 1.15) - blankPenalty - brightnessPenalty;
    if (!best || score > best.score) {
      best = { src, score, position: Math.round(position * 100) };
    }
  });

  return best;
}

function cropBox(width, height, targetRatio, position) {
  let cropWidth = width;
  let cropHeight = cropWidth / targetRatio;
  if (cropHeight > height) {
    cropHeight = height;
    cropWidth = cropHeight * targetRatio;
  }
  return {
    x: Math.max(0, (width - cropWidth) / 2),
    y: Math.max(0, (height - cropHeight) * position),
    width: cropWidth,
    height: cropHeight
  };
}

function imageStats(pixels) {
  let brightness = 0;
  let saturation = 0;
  let dark = 0;
  let white = 0;
  const lumas = [];

  for (let index = 0; index < pixels.length; index += 4) {
    const red = pixels[index];
    const green = pixels[index + 1];
    const blue = pixels[index + 2];
    const max = Math.max(red, green, blue);
    const min = Math.min(red, green, blue);
    const luma = red * .2126 + green * .7152 + blue * .0722;
    brightness += luma;
    saturation += max ? ((max - min) / max) * 100 : 0;
    if (luma < 28) dark += 1;
    if (luma > 238) white += 1;
    lumas.push(luma);
  }

  const count = Math.max(1, lumas.length);
  brightness /= count;
  saturation /= count;
  const variance = lumas.reduce((sum, luma) => sum + ((luma - brightness) ** 2), 0) / count;
  return {
    brightness,
    saturation,
    contrast: Math.sqrt(variance),
    darkRatio: dark / count,
    whiteRatio: white / count
  };
}

function toggleBookmark(slug) {
  if (state.bookmarks.has(slug)) {
    state.bookmarks.delete(slug);
  } else {
    state.bookmarks.add(slug);
  }
  localStorage.setItem(storageKeys.bookmarks, JSON.stringify([...state.bookmarks]));
  renderCatalog();
  renderBookmarks();
  const current = window.location.pathname.split("/").filter(Boolean);
  if (current[0] === "manga" && current[1] === slug) renderDetailPage(slug);
}

function chapterReadKey(comic, chapter) {
  return `${comic.slug}:${chapterSlugFor(comic, chapter)}`;
}

function markChapterRead(comic, chapter) {
  state.readChapters.add(chapterReadKey(comic, chapter));
  localStorage.setItem(storageKeys.readChapters, JSON.stringify([...state.readChapters]));
}

function isChapterRead(comic, chapter) {
  return state.readChapters.has(chapterReadKey(comic, chapter));
}

function renderBookmarks() {
  const bookmarked = state.comics.filter(comic => state.bookmarks.has(comic.slug));
  if (bookmarked.length === 0) {
    els.bookmarkList.innerHTML = `<div class="bookmark-empty">Belum ada bookmark.</div>`;
    return;
  }
  els.bookmarkList.innerHTML = bookmarked.map((comic, index) => renderComicCard(comic, index)).join("");
}

function findComic(slug) {
  return state.comics.find(item => item.slug === slug);
}

function comicsBySlugs(slugs = []) {
  const bySlug = new Map(state.comics.map(comic => [comic.slug, comic]));
  return slugs.map(slug => bySlug.get(slug)).filter(Boolean);
}

function findChapterIndex(comic, chapterSlug) {
  if (!comic) return -1;
  const index = comic.chapters.findIndex(chapter => chapterSlugFor(comic, chapter) === chapterSlug || slugify(chapter.title) === chapterSlug);
  return index >= 0 ? index : 0;
}

function findComicByChapterSlug(chapterSlug) {
  for (const comic of state.comics) {
    const index = comic.chapters.findIndex(chapter => chapterSlugFor(comic, chapter) === chapterSlug || slugify(chapter.title) === chapterSlug);
    if (index >= 0) return { comic, chapterIndex: index, chapterSlug };
  }
  return null;
}

function mangaPath(comic) {
  return `/manga/${encodeURIComponent(comic.slug)}/`;
}

function rankByRange(comics, range) {
  const recencyWeight = {
    today: 900,
    weekly: 520,
    monthly: 260,
    all: 0
  }[range] ?? 900;
  const chapterWeight = range === "all" ? 38 : 8;

  return [...comics].sort((a, b) => {
    return rankingScore(b, recencyWeight, chapterWeight) - rankingScore(a, recencyWeight, chapterWeight);
  });
}

function rankingScore(comic, recencyWeight, chapterWeight) {
  const ageDays = Math.max(0, (Date.now() - latestComicTime(comic)) / 864e5);
  const recency = recencyWeight ? recencyWeight / (ageDays + 1) : 0;
  return recency + Number(comic.views || 0) + (comic.chapters?.length || 0) * chapterWeight + Number(comic.rating || 0) * 12;
}

function latestComicTime(comic) {
  const dates = [
    comic.updatedAt ? new Date(comic.updatedAt).getTime() : 0,
    ...(comic.chapters || []).slice(0, 5).map(chapter => parseChapterDate(chapter.date || chapter.title)?.getTime() || 0)
  ];
  return Math.max(...dates, 0) || 0;
}

function shortChapterDate(value) {
  const raw = String(value || "").trim();
  if (!raw || /^baru$/i.test(raw)) return "baru";

  const parsed = parseChapterDate(raw);
  if (!parsed) return raw.replace(/\s+\d{4}$/i, "").slice(0, 12);

  const now = new Date();
  const diffMs = Math.max(0, now - parsed);
  const diffHours = Math.floor(diffMs / 36e5);
  if (diffHours < 1) return "baru";
  if (diffHours < 24) return `${diffHours} jam`;
  const diffDays = Math.max(1, Math.floor(diffHours / 24));
  if (diffDays < 30) return `${diffDays} hari`;
  const diffMonths = Math.floor(diffDays / 30);
  if (diffMonths < 12) return `${diffMonths} bln`;
  return `${Math.floor(diffMonths / 12)} thn`;
}

function releaseDateLabel(value) {
  const parsed = parseChapterDate(value);
  if (!parsed) return "";
  const months = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
  return `${months[parsed.getMonth()]} ${parsed.getDate()}, ${parsed.getFullYear()}`;
}

function cleanChapterTitle(value) {
  const text = String(value || "").replace(/^Latest:\s*/i, "").trim();
  const chapterMatch = text.match(/\b(Chapter\s+\d+(?:\.\d+)?)/i);
  if (chapterMatch) return chapterMatch[1];

  return text
    .replace(/\s+end\b/i, "")
    .replace(/\s+(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Mei|Jun(?:e|i)?|Jul(?:y|i)?|Aug(?:ust)?|Agu(?:stus)?|Sep(?:tember)?|Oct(?:ober)?|Okt(?:ober)?|Nov(?:ember)?|Dec(?:ember)?|Des(?:ember)?)\s+\d{1,2},?\s+\d{4}$/i, "")
    .replace(/\s+\d{1,2}\s+(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Mei|Jun(?:e|i)?|Jul(?:y|i)?|Aug(?:ust)?|Agu(?:stus)?|Sep(?:tember)?|Oct(?:ober)?|Okt(?:ober)?|Nov(?:ember)?|Dec(?:ember)?|Des(?:ember)?)\s+\d{4}$/i, "")
    .trim();
}

function chapterNumber(value) {
  const match = String(value || "").match(/chapter\s+(\d+(?:\.\d+)?)/i);
  return match ? Number(match[1]) : 0;
}

function chapterSubtitle(comic, chapter) {
  const text = String(chapter.title || "")
    .replace(/^Latest:\s*/i, "")
    .replace(/\s+end\b/i, "")
    .replace(new RegExp(`^${escapeRegExp(comic.title)}\\s*[-:]?\\s*`, "i"), "")
    .replace(/\s+(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Mei|Jun(?:e|i)?|Jul(?:y|i)?|Aug(?:ust)?|Agu(?:stus)?|Sep(?:tember)?|Oct(?:ober)?|Okt(?:ober)?|Nov(?:ember)?|Dec(?:ember)?|Des(?:ember)?)\s+\d{1,2},?\s+\d{4}$/i, "")
    .replace(/\s+\d{1,2}\s+(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Mei|Jun(?:e|i)?|Jul(?:y|i)?|Aug(?:ust)?|Agu(?:stus)?|Sep(?:tember)?|Oct(?:ober)?|Okt(?:ober)?|Nov(?:ember)?|Dec(?:ember)?|Des(?:ember)?)\s+\d{4}$/i, "")
    .trim();
  const subtitle = text.replace(/^Chapter\s+\d+(?:\.\d+)?\s*[-:]?\s*/i, "").trim();
  return /^chapter\b/i.test(subtitle) ? "" : subtitle;
}

function formatChapterDisplayTitle(comic, chapter) {
  const cleanTitle = cleanChapterTitle(chapter.title);
  if (/^chapter\b/i.test(cleanTitle)) return `${comic.title} ${cleanTitle}`;
  if (cleanTitle.toLowerCase().startsWith(comic.title.toLowerCase())) return cleanTitle;
  return `${comic.title} ${cleanTitle}`;
}

function formatReaderTitle(comic, chapter) {
  return formatChapterDisplayTitle(comic, chapter).replace(/^Latest:\s*/i, "");
}

function parseChapterDate(value) {
  const normalized = String(value || "").replaceAll(",", " ").replace(/\s+/g, " ").trim();
  const monthMap = {
    jan: 0, januari: 0, january: 0,
    feb: 1, februari: 1, february: 1,
    mar: 2, maret: 2, march: 2,
    apr: 3, april: 3,
    mei: 4, may: 4,
    jun: 5, juni: 5, june: 5,
    jul: 6, juli: 6, july: 6,
    agu: 7, agustus: 7, aug: 7, august: 7,
    sep: 8, september: 8,
    okt: 9, oktober: 9, oct: 9, october: 9,
    nov: 10, november: 10,
    des: 11, desember: 11, dec: 11, december: 11
  };

  const monthNames = Object.keys(monthMap).join("|");
  const monthFirstMatch = normalized.match(new RegExp(`\\b(${monthNames})\\s+(\\d{1,2})\\s+(\\d{4})\\b`, "i"));
  if (monthFirstMatch) {
    return new Date(Number(monthFirstMatch[3]), monthMap[monthFirstMatch[1].toLowerCase()], Number(monthFirstMatch[2]));
  }

  const dayFirstMatch = normalized.match(new RegExp(`\\b(\\d{1,2})\\s+(${monthNames})\\s+(\\d{4})\\b`, "i"));
  if (dayFirstMatch) {
    return new Date(Number(dayFirstMatch[3]), monthMap[dayFirstMatch[2].toLowerCase()], Number(dayFirstMatch[1]));
  }

  const parts = normalized.split(" ");
  if (parts.length >= 3) {
    const monthFirst = monthMap[parts[0]?.toLowerCase()];
    if (monthFirst !== undefined) {
      const day = Number(parts[1]);
      const year = Number(parts[2]);
      if (day && year) return new Date(year, monthFirst, day);
    }

    const dayFirst = Number(parts[0]);
    const month = monthMap[parts[1]?.toLowerCase()];
    const year = Number(parts[2]);
    if (dayFirst && month !== undefined && year) return new Date(year, month, dayFirst);
  }

  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function countryFor(type) {
  if (/manga/i.test(type || "")) return "🇯🇵";
  if (/manhua/i.test(type || "")) return "🇨🇳";
  return "🇰🇷";
}

function readerPath(comic, chapterIndex) {
  const chapter = comic.chapters[chapterIndex];
  return `/${encodeURIComponent(chapterSlugFor(comic, chapter))}/`;
}

function chapterSlugFor(comic, chapter) {
  const sourceSlug = sourceChapterSlug(chapter.url);
  if (sourceSlug) return sourceSlug;
  return `${comic.slug}-${slugify(cleanChapterTitle(chapter.title))}`;
}

function sourceChapterSlug(value) {
  if (!value) return "";
  try {
    return sanitizeChapterSlug(new URL(value).pathname.split("/").filter(Boolean).pop() || "");
  } catch {
    return sanitizeChapterSlug(String(value).split("/").filter(Boolean).pop() || "");
  }
}

function sanitizeChapterSlug(value) {
  return String(value || "").replace(/-end$/i, "");
}

function assetSrc(value) {
  if (!value) return "";
  if (/^https?:\/\//i.test(value)) return value;
  return `/${String(value).replace(/^\/+/, "")}`;
}

function coverMarkup(comic) {
  const coverImage = comic.cover || comic.image || "";
  const image = coverImage ? `<img src="${escapeHtml(assetSrc(coverImage))}" alt="">` : "";
  const completed = /completed/i.test(comic.status || "") ? `<span class="cover-completed">Completed</span>` : "";
  return `${image}${completed}`;
}

function slugify(value) {
  return String(value)
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
