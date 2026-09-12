import { chromium } from 'playwright';

/**
 * A single shared Chromium instance, reused across requests. Launching a
 * browser per request would be slow and memory-heavy; a shared browser with a
 * fresh context per scrape gives isolation without the launch cost.
 */
let browserPromise = null;

export async function getBrowser() {
  if (!browserPromise) {
    browserPromise = chromium.launch({
      headless: true,
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        // Reduce the most obvious "I am automated" signal.
        '--disable-blink-features=AutomationControlled',
      ],
    });
  }
  return browserPromise;
}

export async function closeBrowser() {
  if (browserPromise) {
    const b = await browserPromise;
    await b.close();
    browserPromise = null;
  }
}

/**
 * A context configured to look like an ordinary ru-locale desktop Chrome, with
 * a realistic UA and viewport. In production this is where proxy rotation and
 * a stealth plugin would plug in (see README → anti-ban).
 */
export async function newContext(browser) {
  const context = await browser.newContext({
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
    viewport: { width: 1366, height: 900 },
    userAgent:
      'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    extraHTTPHeaders: {
      'Accept-Language': 'ru-RU,ru;q=0.9,en;q=0.8',
    },
  });

  // Strip navigator.webdriver, the classic headless tell.
  await context.addInitScript(() => {
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
  });

  return context;
}
