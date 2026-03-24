const EXTENSION_VERSION = "2.5.0";
const UPDATE_CHECK_URL = null;
const UPDATE_CHECK_INTERVAL_HOURS = 6;

const PLATFORMS = {
  1: { domain: ".netflix.com",     url: "https://www.netflix.com/" },
  2: { domain: ".spotify.com",     url: "https://open.spotify.com/" },
  3: { domain: ".disneyplus.com",  url: "https://www.disneyplus.com/" },
  4: { domain: ".openai.com",      url: "https://chat.openai.com/" },
  5: { domain: ".canva.com",       url: "https://www.canva.com/" },
  6: { domain: ".udemy.com",       url: "https://www.udemy.com/" },
  7: { domain: ".coursera.org",    url: "https://www.coursera.org/" },
  8: { domain: ".skillshare.com",  url: "https://www.skillshare.com/" },
  9: { domain: ".grammarly.com",   url: "https://app.grammarly.com/" }
};

const LOGIN_VERIFICATION = {
  1: {
    successSelectors: ['.profile-icon', '[data-uia="profile-link"]', '.avatar-wrapper', '.account-menu-item', '[aria-label="Account"]'],
    failSelectors: ['[data-uia="login-page-container"]', '.login-form', '[data-uia="login-submit-button"]'],
    failUrlPatterns: ['/login', '/Login']
  },
  2: {
    successSelectors: ['.Root__nav-bar', '[data-testid="user-widget-link"]', '.user-widget', '[data-testid="home-page"]'],
    failSelectors: ['[data-testid="login-button"]', '#login-username'],
    failUrlPatterns: ['/login']
  },
  3: {
    successSelectors: ['.profile-avatar', '[data-gv2containerkey="user-menu"]'],
    failSelectors: ['[data-testid="login-button"]'],
    failUrlPatterns: ['/login']
  },
  4: {
    successSelectors: ['[data-testid="profile-button"]', '.text-token-text-primary', 'nav', '#prompt-textarea', '[id="prompt-textarea"]'],
    failSelectors: ['[data-testid="login-button"]', '.auth0-lock'],
    failUrlPatterns: ['/auth/login']
  },
  5: {
    successSelectors: ['[data-testid="header-account-button"]', '.UmBLm', '._3bOaQ'],
    failSelectors: ['[data-testid="login-button"]'],
    failUrlPatterns: ['/login']
  },
  6: {
    successSelectors: ['.ud-header--instructor', '[data-purpose="header-profile"]', '.header--gap-button--'],
    failSelectors: ['[data-purpose="header-login"]', '[name="email"]'],
    failUrlPatterns: ['/join/login-popup']
  },
  7: {
    successSelectors: ['[data-e2e="header-user-dropdown"]', '.c-ph-avatar', '[data-testid="header-profile-avatar"]'],
    failSelectors: ['[data-e2e="header-login-button"]', '#email'],
    failUrlPatterns: ['/login']
  },
  8: {
    successSelectors: ['.user-avatar', '.header-profile', '.authenticated'],
    failSelectors: ['.login-form', '[href="/login"]'],
    failUrlPatterns: ['/login', '/signup']
  },
  9: {
    successSelectors: ['[data-aid="sidebar-main"]', '.sidebar_user_info', '.user-avatar'],
    failSelectors: ['[data-aid="login-button"]'],
    failUrlPatterns: ['/signin']
  }
};

const VERIFY_DELAY_MS = 4000;
const VERIFY_RETRY_DELAY_MS = 3000;
const VERIFY_MAX_RETRIES = 2;

const COOKIE_SHORT_EXPIRY_SECONDS = 3600;
const HEARTBEAT_INTERVAL_MINUTES = 1;

