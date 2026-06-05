const DEFAULT_SOURCE = "https://komiktap.info/";
const ADMIN_BRAND = "Manhwa Scraper Admin";
const PAGE_SIZE = 25;
const DEFAULT_DOWNLOAD_CONCURRENCY = 80;
const PANEL_ROUTES = {
  dashboardPanel: "/admin",
  settingsPanel: "/admin/settings",
  homepagePanel: "/admin/homepage",
  seoPanel: "/admin/seo",
  scrapePanel: "/admin/scrape",
  dataPanel: "/admin/data",
  toolsPanel: "/admin/tools",
  pagesPanel: "/admin/pages",
  menuPanel: "/admin/menu",
  sitemapPanel: "/admin/sitemap"
};

const bannerSlots = [
  { value: "home-top", label: "Home Top", size: "Rasio 12:1 desktop, 4:1 mobile", note: "Setelah header, sebelum hero." },
  { value: "home-before-update", label: "Sebelum Update", size: "Rasio 12:1 desktop, 4:1 mobile", note: "Sebelum daftar update." },
  { value: "update-after-grid", label: "Setelah Update", size: "Rasio 12:1 desktop, 4:1 mobile", note: "Setelah grid/list update." },
  { value: "before-popular", label: "Sebelum Populer", size: "Rasio 12:1 desktop, 4:1 mobile", note: "Sebelum section populer." }
];
const ROUTE_PANELS = Object.fromEntries(Object.entries(PANEL_ROUTES).map(([panel, route]) => [route, panel]));

const els = {
  navItems: document.querySelectorAll("[data-panel]"),
  panels: document.querySelectorAll(".admin-panel"),
  settingsForm: document.querySelector("#settingsForm"),
  siteTitle: document.querySelector("#siteTitleInput"),
  metaDescription: document.querySelector("#metaDescriptionInput"),
  metaKeywords: document.querySelector("#metaKeywordsInput"),
  footerText: document.querySelector("#footerTextInput"),
  headerLogoText: document.querySelector("#headerLogoTextInput"),
  logoUrl: document.querySelector("#logoUrlInput"),
  faviconUrl: document.querySelector("#faviconUrlInput"),
  ogImageUrl: document.querySelector("#ogImageUrlInput"),
  homepageForm: document.querySelector("#homepageForm"),
  heroMode: document.querySelector("#heroModeInput"),
  heroSlugs: document.querySelector("#heroSlugsInput"),
  heroPicker: document.querySelector("#heroPickerInput"),
  setHero: document.querySelector("#setHeroButton"),
  clearHero: document.querySelector("#clearHeroButton"),
  recommendMode: document.querySelector("#recommendModeInput"),
  recommendSlugs: document.querySelector("#recommendSlugsInput"),
  popularMode: document.querySelector("#popularModeInput"),
  popularSlugs: document.querySelector("#popularSlugsInput"),
  bannerManager: document.querySelector("#bannerManager"),
  addBanner: document.querySelector("#addBannerButton"),
  seoForm: document.querySelector("#seoForm"),
  canonicalUrl: document.querySelector("#canonicalUrlInput"),
  schemaEnabled: document.querySelector("#schemaEnabledInput"),
  robotsText: document.querySelector("#robotsTextInput"),
  oldSlug: document.querySelector("#oldSlugInput"),
  newSlug: document.querySelector("#newSlugInput"),
  updateSlug: document.querySelector("#updateSlugButton"),
  comicType: document.querySelector("#comicTypeInput"),
  mode: document.querySelector("#modeInput"),
  popularRange: document.querySelector("#popularRangeInput"),
  popularRangeHint: document.querySelector("#popularRangeHint"),
  scanMaxPage: document.querySelector("#scanMaxPageInput"),
  downloadConcurrency: document.querySelector("#downloadConcurrencyInput"),
  cookie: document.querySelector("#cookieInput"),
  scanCatalog: document.querySelector("#scanCatalogButton"),
  scrapeNewCatalog: document.querySelector("#scrapeNewCatalogButton"),
  updateAll: document.querySelector("#updateAllButton"),
  schedulerStatus: document.querySelector("#schedulerStatus"),
  schedulerEnabled: document.querySelector("#schedulerEnabledInput"),
  schedulerTime: document.querySelector("#schedulerTimeInput"),
  schedulerInterval: document.querySelector("#schedulerIntervalInput"),
  saveScheduler: document.querySelector("#saveSchedulerButton"),
  runSchedulerNow: document.querySelector("#runSchedulerNowButton"),
  load: document.querySelector("#loadButton"),
  scrape: document.querySelector("#scrapeButton"),
  selectAll: document.querySelector("#selectAllButton"),
  progress: document.querySelector("#scrapeProgress"),
  progressTitle: document.querySelector("#progressTitle"),
  progressPercent: document.querySelector("#progressPercent"),
  progressBar: document.querySelector("#progressBar"),
  progressManga: document.querySelector("#progressManga"),
  progressChapter: document.querySelector("#progressChapter"),
  progressImage: document.querySelector("#progressImage"),
  progressEta: document.querySelector("#progressEta"),
  progressSpeed: document.querySelector("#progressSpeed"),
  progressMode: document.querySelector("#progressMode"),
  progressSubBar: document.querySelector("#progressSubBar"),
  progressDetail: document.querySelector("#progressDetail"),
  failedChapterList: document.querySelector("#failedChapterList"),
  pauseJob: document.querySelector("#pauseJobButton"),
  resumeJob: document.querySelector("#resumeJobButton"),
  cancelJob: document.querySelector("#cancelJobButton"),
  retryFailed: document.querySelector("#retryFailedButton"),
  refreshLogs: document.querySelector("#refreshLogsButton"),
  scrapeLogList: document.querySelector("#scrapeLogList"),
  quickButtons: document.querySelectorAll("[data-run-mode]"),
  workflowButtons: document.querySelectorAll("[data-workflow]"),
  grid: document.querySelector("#scrapeGrid"),
  pagination: document.querySelector("#sourcePagination"),
  savedList: document.querySelector("#savedList"),
  status: document.querySelector("#scrapeStatus"),
  sourceHint: document.querySelector("#sourceHint"),
  total: document.querySelector("#totalSaved"),
  chapterCount: document.querySelector("#chapterCount"),
  coverCount: document.querySelector("#coverCount"),
  imageCount: document.querySelector("#imageCount"),
  storageSize: document.querySelector("#storageSize"),
  libraryCount: document.querySelector("#libraryCount"),
  librarySearch: document.querySelector("#librarySearchInput"),
  libraryTypeFilter: document.querySelector("#libraryTypeFilter"),
  libraryStatusFilter: document.querySelector("#libraryStatusFilter"),
  libraryCompletenessFilter: document.querySelector("#libraryCompletenessFilter"),
  bulkType: document.querySelector("#bulkTypeInput"),
  bulkStatus: document.querySelector("#bulkStatusInput"),
  bulkGenres: document.querySelector("#bulkGenresInput"),
  bulkUpdate: document.querySelector("#bulkUpdateButton"),
  refreshLibrary: document.querySelector("#refreshLibraryButton"),
  selectSavedAll: document.querySelector("#selectSavedAllButton"),
  deleteSelected: document.querySelector("#deleteSelectedButton"),
  modeTitle: document.querySelector("#modeTitle"),
  rebuildThumb: document.querySelector("#rebuildThumbButton"),
  scanBroken: document.querySelector("#scanBrokenButton"),
  resetViews: document.querySelector("#resetViewsButton"),
  toolOutput: document.querySelector("#toolOutput"),
  logout: document.querySelector("#logoutButton")
};

