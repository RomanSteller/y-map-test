import { chromium } from 'playwright';

/**
 * Один общий инстанс Chromium, переиспользуемый между запросами. Запускать
 * браузер на каждый запрос — медленно и прожорливо по памяти; общий браузер
 * плюс свежий контекст на каждый скрап даёт изоляцию без затрат на запуск.
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
        // Убираем самый очевидный признак «я — автоматизация».
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
 * Контекст, настроенный так, чтобы выглядеть как обычный десктопный Chrome с
 * русской локалью — с правдоподобными UA и вьюпортом. На проде именно сюда
 * подключались бы ротация прокси и stealth-плагин (см. README → анти-бан).
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

  // Убираем navigator.webdriver — классический маркер headless-браузера.
  await context.addInitScript(() => {
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
  });

  return context;
}