let injectedCookies = [];
let suppressCookieWatcher = false;

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.action === "login") {
    handleLogin(message)
      .then(r => sendResponse(r))
      .catch(e => sendResponse({ success: false, message: e.message }));
    return true;
  }

  if (message.action === "ping") {
    sendResponse({ success: true, status: "active", version: EXTENSION_VERSION });
    return false;
  }

  if (message.action === "force_sync") {
    chrome.storage.local.set({ heartbeatStatus: "syncing" });
    sendResponse({ success: true, message: "Sync requested. Content script will handle on next page load." });
    return false;
  }

  if (message.action === "heartbeat_result") {
    handleHeartbeatResult(message);
    return false;
  }

  if (message.action === "guard_log") {
    storeLog(message.level || "info", message.category || "guard", message.message, message.meta);
    return false;
  }

  if (message.action === "get_logs") {
    getStoredLogs(message.filter).then(logs => sendResponse({ logs }));
    return true;
  }

  if (message.action === "clear_logs") {
    chrome.storage.local.set({ clearorbit_logs: [] });
    sendResponse({ success: true });
    return false;
  }

  if (message.action === "set_debug_mode") {
    chrome.storage.local.set({ clearorbit_debug_mode: !!message.enabled });
    storeLog("info", "system", "Debug mode " + (message.enabled ? "enabled" : "disabled"));
    sendResponse({ success: true, enabled: !!message.enabled });
    return false;
  }

  if (message.action === "get_debug_mode") {
    chrome.storage.local.get(["clearorbit_debug_mode"], (data) => {
      sendResponse({ enabled: data.clearorbit_debug_mode === true });
    });
    return true;
  }

  if (message.action === "check_update") {
    checkForUpdates().then(result => sendResponse(result));
    return true;
  }

  if (message.action === "clear_injected_cookies") {
    purgeAllInjectedCookies()
      .then(() => {
        storeLog("info", "logout", "All injected cookies cleared on user logout");
        sendResponse({ success: true, message: "Injected cookies cleared." });
      })
      .catch(e => sendResponse({ success: false, message: e.message }));
    return true;
  }

  if (message.action === "CLEAR_ALL_COOKIES") {
    clearAllPlatformCookiesAndRefresh()
      .then(() => {
        sendResponse({ success: true, message: "All platform cookies cleared and tabs refreshed." });
      })
      .catch(e => sendResponse({ success: false, message: e.message }));
    return true;
  }

  if (message.action === "FORCE_BROWSER_LOGOUT") {
    forceBrowserLogout(message.platforms || [])
      .then(() => {
        sendResponse({ success: true, message: "Force browser logout complete." });
      })
      .catch(e => sendResponse({ success: false, message: e.message }));
    return true;
  }
});

chrome.runtime.onMessageExternal.addListener((message, sender, sendResponse) => {
  if (message.action === "login") {
    handleLogin(message)
      .then(r => sendResponse(r))
      .catch(e => sendResponse({ success: false, message: e.message }));
    return true;
  }

  if (message.action === "ping") {
    sendResponse({ success: true, status: "active", version: EXTENSION_VERSION });
    return false;
  }

  if (message.action === "CLEAR_ALL_COOKIES") {
    clearAllPlatformCookiesAndRefresh()
      .then(() => {
        sendResponse({ success: true, message: "All platform cookies cleared and tabs refreshed." });
      })
      .catch(e => sendResponse({ success: false, message: e.message }));
    return true;
  }

  if (message.action === "FORCE_BROWSER_LOGOUT") {
    forceBrowserLogout(message.platforms || [])
      .then(() => {
        sendResponse({ success: true, message: "Force browser logout complete." });
      })
      .catch(e => sendResponse({ success: false, message: e.message }));
    return true;
  }
});

const LOG_STORAGE_KEY = "clearorbit_logs";
const MAX_STORED_LOGS = 500;
let logBuffer = [];
let logFlushTimer = null;

function storeLog(level, category, message, meta) {
  const entry = { ts: Date.now(), time: new Date().toISOString(), level, cat: category, msg: message };
  if (meta) entry.meta = meta;
  logBuffer.push(entry);
  if (!logFlushTimer) {
    logFlushTimer = setTimeout(flushLogs, 2000);
  }
}

async function flushLogs() {
  logFlushTimer = null;
  if (logBuffer.length === 0) return;
  try {
    const data = await chrome.storage.local.get([LOG_STORAGE_KEY]);
    let logs = data[LOG_STORAGE_KEY] || [];
    logs = logs.concat(logBuffer);
    if (logs.length > MAX_STORED_LOGS) logs = logs.slice(logs.length - MAX_STORED_LOGS);
    await chrome.storage.local.set({ [LOG_STORAGE_KEY]: logs });
    logBuffer = [];
  } catch (e) {
    console.error("[ClearOrbit] Log flush failed", e);
  }
}

async function getStoredLogs(filter) {
  const data = await chrome.storage.local.get([LOG_STORAGE_KEY]);
  let logs = data[LOG_STORAGE_KEY] || [];
  if (filter) {
    if (filter.level) logs = logs.filter(l => l.level === filter.level);
    if (filter.category) logs = logs.filter(l => l.cat === filter.category);
    if (filter.since) logs = logs.filter(l => l.ts >= filter.since);
    if (filter.limit) logs = logs.slice(-filter.limit);
  }
  return logs;
}