let sourceItems = [];
let catalogItems = [];
let savedItems = [];
let currentPage = 1;
let workflow = "new";
let isLoadingSource = false;
let activeJobId = localStorage.getItem("manhwa-portal-active-scrape-job") || "";
let progressTimer = null;
let progressPollFailures = 0;
let settingsCache = {};
const sourceCache = new Map();
const selectedUrls = new Set();
const selectedSlugs = new Set();

if (els.downloadConcurrency) els.downloadConcurrency.value = String(DEFAULT_DOWNLOAD_CONCURRENCY);

init();

async function init() {
  bindEvents();
  await Promise.all([loadSettings(), refreshStats()]);
  await loadScheduler();
  await loadSourceCatalog();
  await loadScrapeLogs();
  syncTitle();
  showPanel(panelFromPath(), false);
  renderPagination();
  if (activeJobId) pollScrapeJob(activeJobId);
}

function bindEvents() {
  els.navItems.forEach(item => {
    item.addEventListener("click", event => {
      event.preventDefault();
      showPanel(item.dataset.panel, true);
    });
  });
  window.addEventListener("popstate", () => showPanel(panelFromPath(), false));
  els.logout?.addEventListener("click", logoutAdmin);

  els.settingsForm.addEventListener("submit", saveSettings);
  els.homepageForm?.addEventListener("submit", saveHomepageSettings);
  els.setHero?.addEventListener("click", setSelectedHero);
  els.clearHero?.addEventListener("click", clearManualHero);
  els.seoForm?.addEventListener("submit", saveSeoSettings);
  els.addBanner?.addEventListener("click", addBannerRow);
  els.updateSlug?.addEventListener("click", updateSlug);
  els.load.addEventListener("click", () => {
    currentPage = 1;
    loadSourceList({ preserveSelection: false });
  });
  els.scanCatalog?.addEventListener("click", scanSourceCatalog);
  els.scrapeNewCatalog?.addEventListener("click", scrapeNewFromCatalog);
  els.updateAll?.addEventListener("click", updateAllComics);
  els.saveScheduler?.addEventListener("click", saveSchedulerSettings);
  els.runSchedulerNow?.addEventListener("click", runSchedulerNow);
  els.scrape.addEventListener("click", scrapeSelected);
  els.selectAll.addEventListener("click", toggleSelectAll);
  els.refreshLibrary.addEventListener("click", refreshLibrary);
  els.selectSavedAll.addEventListener("click", toggleSavedSelection);
  els.deleteSelected.addEventListener("click", deleteSelectedComics);
  els.pauseJob?.addEventListener("click", () => controlJob("pause"));
  els.resumeJob?.addEventListener("click", () => controlJob("resume"));
  els.cancelJob?.addEventListener("click", () => controlJob("cancel"));
  els.retryFailed?.addEventListener("click", () => retryFailedJob());
  els.failedChapterList?.addEventListener("click", event => {
    const button = event.target.closest("[data-retry-chapter-url]");
    if (button) retryFailedJob(button.dataset.retryChapterUrl);
  });
  els.refreshLogs?.addEventListener("click", loadScrapeLogs);
  els.bulkUpdate?.addEventListener("click", bulkUpdateSelected);
  els.rebuildThumb?.addEventListener("click", rebuildThumbnails);
  els.scanBroken?.addEventListener("click", scanBrokenImages);
  els.resetViews?.addEventListener("click", resetPopularManual);
  [els.librarySearch, els.libraryTypeFilter, els.libraryStatusFilter, els.libraryCompletenessFilter].forEach(input => {
    input?.addEventListener("input", renderSavedList);
    input?.addEventListener("change", renderSavedList);
  });
  els.workflowButtons.forEach(button => {
    button.addEventListener("click", () => setWorkflow(button.dataset.workflow || "new"));
  });
  els.comicType.addEventListener("change", resetSourceList);
  els.mode.addEventListener("change", () => {
    syncTitle();
    resetSourceList();
  });
  els.popularRange.addEventListener("change", () => {
    syncTitle();
    resetSourceList();
  });
  els.quickButtons.forEach(button => {
    button.addEventListener("click", () => {
      els.mode.value = button.dataset.runMode;
      showPanel("scrapePanel", true);
      currentPage = 1;
      syncTitle();
      loadSourceList({ preserveSelection: false });
    });
  });

  els.pagination.addEventListener("click", event => {
    const button = event.target.closest("[data-page]");
    if (!button) return;
    currentPage = Number(button.dataset.page || 1);
    if (catalogItems.length) {
      renderResults(pagedSourceItems(), { selectable: true });
      renderPagination();
      return;
    }
    loadSourceList({ preserveSelection: true });
  });
}

function panelFromPath() {
  const path = window.location.pathname.replace(/\/$/, "") || "/admin";
  return ROUTE_PANELS[path] || ROUTE_PANELS[`${path}/`] || "dashboardPanel";
}

function showPanel(panelId, push = false) {
  els.panels.forEach(panel => panel.classList.toggle("active", panel.id === panelId));
  els.navItems.forEach(item => item.classList.toggle("active", item.dataset.panel === panelId));
  const route = PANEL_ROUTES[panelId] || "/admin";
  if (push && window.location.pathname !== route) history.pushState({}, "", route);
}

function resetSourceList() {
  currentPage = 1;
  sourceItems = [];
  catalogItems = [];
  selectedUrls.clear();
  renderResults([], { selectable: true });
  renderPagination();
  els.sourceHint.textContent = "Klik Muat Daftar untuk melihat hasil.";
}

async function loadScheduler() {
  try {
    const response = await fetch("/api/scheduler");
    const data = await readApiJson(response, "Gagal membaca respons scheduler.");
    if (!response.ok || !data.ok) throw new Error(data.error || "Gagal memuat scheduler.");
    renderScheduler(data.scheduler || {});
  } catch (error) {
    if (els.schedulerStatus) els.schedulerStatus.textContent = error.message;
  }
}

function renderScheduler(scheduler = {}) {
  if (els.schedulerEnabled) els.schedulerEnabled.checked = scheduler.enabled === true;
  if (els.schedulerTime) els.schedulerTime.value = scheduler.dailyTime || "03:00";
  if (els.schedulerInterval) els.schedulerInterval.value = String(scheduler.intervalHours || 0);
  if (!els.schedulerStatus) return;
  const lastRun = scheduler.lastRunAt ? new Date(scheduler.lastRunAt).toLocaleString("id-ID") : "belum pernah";
  els.schedulerStatus.textContent = scheduler.enabled
    ? `Aktif · jam ${scheduler.dailyTime || "03:00"} · terakhir ${lastRun}`
    : `Nonaktif · terakhir ${lastRun}`;
}

async function saveSchedulerSettings() {
  setStatus("Menyimpan scheduler...", "running");
  try {
    const response = await fetch("/api/scheduler", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({
        enabled: els.schedulerEnabled?.checked === true,
        dailyTime: els.schedulerTime?.value || "03:00",
        intervalHours: Number(els.schedulerInterval?.value || 0),
        cookie: els.cookie.value.trim()
      })
    });
    const data = await readApiJson(response, "Gagal membaca respons scheduler.");
    if (!response.ok || !data.ok) throw new Error(data.error || "Gagal menyimpan scheduler.");
    renderScheduler(data.scheduler);
    setStatus("Scheduler tersimpan.", "");
  } catch (error) {
    setStatus(error.message, "error");
  }
}

async function runSchedulerNow() {
  setStatus("Menjalankan update semua sekarang...", "running");
  setScrapeBusy(true);
  try {
    const response = await fetch("/api/scheduler-run-now", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({ cookie: els.cookie.value.trim() })
    });
    const data = await readApiJson(response, "Gagal membaca respons scheduler.");
    if (!response.ok || !data.ok) throw new Error(data.error || "Gagal menjalankan update sekarang.");
    await loadScheduler();
    startTrackingJob(data);
  } catch (error) {
    setStatus(error.message, "error");
    setScrapeBusy(false);
  }
}