async function checkForUpdates() {
  const result = { currentVersion: EXTENSION_VERSION, updateAvailable: false, latestVersion: EXTENSION_VERSION };
  if (!UPDATE_CHECK_URL) return result;
  try {
    const res = await fetch(UPDATE_CHECK_URL, { cache: "no-store" });
    const data = await res.json();
    if (data.version && data.version !== EXTENSION_VERSION) {
      result.latestVersion = data.version;
      result.updateAvailable = true;
      result.updateUrl = data.url || null;
      result.changelog = data.changelog || null;
      await chrome.storage.local.set({ clearorbit_update: result });
      storeLog("info", "update", "Update available: v" + data.version);
    }
  } catch (e) {
    storeLog("warn", "update", "Update check failed: " + e.message);
  }
  return result;
}

const ALLOWED_DOMAINS = new Set(
  Object.values(PLATFORMS).map(p => p.domain.replace(/^\./, ""))
);

function isAllowedRedirectUrl(url) {
  try {
    const hostname = new URL(url).hostname;
    for (const allowed of ALLOWED_DOMAINS) {
      if (hostname === allowed || hostname.endsWith("." + allowed)) return true;
    }
  } catch (e) {}
  return false;
}

function resolvePlatformTarget(platformId, serverDomain, serverRedirectUrl) {
  const pid = platformId ? parseInt(platformId) : null;
  const platform = pid ? PLATFORMS[pid] : null;

  if (serverRedirectUrl && serverRedirectUrl.startsWith("https://") && isAllowedRedirectUrl(serverRedirectUrl)) {
    return {
      domain: serverDomain || (platform ? platform.domain : null),
      url: serverRedirectUrl,
      source: "server"
    };
  }

  if (platform) {
    return {
      domain: platform.domain,
      url: platform.url,
      source: "local_map"
    };
  }

  return null;
}

async function clearDomainCookies(domain) {
  const cleanDomain = domain.replace(/^\./, "");
  let deletedCount = 0;

  try {
    const existing = await chrome.cookies.getAll({ domain: cleanDomain });
    console.log("[ClearOrbit] Clearing cookies for " + cleanDomain + ": " + existing.length + " found");
    storeLog("info", "cookie", "Clearing old cookies", { domain: cleanDomain, count: existing.length });

    for (const cookie of existing) {
      try {
        const cookieUrl = "https://" + cookie.domain.replace(/^\./, "") + cookie.path;
        await chrome.cookies.remove({ url: cookieUrl, name: cookie.name });
        deletedCount++;
      } catch (e) {
        console.warn("[ClearOrbit] Failed to delete cookie: " + cookie.name, e);
      }
    }

    const dotExisting = await chrome.cookies.getAll({ domain: "." + cleanDomain });
    for (const cookie of dotExisting) {
      const alreadyDeleted = existing.some(e => e.name === cookie.name && e.domain === cookie.domain);
      if (alreadyDeleted) continue;
      try {
        const cookieUrl = "https://" + cookie.domain.replace(/^\./, "") + cookie.path;
        await chrome.cookies.remove({ url: cookieUrl, name: cookie.name });
        deletedCount++;
      } catch (e) {}
    }
  } catch (e) {
    console.warn("[ClearOrbit] Error clearing cookies for " + cleanDomain, e);
    storeLog("error", "cookie", "Error clearing cookies", { domain: cleanDomain, error: e.message });
  }

  console.log("[ClearOrbit] Deleted " + deletedCount + " old cookies for " + cleanDomain);
  storeLog("info", "cookie", "Old cookies deleted", { domain: cleanDomain, deletedCount });
  return deletedCount;
}

async function handleLogin(message) {
  const { cookieString, cookies: jsonCookies, domain, platformName, platformId, redirectUrl: serverRedirectUrl } = message;

  console.log("[ClearOrbit] Login request — Platform:", platformName, "| ID:", platformId, "| Domain:", domain);
  storeLog("info", "login", "Login request received", { platformName, platformId, domain, serverRedirectUrl });

  if ((!cookieString && !jsonCookies) || !domain) {
    storeLog("error", "login", "Missing cookie data or domain", { platformId, domain });
    return { success: false, message: "Missing cookie data or domain." };
  }

  const target = resolvePlatformTarget(platformId, domain, serverRedirectUrl);
  if (!target) {
    storeLog("error", "login", "Could not resolve platform target", { platformId, domain });
    return { success: false, message: "Unknown platform. Cannot determine redirect URL." };
  }

  console.log("[ClearOrbit] Resolved target:", target.url, "(source:", target.source + ")");
  storeLog("info", "login", "Platform resolved", { url: target.url, source: target.source });

  const effectiveDomain = target.domain || domain;

  let cookies;
  if (Array.isArray(jsonCookies) && jsonCookies.length > 0) {
    cookies = jsonCookies.map(c => ({
      name: c.name,
      value: String(c.value ?? ""),
      domain: c.domain || effectiveDomain,
      path: c.path || "/",
      secure: c.secure !== undefined ? c.secure : true,
      httpOnly: c.httpOnly !== undefined ? c.httpOnly : true,
      sameSite: normalizeSameSite(c.sameSite),
      expirationDate: c.expirationDate || null
    })).filter(c => c.name);
  } else {
    cookies = parseCookieString(cookieString || "");
  }

  if (cookies.length === 0) {
    storeLog("error", "login", "No valid cookies parsed", { platformId });
    return { success: false, message: "No valid cookies found." };
  }

  console.log("[ClearOrbit] Cookies found:", cookies.length, "| Action: clear_and_inject");
  storeLog("info", "cookie", "Cookies parsed", { count: cookies.length, format: jsonCookies ? "json" : "plain" });

  suppressCookieWatcher = true;

  let deletedCount = 0;
  const results = [];

  try {
    deletedCount = await clearDomainCookies(effectiveDomain);

    injectedCookies = injectedCookies.filter(c => {
      const cDomain = (c.domain || "").replace(/^\./, "");
      const targetDomain = effectiveDomain.replace(/^\./, "");
      return !(cDomain === targetDomain || cDomain.endsWith("." + targetDomain));
    });

    console.log("[ClearOrbit] Injecting " + cookies.length + " new cookies for " + effectiveDomain);
    const shortExpiry = Math.floor(Date.now() / 1000) + COOKIE_SHORT_EXPIRY_SECONDS;

    for (const cookie of cookies) {
      try {
        const cookieDomain = cookie.domain || effectiveDomain;
        const url = "https://" + cookieDomain.replace(/^\./, "");
        const domainVal = cookieDomain.startsWith(".") ? cookieDomain : "." + cookieDomain;
        await chrome.cookies.set({
          url: url,
          domain: domainVal,
          path: cookie.path || "/",
          name: cookie.name,
          value: cookie.value,
          secure: cookie.secure !== undefined ? cookie.secure : true,
          httpOnly: cookie.httpOnly !== undefined ? cookie.httpOnly : true,
          sameSite: normalizeSameSite(cookie.sameSite),
          expirationDate: shortExpiry
        });

        injectedCookies.push({
          name: cookie.name,
          value: cookie.value,
          domain: cookieDomain,
          path: cookie.path || "/",
          secure: cookie.secure !== undefined ? cookie.secure : true,
          httpOnly: cookie.httpOnly !== undefined ? cookie.httpOnly : true,
          sameSite: normalizeSameSite(cookie.sameSite),
          url: url,
          platformId: platformId ? parseInt(platformId) : null
        });

        results.push({ name: cookie.name, status: "set" });
      } catch (err) {
        results.push({ name: cookie.name, status: "failed", error: err.message });
        storeLog("error", "cookie", "Failed to set cookie", { name: cookie.name, error: err.message });
      }
    }
  } finally {
    suppressCookieWatcher = false;
  }

  const successCount = results.filter(r => r.status === "set").length;
  const failCount = results.filter(r => r.status === "failed").length;

  console.log("[ClearOrbit] Result: " + successCount + " set, " + failCount + " failed, " + deletedCount + " old removed");
  storeLog("info", "login", "Injection complete", {
    platform: platformName || domain,
    set: successCount,
    failed: failCount,
    oldRemoved: deletedCount,
    redirectUrl: target.url
  });

  await chrome.storage.local.set({
    lastInjection: {
      platform: platformName || domain,
      platformId: platformId ? parseInt(platformId) : null,
      time: new Date().toISOString(),
      cookiesSet: successCount,
      cookiesFailed: failCount,
      oldCookiesRemoved: deletedCount,
      redirectUrl: target.url
    },
    injectedCookies: injectedCookies
  });

  let createdTabId = null;
  if (successCount > 0 && target.url) {
    const tab = await chrome.tabs.create({ url: target.url });
    createdTabId = tab.id;
  }

  startHeartbeat();

  const pid = platformId ? parseInt(platformId) : null;
  if (createdTabId && pid && LOGIN_VERIFICATION[pid]) {
    scheduleLoginVerification(createdTabId, pid, message.accountId || null, message.platformId || null);
  }

  return {
    success: successCount > 0 && failCount === 0,
    message: successCount + " cookie(s) set, " + failCount + " failed. " + deletedCount + " old removed.",
    redirectUrl: target.url
  };
}