function setWorkflow(nextWorkflow) {
  workflow = "new";
  currentPage = 1;
  selectedUrls.clear();
  syncTitle();

  resetSourceList();
  setStatus("Mode judul baru aktif. Judul yang sudah tersimpan akan disembunyikan dari daftar.", "");
}

async function loadSettings() {
  const response = await fetch("/api/settings");
  const data = await readApiJson(response, "Gagal membaca respons settings.");
  if (!response.ok || !data.ok) throw new Error(data.error || "Gagal memuat settings.");

  const settings = data.settings || {};
  settingsCache = settings;
  const brand = document.querySelector(".admin-brand");
  if (brand) brand.textContent = ADMIN_BRAND;
  els.siteTitle.value = settings.siteTitle || "";
  els.metaDescription.value = settings.metaDescription || "";
  els.metaKeywords.value = settings.metaKeywords || "";
  els.footerText.value = settings.footerText || "";
  els.headerLogoText.value = settings.headerLogoText || "";
  els.logoUrl.value = settings.logoUrl || "";
  els.faviconUrl.value = settings.faviconUrl || "";
  if (els.ogImageUrl) els.ogImageUrl.value = settings.ogImageUrl || "";
  if (els.heroMode) els.heroMode.value = settings.heroMode || "auto";
  if (els.heroSlugs) els.heroSlugs.value = (settings.heroSlugs || []).join(", ");
  renderHeroPicker();
  if (els.recommendMode) els.recommendMode.value = settings.recommendMode || "auto";
  if (els.recommendSlugs) els.recommendSlugs.value = (settings.recommendSlugs || []).join(", ");
  if (els.popularMode) els.popularMode.value = settings.popularMode || "auto";
  if (els.popularSlugs) els.popularSlugs.value = (settings.popularSlugs || []).join(", ");
  if (els.canonicalUrl) els.canonicalUrl.value = settings.canonicalUrl || "";
  if (els.schemaEnabled) els.schemaEnabled.checked = settings.schemaEnabled !== false;
  if (els.robotsText) els.robotsText.value = settings.robotsText || "";
  renderBannerManager(settings.bannerPlaceholders || []);
}

async function logoutAdmin() {
  await fetch("/api/admin-logout", { method: "POST" }).catch(() => {});
  localStorage.removeItem("manhwa-portal-active-scrape-job");
  location.href = "/admin/login";
}

async function saveSettings(event) {
  event.preventDefault();
  setStatus("Menyimpan settings...", "running");

  const response = await fetch("/api/settings", {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({
      siteTitle: els.siteTitle.value,
      metaDescription: els.metaDescription.value,
      metaKeywords: els.metaKeywords.value,
      footerText: els.footerText.value,
      headerLogoText: els.headerLogoText.value,
      logoUrl: els.logoUrl.value,
      faviconUrl: els.faviconUrl.value,
      ogImageUrl: els.ogImageUrl?.value || settingsCache.ogImageUrl || ""
    })
  });
  const data = await readApiJson(response, "Gagal membaca respons settings.");
  if (!response.ok || !data.ok) {
    setStatus(data.error || "Gagal menyimpan settings.", "error");
    return;
  }

  setStatus("Settings tersimpan. Refresh website untuk melihat perubahan penuh.", "");
  settingsCache = data.settings || settingsCache;
}

async function saveHomepageSettings(event) {
  event.preventDefault();
  await saveSettingsPayload({
    heroMode: els.heroMode.value,
    heroSlugs: splitSlugs(els.heroSlugs.value),
    recommendMode: els.recommendMode.value,
    recommendSlugs: splitSlugs(els.recommendSlugs.value),
    popularMode: els.popularMode.value,
    popularSlugs: splitSlugs(els.popularSlugs.value),
    bannerPlaceholders: readBannerRows()
  }, "Homepage control tersimpan.");
}

async function setSelectedHero() {
  const slug = els.heroPicker?.value;
  if (!slug) {
    setStatus("Pilih manhwa terlebih dahulu untuk hero.", "error");
    return;
  }
  if (els.heroMode) els.heroMode.value = "manual";
  if (els.heroSlugs) els.heroSlugs.value = slug;
  await saveSettingsPayload({ heroMode: "manual", heroSlugs: [slug] }, "Hero homepage diperbarui.");
}

async function clearManualHero() {
  if (els.heroMode) els.heroMode.value = "auto";
  if (els.heroSlugs) els.heroSlugs.value = "";
  if (els.heroPicker) els.heroPicker.value = "";
  await saveSettingsPayload({ heroMode: "auto", heroSlugs: [] }, "Hero kembali auto dari populer.");
}

async function saveSeoSettings(event) {
  event.preventDefault();
  await saveSettingsPayload({
    canonicalUrl: els.canonicalUrl.value,
    schemaEnabled: els.schemaEnabled.checked,
    robotsText: els.robotsText.value
  }, "SEO settings tersimpan.");
}

async function saveSettingsPayload(payload, successText) {
  setStatus("Menyimpan settings...", "running");
  const response = await fetch("/api/settings", {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({ ...settingsCache, ...payload })
  });
  const data = await readApiJson(response, "Gagal membaca respons settings.");
  if (!response.ok || !data.ok) {
    setStatus(data.error || "Gagal menyimpan settings.", "error");
    return;
  }
  settingsCache = data.settings || {};
  await loadSettings();
  setStatus(successText, "");
}

async function refreshStats() {
  const response = await fetch("/api/stats");
  const data = await readApiJson(response, "Gagal membaca statistik.");
  els.total.textContent = data.total || 0;
  els.chapterCount.textContent = data.chapters || 0;
  els.coverCount.textContent = data.savedImages || 0;
  if (els.imageCount) els.imageCount.textContent = data.totalImages || 0;
  if (els.storageSize) els.storageSize.textContent = data.storageLabel || "0 B";
  await refreshLibrary();
}

async function refreshLibrary() {
  const response = await fetch("/api/comics");
  const data = await readApiJson(response, "Gagal membaca library.");
  if (!response.ok || !data.ok) throw new Error(data.error || "Gagal memuat library.");

  savedItems = data.results || [];
  selectedSlugs.clear();
  els.total.textContent = data.total || 0;
  els.libraryCount.textContent = data.total || 0;
  els.chapterCount.textContent = savedItems.reduce((sum, item) => sum + (item.chapterCount || 0), 0);
  els.coverCount.textContent = savedItems.filter(item => item.cover).length;
  renderSavedList();
  renderHeroPicker();
}

function renderHeroPicker() {
  if (!els.heroPicker) return;
  const manhwa = savedItems
    .filter(item => (item.type || "").toLowerCase() === "manhwa")
    .sort((a, b) => (a.title || "").localeCompare(b.title || ""));
  const selected = splitSlugs(els.heroSlugs?.value || "")[0] || settingsCache.heroSlugs?.[0] || "";
  els.heroPicker.innerHTML = manhwa.length
    ? `<option value="">Pilih manhwa...</option>${manhwa.map(item => `<option value="${escapeHtml(item.slug)}" ${item.slug === selected ? "selected" : ""}>${escapeHtml(item.title)} (${item.chapterCount || 0} chapter)</option>`).join("")}`
    : `<option value="">Belum ada Manhwa tersimpan</option>`;
}