function scheduleLoginVerification(tabId, platformId, accountId, serverPlatformId) {
  storeLog("info", "verify", "Scheduling login verification", { tabId, platformId, accountId, delay: VERIFY_DELAY_MS });

  setTimeout(() => {
    verifyLoginOnTab(tabId, platformId, accountId, serverPlatformId, 0);
  }, VERIFY_DELAY_MS);
}

async function verifyLoginOnTab(tabId, platformId, accountId, serverPlatformId, attempt) {
  const verification = LOGIN_VERIFICATION[platformId];
  if (!verification) {
    storeLog("warn", "verify", "No verification config for platform", { platformId });
    return;
  }

  try {
    const tab = await chrome.tabs.get(tabId);
    if (!tab || !tab.url) {
      storeLog("warn", "verify", "Tab not found or no URL", { tabId });
      return;
    }

    const currentUrl = tab.url;
    const pathname = new URL(currentUrl).pathname;

    if (verification.failUrlPatterns) {
      for (const pattern of verification.failUrlPatterns) {
        if (pathname.startsWith(pattern)) {
          storeLog("warn", "verify", "Login FAILED — redirected to login page", { platformId, url: currentUrl, pattern, attempt });
          console.log("[ClearOrbit] Login FAILED: URL matched fail pattern", pattern);
          reportVerificationResult(platformId, accountId, serverPlatformId, false, "Redirected to login page");
          return;
        }
      }
    }

    if (tab.status !== "complete") {
      if (attempt < VERIFY_MAX_RETRIES) {
        storeLog("info", "verify", "Page still loading, retrying", { tabId, attempt });
        setTimeout(() => verifyLoginOnTab(tabId, platformId, accountId, serverPlatformId, attempt + 1), VERIFY_RETRY_DELAY_MS);
        return;
      }
    }

    const results = await chrome.scripting.executeScript({
      target: { tabId: tabId },
      func: (successSels, failSels) => {
        for (const sel of failSels) {
          if (document.querySelector(sel)) {
            return { status: "fail", matchedSelector: sel, type: "fail_selector" };
          }
        }
        for (const sel of successSels) {
          if (document.querySelector(sel)) {
            return { status: "success", matchedSelector: sel, type: "success_selector" };
          }
        }
        return { status: "unknown", type: "no_match" };
      },
      args: [verification.successSelectors || [], verification.failSelectors || []]
    });

    const result = results && results[0] ? results[0].result : null;

    if (!result) {
      storeLog("warn", "verify", "No result from DOM check", { tabId, attempt });
      if (attempt < VERIFY_MAX_RETRIES) {
        setTimeout(() => verifyLoginOnTab(tabId, platformId, accountId, serverPlatformId, attempt + 1), VERIFY_RETRY_DELAY_MS);
      }
      return;
    }

    if (result.status === "success") {
      storeLog("info", "verify", "Login VERIFIED — success selector matched", { platformId, selector: result.matchedSelector, attempt });
      console.log("[ClearOrbit] Login VERIFIED: matched", result.matchedSelector);
      reportVerificationResult(platformId, accountId, serverPlatformId, true, "Login verified");
    } else if (result.status === "fail") {
      storeLog("warn", "verify", "Login FAILED — fail selector matched", { platformId, selector: result.matchedSelector, attempt });
      console.log("[ClearOrbit] Login FAILED: matched fail selector", result.matchedSelector);
      reportVerificationResult(platformId, accountId, serverPlatformId, false, "Login page detected");
    } else {
      if (attempt < VERIFY_MAX_RETRIES) {
        storeLog("info", "verify", "No definitive result, retrying", { platformId, attempt });
        setTimeout(() => verifyLoginOnTab(tabId, platformId, accountId, serverPlatformId, attempt + 1), VERIFY_RETRY_DELAY_MS);
      } else {
        storeLog("info", "verify", "Login status uncertain after max retries — assuming success", { platformId, attempt });
        reportVerificationResult(platformId, accountId, serverPlatformId, true, "Assumed success (no fail indicators)");
      }
    }
  } catch (e) {
    storeLog("error", "verify", "Verification error: " + e.message, { tabId, platformId, attempt });
    console.warn("[ClearOrbit] Verification error:", e.message);
    if (attempt < VERIFY_MAX_RETRIES) {
      setTimeout(() => verifyLoginOnTab(tabId, platformId, accountId, serverPlatformId, attempt + 1), VERIFY_RETRY_DELAY_MS);
    }
  }
}