async function loadSourceList(options = {}) {
  const preserveSelection = options.preserveSelection === true;
  catalogItems = [];
  const cacheKey = JSON.stringify(basePayload());
  const cached = sourceCache.get(cacheKey);
  if (cached) {
    sourceItems = cached.results || [];
    if (!preserveSelection) selectedUrls.clear();
    renderResults(sourceItems, { selectable: true });
    renderPagination(cached.page);
    els.sourceHint.textContent = `${cached.count} ${typeLabel()} dari cache · ${filterLabel(cached.filters)} · ${cached.listingUrl || ""}`;
    if (preserveSelection && selectedUrls.size) {
      updateSelectionStatus();
    } else {
      setStatus(`${cached.count} ${typeLabel()} ditemukan dari ${filterLabel(cached.filters)}. Centang judul yang ingin diambil.`, "");
    }
    return;
  }

  setStatus(`Mengambil ${typeLabel()} dari Komiktap...`, "running");
  isLoadingSource = true;
  els.load.disabled = true;
  if (!preserveSelection) selectedUrls.clear();
  syncTitle();
  renderSourceLoading();
  renderPagination();

  try {
    const response = await fetch("/api/source-list", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify(basePayload())
    });
    const data = await readApiJson(response, "Gagal membaca daftar sumber.");
    if (!response.ok || !data.ok) throw new Error(data.error || "Gagal memuat daftar sumber.");

    sourceItems = data.results || [];
    sourceCache.set(cacheKey, data);
    renderResults(sourceItems, { selectable: true });
    renderPagination();
    els.sourceHint.textContent = `${data.count} ${typeLabel()} dari ${filterLabel(data.filters)} · ${data.listingUrl}`;
    if (preserveSelection && selectedUrls.size) {
      updateSelectionStatus();
    } else {
      setStatus(`${data.count} ${typeLabel()} ditemukan dari ${filterLabel(data.filters)}. Centang judul yang ingin diambil.`, "");
    }
  } catch (error) {
    setStatus(error.message, "error");
  } finally {
    isLoadingSource = false;
    els.load.disabled = false;
    renderPagination();
  }
}

async function loadSourceCatalog() {
  try {
    const response = await fetch("/api/source-catalog");
    const data = await readApiJson(response, "Gagal membaca katalog sumber.");
    if (!response.ok || !data.ok) throw new Error(data.error || "Gagal memuat cache katalog.");
    catalogItems = data.catalog?.items || [];
    if (catalogItems.length) {
      sourceItems = catalogItems;
      renderResults(sourceItems.slice(0, PAGE_SIZE), { selectable: true });
      renderPagination();
      renderCatalogHint(data.catalog);
    }
  } catch (error) {
    setStatus(error.message, "error");
  }
}

async function scanSourceCatalog() {
  setStatus("Scan katalog Komiktap berjalan. Ini hanya ambil daftar, belum scrape gambar reader.", "running");
  setScrapeBusy(true);
  renderSourceLoading();
  try {
    const response = await fetch("/api/source-scan", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({
        ...basePayload(),
        comicType: els.comicType.value,
        maxPages: Number(els.scanMaxPage?.value || 30)
      })
    });
    const data = await readApiJson(response, "Gagal membaca hasil scan katalog.");
    if (!response.ok || !data.ok) throw new Error(data.error || "Gagal scan katalog.");
    catalogItems = data.catalog?.items || [];
    sourceItems = catalogItems;
    selectedUrls.clear();
    currentPage = 1;
    renderResults(pagedSourceItems(), { selectable: true });
    renderPagination();
    renderCatalogHint(data.catalog);
    setStatus(`Scan selesai: ${data.catalog.total} judul, ${data.catalog.newCount} baru, ${data.catalog.updateCount} perlu update.`, "");
  } catch (error) {
    setStatus(error.message, "error");
  } finally {
    setScrapeBusy(false);
  }
}

async function scrapeNewFromCatalog() {
  if (!catalogItems.length) {
    setStatus("Scan source dulu agar sistem tahu judul mana yang baru.", "error");
    return;
  }
  const urls = catalogItems
    .filter(item => item.scrapeStatus === "new" && item.sourceUrl)
    .map(item => item.sourceUrl);
  if (!urls.length) {
    setStatus("Tidak ada judul baru dari hasil scan terakhir.", "error");
    return;
  }
  await createScrapeJobFromUrls(urls, {
    smartUpdate: true,
    jobKind: "scrape-all-new",
    statusText: `Membuat job scrape ${urls.length} judul baru...`
  });
}

async function updateAllComics() {
  setStatus("Membuat job update semua koleksi tersimpan...", "running");
  setScrapeBusy(true);
  try {
    const response = await fetch("/api/scrape-update-all", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify(basePayload())
    });
    const data = await readApiJson(response, "Gagal membaca respons update.");
    if (!response.ok || !data.ok) throw new Error(data.error || "Gagal membuat job update.");
    startTrackingJob(data);
  } catch (error) {
    setStatus(error.message, "error");
    setScrapeBusy(false);
  }
}

async function createScrapeJobFromUrls(urls, options = {}) {
  setStatus(options.statusText || "Membuat job scrape...", "running");
  setScrapeBusy(true);
  try {
    const response = await fetch("/api/scrape-selected", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({
        ...basePayload(),
        urls,
        saveImages: true,
        smartUpdate: options.smartUpdate !== false,
        jobKind: options.jobKind || "manual"
      })
    });
    const data = await readApiJson(response, "Gagal membaca respons scrape.");
    if (!response.ok || !data.ok) throw new Error(data.error || "Scrape gagal.");
    startTrackingJob(data);
  } catch (error) {
    setStatus(error.message, "error");
    setScrapeBusy(false);
  }
}

function startTrackingJob(data) {
  activeJobId = data.jobId;
  progressPollFailures = 0;
  localStorage.setItem("manhwa-portal-active-scrape-job", activeJobId);
  selectedUrls.clear();
  renderJobProgress(data.job);
  pollScrapeJob(activeJobId);
}

async function readApiJson(response, fallbackMessage = "Respons API tidak valid.") {
  const text = await response.text();
  if (!text.trim()) {
    const error = new Error("Respons API kosong dari server. Progress akan dicoba lagi.");
    error.transient = true;
    error.status = response.status;
    throw error;
  }

  try {
    return JSON.parse(text);
  } catch (parseError) {
    const preview = text.replace(/\s+/g, " ").trim().slice(0, 180);
    const error = new Error(preview ? `${fallbackMessage} Preview: ${preview}` : fallbackMessage);
    error.transient = true;
    error.status = response.status;
    throw error;
  }
}

function renderCatalogHint(catalog = {}) {
  if (!els.sourceHint) return;
  const scanned = catalog.scannedAt ? new Date(catalog.scannedAt).toLocaleString("id-ID") : "-";
  const source = catalog.listingUrl ? ` · ${catalog.listingUrl}` : "";
  els.sourceHint.textContent = `${catalog.total || 0} katalog · ${catalog.newCount || 0} baru · ${catalog.updateCount || 0} update · ${filterLabel(catalog.filters)} · scan ${scanned}${source}`;
}

async function scrapeSelected() {
  const urls = [...selectedUrls];

  if (urls.length === 0) {
    setStatus("Pilih minimal satu judul dulu dari daftar.", "error");
    return;
  }

  syncTitle();
  await createScrapeJobFromUrls(urls, {
    smartUpdate: false,
    jobKind: "manual-selected",
    statusText: "Membuat job scrape..."
  });
}

async function pollScrapeJob(jobId) {
  clearTimeout(progressTimer);

  try {
    const response = await fetch(`/api/scrape-job?id=${encodeURIComponent(jobId)}`);
    const data = await readApiJson(response, "Gagal membaca progress scrape.");
    if (!response.ok || !data.ok) throw new Error(data.error || "Gagal membaca progress scrape.");

    progressPollFailures = 0;
    renderJobProgress(data.job);

    if (data.job.status === "completed") {
      renderResults(data.job.results || [], { selectable: false });
      sourceItems = data.job.results || [];
      sourceCache.clear();
      await refreshLibrary();
      await loadScrapeLogs();
      setStatus(`Selesai. ${data.job.scraped} judul tersimpan lengkap beserta chapter.`, "");
      notifyAdmin("Scrape selesai", `${data.job.scraped} judul selesai diproses.`);
      localStorage.removeItem("manhwa-portal-active-scrape-job");
      activeJobId = "";
      setScrapeBusy(false);
      return;
    }

    if (data.job.status === "failed") {
      setStatus(`Scrape gagal: ${data.job.message}`, "error");
      await loadScrapeLogs();
      localStorage.removeItem("manhwa-portal-active-scrape-job");
      activeJobId = "";
      setScrapeBusy(false);
      return;
    }

    if (data.job.status === "cancelled") {
      setStatus("Scrape dibatalkan.", "error");
      await loadScrapeLogs();
      localStorage.removeItem("manhwa-portal-active-scrape-job");
      activeJobId = "";
      setScrapeBusy(false);
      return;
    }

    setScrapeBusy(true);
    progressTimer = setTimeout(() => pollScrapeJob(jobId), 1200);
  } catch (error) {
    if (activeJobId === jobId && (error.transient || error.name === "TypeError") && progressPollFailures < 10) {
      progressPollFailures += 1;
      const delay = Math.min(12000, 1500 + progressPollFailures * 1000);
      setStatus(`Respons progress terputus sementara. Mencoba lagi ${progressPollFailures}/10...`, "running");
      setScrapeBusy(true);
      progressTimer = setTimeout(() => pollScrapeJob(jobId), delay);
      return;
    }
    setStatus(error.message, "error");
    setScrapeBusy(false);
  }
}

function renderJobProgress(job) {
  const totalUnits = job.totalChapters || job.totalManga || 1;
  const doneUnits = job.totalChapters ? job.doneChapters : job.doneManga;
  const percent = job.status === "completed"
    ? 100
    : Math.max(job.totalChapters ? 1 : 0, Math.min(99, Math.round((doneUnits / totalUnits) * 100)));
  const subPercent = job.totalChapters
    ? Math.max(0, Math.min(100, Math.round(((job.currentChapterIndex || 0) / job.totalChapters) * 100)))
    : Math.max(0, Math.min(100, Math.round(((job.currentUrlIndex || 0) / Math.max(1, job.totalManga || 1)) * 100)));
  els.progress.hidden = false;
  els.progressTitle.textContent = job.message || "Scrape berjalan...";
  els.progressPercent.textContent = `${percent}%`;
  els.progressBar.style.width = `${percent}%`;
  els.progressManga.textContent = `${job.doneManga}/${job.totalManga}`;
  els.progressChapter.textContent = `${job.doneChapters}/${job.totalChapters || "..."}`;
  els.progressImage.textContent = String(job.doneImages || 0);
  if (els.progressEta) els.progressEta.textContent = estimateEta(job);
  if (els.progressSpeed) els.progressSpeed.textContent = scrapeSpeedLabel(job);
  if (els.progressMode) {
    const mode = job.runtimeMode === "poll-safe" ? "poll aman" : "worker";
    els.progressMode.textContent = `${job.effectiveDownloadConcurrency || job.downloadConcurrency || 6} img / ${job.effectiveChapterConcurrency || job.chapterConcurrency || 1} ch (${mode})`;
  }
  if (els.progressSubBar) els.progressSubBar.style.width = `${subPercent}%`;
  els.progressDetail.textContent = [
    [job.currentTitle, job.currentChapter].filter(Boolean).join(" - ") || "Menunggu proses berikutnya...",
    stageLabel(job),
    job.lastBatchImages ? `Batch terakhir ${job.lastBatchImages} gambar / ${job.lastBatchSeconds || "-"}s` : "",
    job.queuePosition ? `Antrean #${job.queuePosition}` : "",
    job.failedChapters?.length ? `${job.failedChapters.length} chapter gagal` : "",
    job.validationSummary?.issueCount ? `${job.validationSummary.issueCount} issue validasi` : ""
  ].filter(Boolean).join(" · ");
  renderFailedChapterList(job);
  setStatus(job.message || "Scrape berjalan...", job.status === "failed" ? "error" : job.status === "paused" ? "" : "running");
  if (els.retryFailed) els.retryFailed.disabled = !(job.failedChapters?.length);
}

function renderFailedChapterList(job) {
  if (!els.failedChapterList) return;
  const failures = job.failedChapters || [];
  const validation = job.validationSummary;
  if (!failures.length && !validation?.issueCount) {
    els.failedChapterList.innerHTML = "";
    return;
  }
  const failedRows = failures.slice(0, 8).map(item => `
    <div class="failed-chapter-row">
      <span>
        <strong>${escapeHtml(item.mangaTitle || "Manga")}</strong>
        <small>${escapeHtml(item.chapterTitle || "Chapter")} · ${escapeHtml(item.error || "Gagal scrape")}</small>
      </span>
      <button class="secondary small" type="button" data-retry-chapter-url="${escapeHtml(item.chapterUrl || "")}">Retry</button>
    </div>
  `).join("");
  const validationRow = validation?.issueCount ? `
    <div class="validation-summary ${validation.ok ? "ok" : "warn"}">
      Validasi: ${validation.issueCount} issue · ${validation.emptyChapters} chapter kosong · ${validation.missingImages} image hilang · ${validation.missingThumbnails} thumbnail hilang
    </div>
  ` : "";
  els.failedChapterList.innerHTML = `${validationRow}${failedRows}`;
}

function setScrapeBusy(isBusy) {
  els.scrape.disabled = isBusy;
  els.load.disabled = isBusy;
  els.selectAll.disabled = isBusy;
  if (els.scanCatalog) els.scanCatalog.disabled = isBusy;
  if (els.scrapeNewCatalog) els.scrapeNewCatalog.disabled = isBusy;
  if (els.updateAll) els.updateAll.disabled = isBusy;
  if (els.runSchedulerNow) els.runSchedulerNow.disabled = isBusy;
  els.quickButtons.forEach(button => {
    button.disabled = isBusy;
  });
}

async function controlJob(action) {
  if (!activeJobId) {
    setStatus("Tidak ada job aktif.", "error");
    return;
  }
  const response = await fetch("/api/scrape-job-control", {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({ id: activeJobId, action })
  });
  const data = await readApiJson(response, "Gagal membaca respons kontrol job.");
  if (!response.ok || !data.ok) {
    setStatus(data.error || "Gagal mengontrol job.", "error");
    return;
  }
  renderJobProgress(data.job);
  if (action === "cancel") {
    localStorage.removeItem("manhwa-portal-active-scrape-job");
    activeJobId = "";
    setScrapeBusy(false);
  } else {
    pollScrapeJob(data.job.id);
  }
}

async function retryFailedJob(chapterUrl = "") {
  if (!activeJobId) {
    setStatus("Tidak ada job sumber untuk retry.", "error");
    return;
  }
  const response = await fetch("/api/scrape-retry-failed", {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({ id: activeJobId, chapterUrl, ...basePayload() })
  });
  const data = await readApiJson(response, "Gagal membaca respons retry.");
  if (!response.ok || !data.ok) {
    setStatus(data.error || "Gagal retry.", "error");
    return;
  }
  activeJobId = data.jobId;
  localStorage.setItem("manhwa-portal-active-scrape-job", activeJobId);
  renderJobProgress(data.job);
  pollScrapeJob(activeJobId);
}

function estimateEta(job) {
  const total = job.totalChapters || job.totalManga || 0;
  const done = job.totalChapters ? job.doneChapters : job.doneManga;
  if (!total || !done || !job.startedAt) return "-";
  const elapsed = Date.now() - new Date(job.startedAt).getTime();
  const remaining = Math.max(0, (elapsed / done) * (total - done));
  const minutes = Math.ceil(remaining / 60000);
  return minutes <= 1 ? "<1 menit" : `${minutes} menit`;
}