async function reportVerificationResult(platformId, accountId, serverPlatformId, isSuccess, reason) {
  const status = isSuccess ? "success" : "fail";

  storeLog("info", "verify", "Reporting verification: " + status, { platformId, accountId, reason });

  await chrome.storage.local.set({
    lastVerification: {
      platformId: platformId,
      accountId: accountId,
      status: status,
      reason: reason,
      time: new Date().toISOString()
    }
  });

  const tabs = await chrome.tabs.query({});
  for (const tab of tabs) {
    try {
      if (tab.url && (tab.url.includes("replit.dev") || tab.url.includes("replit.app") || tab.url.includes("hostingersite.com") || tab.url.includes("hstgr.io"))) {
        chrome.tabs.sendMessage(tab.id, {
          action: "verification_result",
          platformId: serverPlatformId || platformId,
          accountId: accountId,
          status: status,
          reason: reason
        });
        break;
      }
    } catch (e) {}
  }
}

function normalizeSameSite(value) {
  if (value === null || value === undefined || value === "") return "unspecified";
  const v = String(value).toLowerCase();
  if (v === "no_restriction" || v === "none") return "no_restriction";
  if (v === "strict") return "strict";
  if (v === "unspecified") return "unspecified";
  return "lax";
}

function parseCookieString(raw) {
  const cookies = [];
  const pairs = raw.split(";").map(s => s.trim()).filter(Boolean);

  for (const pair of pairs) {
    const eqIndex = pair.indexOf("=");
    if (eqIndex < 1) continue;

    const name = pair.substring(0, eqIndex).trim();
    const value = pair.substring(eqIndex + 1).trim();

    const lowerName = name.toLowerCase();
    if (["path","domain","expires","max-age","samesite","secure","httponly"].includes(lowerName)) {
      continue;
    }

    cookies.push({
      name: name,
      value: value,
      httpOnly: true,
      sameSite: "lax"
    });
  }

  return cookies;
}

function startHeartbeat() {
  chrome.alarms.create("clearorbit_heartbeat", {
    periodInMinutes: HEARTBEAT_INTERVAL_MINUTES
  });
}

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === "clearorbit_heartbeat") {
    chrome.storage.local.set({
      lastHeartbeat: new Date().toISOString(),
      heartbeatStatus: "pending"
    });
  }
  if (alarm.name === "clearorbit_update_check") {
    checkForUpdates();
  }
});

async function handleHeartbeatResult(message) {
  if (!message.active) {
    await purgeAllInjectedCookies();
    chrome.alarms.clear("clearorbit_heartbeat");
  }

  await chrome.storage.local.set({
    heartbeatStatus: message.status || (message.active ? "active" : "inactive"),
    lastHeartbeat: new Date().toISOString()
  });
}

async function purgeAllInjectedCookies() {
  const stored = await chrome.storage.local.get("injectedCookies");
  const list = stored.injectedCookies || injectedCookies || [];

  suppressCookieWatcher = true;
  const domains = new Set();

  try {
    for (const c of list) {
      if (c.domain) domains.add(c.domain.replace(/^\./, ""));
      try {
        await chrome.cookies.remove({ url: c.url, name: c.name });
      } catch (e) {}
    }

    for (const domain of domains) {
      await clearDomainCookies(domain);
    }
  } finally {
    suppressCookieWatcher = false;
  }

  injectedCookies = [];
  await chrome.storage.local.set({ injectedCookies: [] });
  storeLog("info", "cookie", "Purged all injected cookies", { domains: Array.from(domains) });
  console.log("[ClearOrbit] Purged all injected cookies for " + domains.size + " domain(s)");
}

async function clearAllPlatformCookiesAndRefresh() {
  storeLog("info", "logout", "CLEAR_ALL_COOKIES: Starting full platform cookie cleanup");

  await purgeAllInjectedCookies();

  const allDomains = Object.values(PLATFORMS).map(p => p.domain);

  suppressCookieWatcher = true;
  let totalDeleted = 0;

  try {
    for (const domain of allDomains) {
      totalDeleted += await clearDomainCookies(domain);
    }
  } finally {
    suppressCookieWatcher = false;
  }

  storeLog("info", "logout", "CLEAR_ALL_COOKIES: Cookies removed", { totalDeleted, domains: allDomains.length });

  try {
    const platformUrls = Object.values(PLATFORMS).map(p => {
      const clean = p.domain.replace(/^\./, "");
      return clean;
    });

    const tabs = await chrome.tabs.query({});
    for (const tab of tabs) {
      if (!tab.url || !tab.id) continue;
      try {
        const hostname = new URL(tab.url).hostname;
        const isPlatformTab = platformUrls.some(d => hostname === d || hostname.endsWith("." + d));
        if (isPlatformTab) {
          await chrome.tabs.reload(tab.id);
        }
      } catch (e) {}
    }
  } catch (e) {
    storeLog("error", "logout", "Error refreshing tabs: " + e.message);
  }

  chrome.alarms.clear("clearorbit_heartbeat");

  await chrome.storage.local.set({
    injectedCookies: [],
    heartbeatStatus: "idle",
    lastInjection: null,
    lastVerification: null
  });

  storeLog("info", "logout", "CLEAR_ALL_COOKIES: Full cleanup complete", { totalDeleted });
  console.log("[ClearOrbit] Full logout cleanup complete — " + totalDeleted + " cookies removed");
}

async function forceBrowserLogout(platforms) {
  storeLog("info", "logout", "FORCE_BROWSER_LOGOUT: Starting targeted platform logout", { platforms });

  await purgeAllInjectedCookies();

  suppressCookieWatcher = true;
  let totalDeleted = 0;

  try {
    const allDomains = Object.values(PLATFORMS).map(p => p.domain);

    const targetDomains = platforms.length > 0
      ? platforms.map(d => "." + d.replace(/^\./, ""))
      : allDomains;

    for (const domain of targetDomains) {
      totalDeleted += await clearDomainCookies(domain);
    }
  } finally {
    suppressCookieWatcher = false;
  }

  storeLog("info", "logout", "FORCE_BROWSER_LOGOUT: Cookies removed", { totalDeleted, platformCount: platforms.length });

  try {
    const cleanDomains = platforms.length > 0
      ? platforms.map(d => d.replace(/^\./, ""))
      : Object.values(PLATFORMS).map(p => p.domain.replace(/^\./, ""));

    const tabs = await chrome.tabs.query({});
    let refreshedCount = 0;
    for (const tab of tabs) {
      if (!tab.url || !tab.id) continue;
      try {
        const hostname = new URL(tab.url).hostname;
        const isPlatformTab = cleanDomains.some(d => hostname === d || hostname.endsWith("." + d));
        if (isPlatformTab) {
          await chrome.tabs.reload(tab.id);
          refreshedCount++;
        }
      } catch (e) {}
    }
    storeLog("info", "logout", "FORCE_BROWSER_LOGOUT: Refreshed " + refreshedCount + " platform tab(s)");
  } catch (e) {
    storeLog("error", "logout", "FORCE_BROWSER_LOGOUT: Error refreshing tabs: " + e.message);
  }

  chrome.alarms.clear("clearorbit_heartbeat");

  await chrome.storage.local.set({
    injectedCookies: [],
    heartbeatStatus: "idle",
    lastInjection: null,
    lastVerification: null
  });

  storeLog("info", "logout", "FORCE_BROWSER_LOGOUT: Complete", { totalDeleted });
  console.log("[ClearOrbit] FORCE_BROWSER_LOGOUT complete — " + totalDeleted + " cookies removed");
}

const BLOCKED_PATHS = [
  '/account', '/settings', '/billing', '/password',
  '/YourAccount', '/account-settings', '/profile/edit',
  '/delete-account', '/cancel', '/subscription',
  '/premium', '/redeem'
];

const LOGOUT_PATTERNS = [
  '/logout', '/signout', '/sign-out', '/log-out',
  '/api/logout', '/auth/logout', '/session/end'
];

function getDomainFromPlatforms() {
  const domains = {};
  for (const [id, p] of Object.entries(PLATFORMS)) {
    domains[p.domain.replace(/^\./, '')] = parseInt(id);
  }
  return domains;
}