function scrapeSpeedLabel(job) {
  if (job.lastBatchImages && job.lastBatchSeconds) {
    const speed = Number(job.lastBatchImages) / Math.max(0.001, Number(job.lastBatchSeconds));
    return `${speed.toFixed(speed >= 10 ? 0 : 1)}/d`;
  }
  if (job.doneImages && job.startedAt) {
    const elapsed = Math.max(1, (Date.now() - new Date(job.startedAt).getTime()) / 1000);
    const speed = Number(job.doneImages) / elapsed;
    return `${speed.toFixed(speed >= 10 ? 0 : 1)}/d`;
  }
  return "-";
}

function stageLabel(job) {
  if (job.status === "completed") return "Selesai";
  if (job.stage === "downloading") return "Download gambar";
  if (job.currentComic) return "Download chapter";
  if ((job.currentUrlIndex || 0) < (job.totalManga || 0)) return "Ambil metadata";
  return "Menunggu";
}

function notifyAdmin(title, body) {
  document.title = `${title} - ${ADMIN_BRAND}`;
  if (!("Notification" in window)) return;
  if (Notification.permission === "granted") {
    new Notification(title, { body });
  } else if (Notification.permission === "default") {
    Notification.requestPermission().then(permission => {
      if (permission === "granted") new Notification(title, { body });
    });
  }
}

function basePayload() {
  const speed = Math.max(1, Math.min(80, Number(els.downloadConcurrency?.value || DEFAULT_DOWNLOAD_CONCURRENCY)));
  return {
    source: DEFAULT_SOURCE,
    comicType: els.comicType.value,
    mode: els.mode.value,
    popularRange: els.popularRange.value,
    page: currentPage,
    limit: PAGE_SIZE,
    downloadConcurrency: speed,
    chapterConcurrency: speed >= 80 ? 12 : speed >= 48 ? 8 : speed >= 24 ? 4 : speed >= 12 ? 2 : 1,
    onlyNew: workflow === "new",
    cookie: els.cookie.value.trim()
  };
}

function renderResults(results, options = {}) {
  if (!results.length) {
    els.grid.innerHTML = `<div class="empty-state">Tidak ada judul baru di halaman ini. Coba halaman berikutnya.</div>`;
    return;
  }

  els.grid.innerHTML = results.map(item => {
    const coverSrc = item.cover ? `/${item.cover}` : item.image;
    const cover = coverSrc ? `<img src="${escapeHtml(coverSrc)}" alt="">` : "";
    const selectable = options.selectable && !item.alreadySaved;
    const checked = selectedUrls.has(item.sourceUrl) ? "checked" : "";
    const statusClass = item.scrapeStatus || (item.alreadySaved ? "saved" : "new");
    const statusLabel = item.scrapeStatusLabel || (item.alreadySaved ? "Sudah ada" : "Belum ada");
    const overlay = item.dataSaved || item.imagesSaved || item.alreadySaved ? `
      <div class="scrape-overlay">
        <div>
          <div class="ok">${escapeHtml(statusLabel)}</div>
          <div class="${item.imagesSaved ? "warn" : ""}">Cover Saved ${item.imagesSaved ? "OK" : "-"}</div>
          <div class="${item.chapterImagesSaved ? "warn" : ""}">Reader Images ${item.chapterImagesSaved ? "OK" : "-"}</div>
        </div>
      </div>
    ` : "";

    return `
      <article class="scrape-card ${selectable && checked ? "selected" : ""} ${item.alreadySaved ? "already-saved" : ""}" data-url="${escapeHtml(item.sourceUrl)}">
        <span class="saved-ribbon ${escapeHtml(statusClass)}">${escapeHtml(statusLabel)}</span>
        ${selectable ? `<label class="select-box"><input type="checkbox" data-select-url="${escapeHtml(item.sourceUrl)}" ${checked}> Pilih</label>` : ""}
        <a class="scrape-cover ${cover ? "" : "no-image"}" href="${escapeHtml(item.sourceUrl)}" target="_blank" rel="noreferrer">
          ${cover || "No cover"}
          <span class="scrape-country">${typeBadge(item.type)}</span>
          <span class="scrape-chapter-badge">${item.chapterCount || 0} Chapter</span>
          ${overlay}
        </a>
        <h2><a href="/manga/${escapeHtml(item.slug)}">${escapeHtml(item.title)}</a></h2>
        <div class="scrape-meta-line">
          <span>${escapeHtml(item.chapter || "Chapter terbaru")}</span>
          <strong>${item.localChapterCount || 0}/${item.sourceChapterCount || item.chapterCount || 0} Chapter</strong>
        </div>
        ${item.incompleteChapterCount || item.pendingChapterCount ? `<p class="scrape-health">${item.pendingChapterCount || 0} perlu diambil · ${item.incompleteChapterCount || 0} belum lengkap</p>` : ""}
        <div class="scrape-rating"><span>${stars(item.rating)}</span><small>${Number(item.rating || 0).toFixed(1)}</small></div>
      </article>
    `;
  }).join("");

  els.grid.querySelectorAll("[data-select-url]").forEach(input => {
    input.addEventListener("change", () => {
      if (input.checked) selectedUrls.add(input.dataset.selectUrl);
      else selectedUrls.delete(input.dataset.selectUrl);
      input.closest(".scrape-card")?.classList.toggle("selected", input.checked);
      updateSelectionStatus();
    });
  });
}

function renderSourceLoading() {
  els.grid.innerHTML = Array.from({ length: 10 }, () => `
    <article class="scrape-card skeleton-card">
      <span class="select-box skeleton-line"></span>
      <span class="scrape-cover skeleton-box"></span>
      <span class="skeleton-line title"></span>
      <span class="skeleton-line"></span>
    </article>
  `).join("");
}

function pagedSourceItems() {
  const start = (currentPage - 1) * PAGE_SIZE;
  return sourceItems.slice(start, start + PAGE_SIZE);
}

function renderPagination() {
  if (catalogItems.length && sourceItems === catalogItems) {
    const totalPages = Math.max(1, Math.ceil(sourceItems.length / PAGE_SIZE));
    currentPage = Math.min(Math.max(1, currentPage), totalPages);
    const pages = compactPages(currentPage, totalPages);
    els.pagination.innerHTML = pages.map(page => page === "..."
      ? `<span class="pagination-ellipsis">...</span>`
      : `<button class="${page === currentPage ? "active" : ""}" type="button" data-page="${page}" ${isLoadingSource ? "disabled" : ""}>${page}</button>`
    ).join("");
    return;
  }
  const start = Math.max(1, currentPage - 2);
  const pages = Array.from({ length: 5 }, (_, index) => start + index);
  els.pagination.innerHTML = pages.map(page => `
    <button class="${page === currentPage ? "active" : ""}" type="button" data-page="${page}" ${isLoadingSource ? "disabled" : ""}>${page}</button>
  `).join("");
}

function compactPages(current, total) {
  return Array.from({ length: total }, (_, index) => index + 1)
    .filter(page => page === 1 || page === total || Math.abs(page - current) <= 1)
    .reduce((pages, page) => {
      if (pages.length && page - pages[pages.length - 1] > 1) pages.push("...");
      pages.push(page);
      return pages;
    }, []);
}