function matchesPlatformDomain(url) {
  try {
    const hostname = new URL(url).hostname;
    const domainMap = getDomainFromPlatforms();
    for (const [domain, platformId] of Object.entries(domainMap)) {
      if (hostname === domain || hostname.endsWith('.' + domain) || hostname.endsWith(domain)) {
        return { platformId, domain };
      }
    }
  } catch (e) {}
  return null;
}

chrome.webNavigation.onBeforeNavigate.addListener((details) => {
  if (details.frameId !== 0) return;

  const url = details.url.toLowerCase();
  const match = matchesPlatformDomain(details.url);
  if (!match) return;

  const pathname = new URL(url).pathname;

  for (const blocked of BLOCKED_PATHS) {
    if (pathname.startsWith(blocked.toLowerCase())) {
      storeLog("warn", "guard", "Blocked path access", { url: details.url, blocked });
      chrome.tabs.update(details.tabId, { url: PLATFORMS[match.platformId].url });
      return;
    }
  }

  for (const logoutPath of LOGOUT_PATTERNS) {
    if (pathname.startsWith(logoutPath.toLowerCase())) {
      storeLog("warn", "guard", "Logout attempt blocked", { url: details.url });
      chrome.tabs.update(details.tabId, { url: PLATFORMS[match.platformId].url });
      reInjectCookiesForPlatform(match.platformId);
      return;
    }
  }
});

chrome.cookies.onChanged.addListener((changeInfo) => {
  if (suppressCookieWatcher) return;
  if (!changeInfo.removed) return;
  if (changeInfo.cause === 'overwrite') return;

  const cookie = changeInfo.cookie;
  const tracked = injectedCookies.find(c => c.name === cookie.name && cookie.domain.includes(c.domain.replace(/^\./, '')));
  if (tracked) {
    storeLog("info", "cookie", "Tracked cookie removed, re-injecting", { name: cookie.name, domain: cookie.domain, cause: changeInfo.cause });
    reInjectCookiesForDomain(cookie.domain);
  }
});

async function reInjectCookiesForPlatform(platformId) {
  const stored = await chrome.storage.local.get(['lastInjection', 'injectedCookies']);
  if (!stored.injectedCookies || stored.injectedCookies.length === 0) return;

  const platform = PLATFORMS[platformId];
  if (!platform) return;

  const shortExpiry = Math.floor(Date.now() / 1000) + COOKIE_SHORT_EXPIRY_SECONDS;
  const relevantCookies = stored.injectedCookies.filter(c =>
    c.domain && (c.domain.includes(platform.domain.replace(/^\./, '')))
  );

  storeLog("info", "cookie", "Re-injecting cookies", { platformId, count: relevantCookies.length });

  suppressCookieWatcher = true;
  try {
    for (const c of relevantCookies) {
      try {
        await chrome.cookies.set({
          url: c.url,
          name: c.name,
          value: c.value || '',
          domain: c.domain.startsWith('.') ? c.domain : '.' + c.domain,
          path: c.path || '/',
          secure: c.secure !== undefined ? c.secure : true,
          httpOnly: c.httpOnly !== undefined ? c.httpOnly : true,
          sameSite: c.sameSite || 'lax',
          expirationDate: shortExpiry
        });
      } catch (e) {}
    }
  } finally {
    suppressCookieWatcher = false;
  }
}

async function reInjectCookiesForDomain(domain) {
  const domainClean = domain.replace(/^\./, '');
  for (const [id, p] of Object.entries(PLATFORMS)) {
    if (domainClean.includes(p.domain.replace(/^\./, ''))) {
      await reInjectCookiesForPlatform(parseInt(id));
      return;
    }
  }
}

chrome.runtime.onInstalled.addListener((details) => {
  chrome.storage.local.set({
    injectedCookies: [],
    heartbeatStatus: "idle",
    lastHeartbeat: null,
    clearorbit_version: EXTENSION_VERSION
  });

  if (details.reason === "install") {
    storeLog("info", "system", "Extension installed v" + EXTENSION_VERSION);
  } else if (details.reason === "update") {
    storeLog("info", "system", "Extension updated to v" + EXTENSION_VERSION, { previousVersion: details.previousVersion });
  }

  chrome.alarms.create("clearorbit_update_check", {
    periodInMinutes: UPDATE_CHECK_INTERVAL_HOURS * 60
  });
});