function renderSavedList() {
  const items = filteredSavedItems();
  if (!items.length) {
    els.savedList.innerHTML = `<div class="empty-state">Belum ada komik tersimpan.</div>`;
    return;
  }

  els.savedList.innerHTML = items.map(item => {
    const coverSrc = item.cover ? `/${item.cover}` : item.image;
    const checked = selectedSlugs.has(item.slug) ? "checked" : "";
    return `
      <article class="saved-item ${checked ? "selected" : ""}">
        <label class="select-box"><input type="checkbox" data-select-slug="${escapeHtml(item.slug)}" ${checked}></label>
        <a class="saved-cover" href="/manga/${escapeHtml(item.slug)}">
          ${coverSrc ? `<img src="${escapeHtml(coverSrc)}" alt="">` : ""}
        </a>
        <div class="saved-info">
          <h3><a href="/manga/${escapeHtml(item.slug)}">${escapeHtml(item.title)}</a></h3>
          <p>${escapeHtml(item.type)} - ${escapeHtml(item.status)} - ${item.chapterCount || 0} chapter - ${item.thumbnailCount || 0} thumbnail${item.missingThumbnailCount ? ` - ${item.missingThumbnailCount} perlu thumbnail` : ""}</p>
          <div class="preview-links">
            <a href="${escapeHtml(item.previewUrl)}" target="_blank">Preview</a>
            ${item.latestReaderUrl ? `<a href="${escapeHtml(item.latestReaderUrl)}" target="_blank">Reader</a>` : ""}
          </div>
        </div>
        ${item.missingThumbnailCount ? `<span class="quality-warning">Thumb missing</span>` : `<span class="quality-ok">Thumbnail OK</span>`}
        <button class="secondary small" data-pin-hero="${escapeHtml(item.slug)}" type="button">Hero</button>
        <button class="secondary small" data-pin-recommend="${escapeHtml(item.slug)}" type="button">Rekom</button>
        <button class="secondary small" data-rebuild-one="${escapeHtml(item.slug)}" type="button">Thumb</button>
        <button class="danger small" data-delete-slug="${escapeHtml(item.slug)}" type="button">Hapus</button>
      </article>
    `;
  }).join("");

  els.savedList.querySelectorAll("[data-select-slug]").forEach(input => {
    input.addEventListener("change", () => {
      if (input.checked) selectedSlugs.add(input.dataset.selectSlug);
      else selectedSlugs.delete(input.dataset.selectSlug);
      input.closest(".saved-item")?.classList.toggle("selected", input.checked);
    });
  });

  els.savedList.querySelectorAll("[data-delete-slug]").forEach(button => {
    button.addEventListener("click", () => deleteComics([button.dataset.deleteSlug]));
  });
  els.savedList.querySelectorAll("[data-rebuild-one]").forEach(button => {
    button.addEventListener("click", () => rebuildThumbnails([button.dataset.rebuildOne]));
  });
  els.savedList.querySelectorAll("[data-pin-hero]").forEach(button => {
    button.addEventListener("click", () => pinHomepageSlug("hero", button.dataset.pinHero));
  });
  els.savedList.querySelectorAll("[data-pin-recommend]").forEach(button => {
    button.addEventListener("click", () => pinHomepageSlug("recommend", button.dataset.pinRecommend));
  });
}

function filteredSavedItems() {
  const query = (els.librarySearch?.value || "").trim().toLowerCase();
  const type = els.libraryTypeFilter?.value || "all";
  const status = els.libraryStatusFilter?.value || "all";
  const completeness = els.libraryCompletenessFilter?.value || "all";
  return savedItems.filter(item => {
    const haystack = [item.title, item.slug, item.type, item.status, item.chapter].join(" ").toLowerCase();
    const complete = item.cover && item.chapterImagesSaved && (item.thumbnailCount || 0) >= (item.chapterCount || 0);
    return (!query || haystack.includes(query))
      && (type === "all" || item.type === type)
      && (status === "all" || item.status === status)
      && (completeness === "all" || (completeness === "complete" ? complete : !complete));
  });
}

function toggleSelectAll() {
  if (!sourceItems.length) return;
  const visibleItems = catalogItems.length && sourceItems === catalogItems ? pagedSourceItems() : sourceItems;
  const selectableItems = visibleItems.filter(item => !item.alreadySaved && item.sourceUrl);
  const shouldSelect = selectableItems.some(item => !selectedUrls.has(item.sourceUrl));
  selectableItems.forEach(item => {
    if (shouldSelect) selectedUrls.add(item.sourceUrl);
    else selectedUrls.delete(item.sourceUrl);
  });
  renderResults(visibleItems, { selectable: true });
  updateSelectionStatus();
}

function toggleSavedSelection() {
  if (!savedItems.length) return;
  const shouldSelect = selectedSlugs.size !== savedItems.length;
  selectedSlugs.clear();
  if (shouldSelect) savedItems.forEach(item => selectedSlugs.add(item.slug));
  renderSavedList();
}

async function deleteSelectedComics() {
  if (!selectedSlugs.size) {
    setStatus("Pilih komik yang ingin dihapus dari library.", "error");
    return;
  }
  await deleteComics([...selectedSlugs]);
}

async function deleteComics(slugs) {
  const names = slugs.map(slug => savedItems.find(item => item.slug === slug)?.title || slug);
  if (!confirm(`Hapus ${names.length} komik dari library dan asset lokalnya?`)) return;

  setStatus("Menghapus data tersimpan...", "running");
  els.deleteSelected.disabled = true;
  try {
    const response = await fetch("/api/delete-comics", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({ slugs, deleteAssets: true })
    });
    const data = await readApiJson(response, "Gagal membaca respons hapus data.");
    if (!response.ok || !data.ok) throw new Error(data.error || "Gagal menghapus data.");

    selectedSlugs.clear();
    await refreshLibrary();
    sourceCache.clear();
    setStatus(`${data.deleted} komik dihapus. Total tersimpan sekarang ${data.totalSaved}.`, "");
  } catch (error) {
    setStatus(error.message, "error");
  } finally {
    els.deleteSelected.disabled = false;
  }
}

async function bulkUpdateSelected() {
  if (!selectedSlugs.size) {
    setStatus("Pilih komik untuk bulk update.", "error");
    return;
  }
  const response = await fetch("/api/bulk-update-comics", {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({
      slugs: [...selectedSlugs],
      type: els.bulkType.value,
      status: els.bulkStatus.value,
      genres: els.bulkGenres.value
    })
  });
  const data = await readApiJson(response, "Gagal membaca respons bulk update.");
  if (!response.ok || !data.ok) {
    setStatus(data.error || "Bulk update gagal.", "error");
    return;
  }
  await refreshLibrary();
  setStatus(`${data.updated} komik berhasil diupdate.`, "");
}

async function rebuildThumbnails(slugs = [...selectedSlugs]) {
  setStatus("Rebuild thumbnail berjalan...", "running");
  const response = await fetch("/api/rebuild-thumbnails", {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({ slugs })
  });
  const data = await readApiJson(response, "Gagal membaca respons thumbnail.");
  if (!response.ok || !data.ok) {
    setStatus(data.error || "Rebuild thumbnail gagal.", "error");
    return;
  }
  if (els.toolOutput) els.toolOutput.innerHTML = `<div class="empty-state">${data.thumbnails} thumbnail chapter dibuat ulang.</div>`;
  await refreshLibrary();
  setStatus(`${data.thumbnails} thumbnail dibuat ulang.`, "");
}

async function pinHomepageSlug(target, slug) {
  if (!slug) return;
  const key = target === "hero" ? "heroSlugs" : "recommendSlugs";
  const modeKey = target === "hero" ? "heroMode" : "recommendMode";
  const existing = Array.isArray(settingsCache[key]) ? settingsCache[key] : [];
  const next = [slug, ...existing.filter(item => item !== slug)].slice(0, target === "hero" ? 7 : 6);
  await saveSettingsPayload({ [modeKey]: "manual", [key]: next }, `${target === "hero" ? "Hero" : "Rekomendasi"} dipin.`);
  if (target === "hero" && els.heroSlugs) {
    els.heroMode.value = "manual";
    els.heroSlugs.value = next.join(", ");
  }
  if (target === "recommend" && els.recommendSlugs) {
    els.recommendMode.value = "manual";
    els.recommendSlugs.value = next.join(", ");
  }
}

async function scanBrokenImages() {
  setStatus("Scanning broken image...", "running");
  const response = await fetch("/api/scan-broken-images");
  const data = await readApiJson(response, "Gagal membaca respons scan image.");
  if (!response.ok || !data.ok) {
    setStatus(data.error || "Scan gagal.", "error");
    return;
  }
  if (els.toolOutput) {
    els.toolOutput.innerHTML = `
      <div class="empty-state">Dicek ${data.checked} file. Broken: ${data.brokenCount}.</div>
      <div class="log-list">${(data.broken || []).slice(0, 60).map(item => `<article><strong>${escapeHtml(item.type)}: ${escapeHtml(item.title)}</strong><small>${escapeHtml(item.path)}</small></article>`).join("")}</div>
    `;
  }
  setStatus(data.brokenCount ? `${data.brokenCount} broken image ditemukan.` : "Semua image lokal aman.", data.brokenCount ? "error" : "");
}

async function loadScrapeLogs() {
  if (!els.scrapeLogList) return;
  const response = await fetch("/api/scrape-logs");
  const data = await readApiJson(response, "Gagal membaca log scrape.");
  const logs = data.logs || [];
  els.scrapeLogList.innerHTML = logs.length ? logs.map(log => `
    <article>
      <strong>${escapeHtml(log.status)} · ${escapeHtml(log.message)}</strong>
      <small>${new Date(log.startedAt).toLocaleString()} · ${log.doneChapters || 0}/${log.totalChapters || 0} chapter · ${log.doneImages || 0} image · ${(log.errors || []).length} error</small>
      ${(log.errors || []).slice(0, 3).map(error => `<small class="error-line">${escapeHtml(error)}</small>`).join("")}
    </article>
  `).join("") : `<div class="empty-state">Belum ada log scrape.</div>`;
}

async function updateSlug() {
  const response = await fetch("/api/update-slug", {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({ from: els.oldSlug.value, to: els.newSlug.value })
  });
  const data = await readApiJson(response, "Gagal membaca respons update slug.");
  if (!response.ok || !data.ok) {
    setStatus(data.error || "Update slug gagal.", "error");
    return;
  }
  els.oldSlug.value = "";
  els.newSlug.value = "";
  await refreshLibrary();
  setStatus(`Slug ${data.from} diarahkan ke ${data.to}.`, "");
}

async function resetPopularManual() {
  await saveSettingsPayload({ popularMode: "auto", popularSlugs: [] }, "Populer kembali ke mode auto.");
}

function updateSelectionStatus() {
  setStatus(`${selectedUrls.size} judul baru dipilih.`, "");
}

function syncTitle() {
  const isPopular = els.mode.value === "popular";
  const modeText = isPopular ? "POPULAR GLOBAL" : els.mode.value.toUpperCase();
  els.modeTitle.textContent = `JUDUL BARU / ${modeText}`;
  els.workflowButtons.forEach(button => button.classList.toggle("active", button.dataset.workflow === workflow));
  els.load.textContent = "Muat Daftar";
  els.scrape.textContent = "Scrape Terpilih";
  els.selectAll.hidden = false;
  if (els.popularRange) els.popularRange.disabled = isPopular;
  if (els.popularRangeHint) {
    els.popularRangeHint.textContent = isPopular
      ? "Komiktap archive hanya menyediakan popular global."
      : "Dipakai hanya jika source mendukung rentang popular.";
  }
}

function renderBannerManager(banners = []) {
  if (!els.bannerManager) return;
  const rows = banners.length ? banners : [{ slot: "home-top", label: "Banner Home Top", imageUrl: "", targetUrl: "", enabled: true }];
  els.bannerManager.innerHTML = rows.map((banner, index) => bannerRow(banner, index)).join("");
  els.bannerManager.querySelectorAll("[data-remove-banner]").forEach(button => {
    button.addEventListener("click", () => {
      button.closest(".banner-row")?.remove();
    });
  });
}

function bannerRow(banner, index) {
  const selectedSlot = bannerSlots.find(slot => slot.value === banner.slot) || bannerSlots[index % bannerSlots.length] || bannerSlots[0];
  const options = bannerSlots.map(slot => `<option value="${escapeHtml(slot.value)}" ${slot.value === selectedSlot.value ? "selected" : ""}>${escapeHtml(slot.label)}</option>`).join("");
  return `
    <div class="banner-row">
      <label>
        <strong>Posisi</strong>
        <select data-banner-field="slot">${options}</select>
        <small>${escapeHtml(selectedSlot.note)} Ukuran: ${escapeHtml(selectedSlot.size)}.</small>
      </label>
      <label>
        <strong>Nama internal</strong>
        <input data-banner-field="label" value="${escapeHtml(banner.label || selectedSlot.label)}" placeholder="Contoh: Banner Home Top">
      </label>
      <label class="banner-url-field">
        <strong>Gambar banner</strong>
        <input data-banner-field="imageUrl" value="${escapeHtml(banner.imageUrl || "")}" placeholder="/assets/banners/home-top.webp">
      </label>
      <label class="banner-url-field">
        <strong>Link tujuan</strong>
        <input data-banner-field="targetUrl" value="${escapeHtml(banner.targetUrl || "")}" placeholder="https://...">
      </label>
      <label class="check-line banner-active"><input data-banner-field="enabled" type="checkbox" ${banner.enabled !== false ? "checked" : ""}> Aktif</label>
      <button class="danger small" data-remove-banner type="button">Hapus</button>
    </div>
  `;
}

function addBannerRow() {
  els.bannerManager?.insertAdjacentHTML("beforeend", bannerRow({ enabled: true }, els.bannerManager.children.length));
}

function readBannerRows() {
  return [...(els.bannerManager?.querySelectorAll(".banner-row") || [])].map(row => ({
    slot: row.querySelector('[data-banner-field="slot"]')?.value || "",
    label: row.querySelector('[data-banner-field="label"]')?.value || "",
    imageUrl: row.querySelector('[data-banner-field="imageUrl"]')?.value || "",
    targetUrl: row.querySelector('[data-banner-field="targetUrl"]')?.value || "",
    enabled: row.querySelector('[data-banner-field="enabled"]')?.checked !== false
  }));
}

function splitSlugs(value) {
  return String(value || "").split(/[\s,]+/).map(item => item.trim()).filter(Boolean);
}

function setStatus(text, state) {
  els.status.textContent = text;
  els.status.className = `scrape-status ${state || ""}`.trim();
}

function stars(rating) {
  const full = Math.max(0, Math.min(5, Math.round(Number(rating || 0) / 2)));
  return "★★★★★".slice(0, full) + "☆☆☆☆☆".slice(0, 5 - full);
}

function typeLabel() {
  const labels = { all: "judul", manhwa: "manhwa", manga: "manga", manhua: "manhua" };
  return labels[els.comicType.value] || "judul";
}

function filterLabel(filters = basePayload()) {
  const type = { all: "Semua", manhwa: "Manhwa", manga: "Manga", manhua: "Manhua" }[filters.comicType] || "Semua";
  const mode = { update: "Update", popular: "Popular", latest: "Latest" }[filters.mode] || "Update";
  return mode === "Popular" ? `${type} / Popular Global` : `${type} / ${mode}`;
}

function typeBadge(type) {
  if (/manhua/i.test(type || "")) return "Manhua";
  if (/manga/i.test(type || "")) return "Manga";
  return "Manhwa";
}

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
