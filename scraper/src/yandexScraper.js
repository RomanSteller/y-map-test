import { getBrowser, newContext } from './browser.js';

/**
 * Scrape one Yandex.Maps organisation.
 *
 * Strategy (see README for the full rationale):
 *   1. Open the organisation's /reviews/ page in a real headless browser.
 *   2. Read the server-rendered app state embedded as <script class="state-view">.
 *      That already gives us the org meta (rating, both counters) plus the
 *      first ~50 reviews — with zero anti-bot friction.
 *   3. Register a response listener for the page's own `fetchReviews` XHRs.
 *      Those requests are signed in-page (the `s=` / csrfToken parameters), so
 *      by letting the page make them and just harvesting the JSON we never have
 *      to reproduce Yandex's signature ourselves.
 *   4. Scroll the reviews list to trigger lazy-loading until we have them all
 *      (or hit maxReviews), reporting progress as we go.
 *
 * Yielded events: {type:'progress',progress,message} … then one
 * {type:'result',data} or {type:'error',reason,message}.
 */
export async function* scrapeOrganization({ url, maxReviews = 600 }) {
  const reviewsUrl = ensureReviewsPath(url);
  const browser = await getBrowser();
  const context = await newContext(browser);
  const page = await context.newPage();

  // Collect reviews from the page's own signed XHRs, keyed by id (dedup).
  const collected = new Map();
  page.on('response', async (response) => {
    const u = response.url();
    if (!u.includes('fetchReviews')) return;
    try {
      const json = await response.json();
      // A successful page fetch wraps them as {data:{reviews:[…]}}; the
      // server-side render / other calls use {reviews:[…]}. CSRF-rotation
      // responses ({csrfToken}) simply have neither.
      const list = json?.data?.reviews ?? json?.reviews ?? [];
      for (const r of list) addReview(collected, r);
    } catch {
      /* non-JSON / rotation response — ignore */
    }
  });

  try {
    yield progress(3, 'Открываю карточку организации…');

    const resp = await page.goto(reviewsUrl, {
      waitUntil: 'domcontentloaded',
      timeout: 45000,
    });

    if (resp && (resp.status() === 429 || resp.status() === 403)) {
      yield error('blocked', `Яндекс ответил HTTP ${resp.status()} — вероятно, антибот.`);
      return;
    }

    if (await looksLikeCaptcha(page)) {
      yield error('blocked', 'Показана капча Яндекса.');
      return;
    }

    yield progress(10, 'Читаю данные страницы…');

    const state = await readStateView(page);
    if (!state) {
      yield error('markup_changed', 'Не найден встроенный state (script.state-view) — разметка изменилась.');
      return;
    }

    const org = extractOrg(state);
    if (!org) {
      yield error('markup_changed', 'Не удалось найти карточку организации в state.');
      return;
    }

    // Seed with the server-rendered first page.
    for (const r of extractEmbeddedReviews(state)) addReview(collected, r);

    const target = Math.min(org.reviews_count || collected.size, maxReviews);
    yield progress(15, `Найдено ${collected.size} из ~${target} отзывов. Подгружаю остальные…`);

    // Park the mouse over the reviews list so real wheel events land there —
    // that, not a programmatic scrollTop, is what triggers Yandex's lazy-load.
    await positionOverReviews(page);

    // Lazy-load the rest by scrolling the reviews container.
    let stagnant = 0;
    for (let i = 0; i < 200 && collected.size < target; i++) {
      const before = collected.size;
      await scrollReviews(page);
      await page.waitForTimeout(700 + Math.floor(Math.random() * 500)); // polite jitter

      if (await looksLikeCaptcha(page)) {
        yield error('blocked', 'Капча появилась во время подгрузки отзывов.');
        return;
      }

      if (collected.size === before) {
        if (++stagnant >= 6) break; // no more loading — probably reached the end
      } else {
        stagnant = 0;
        const pct = Math.min(95, 15 + Math.round((collected.size / target) * 80));
        yield progress(pct, `Загружено ${collected.size} из ~${target} отзывов…`);
      }
    }

    const reviews = [...collected.values()].slice(0, maxReviews);

    if ((org.reviews_count ?? 0) > 0 && reviews.length === 0) {
      yield error('empty_result', `Счётчик показывает ${org.reviews_count} отзывов, но получить не удалось ни одного.`);
      return;
    }

    yield progress(100, 'Готово.');
    yield {
      type: 'result',
      data: { organization: { ...org, url: reviewsUrl }, reviews },
    };
  } catch (e) {
    yield error('source_unavailable', `Ошибка при загрузке страницы: ${e.message}`);
  } finally {
    await context.close();
  }
}

/* ----------------------------- helpers ----------------------------- */

function progress(p, message) {
  return { type: 'progress', progress: p, message };
}
function error(reason, message) {
  return { type: 'error', reason, message };
}

function ensureReviewsPath(url) {
  const clean = url.split('?')[0].replace(/\/+$/, '');
  return clean.endsWith('/reviews') ? clean + '/' : clean + '/reviews/';
}

async function looksLikeCaptcha(page) {
  const url = page.url();
  if (/\/showcaptcha|captcha/i.test(url)) return true;
  return page
    .locator('form[action*="checkcaptcha"], .CheckboxCaptcha, .AdvancedCaptcha')
    .first()
    .isVisible()
    .catch(() => false);
}

/** Read and JSON-parse the embedded <script class="state-view"> blob. */
async function readStateView(page) {
  const raw = await page
    .locator('script.state-view')
    .first()
    .textContent({ timeout: 15000 })
    .catch(() => null);
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

/** Walk the state tree to find the org card carrying ratingData + title. */
function extractOrg(state) {
  let found = null;
  const visit = (node) => {
    if (found || !node || typeof node !== 'object') return;
    if (node.ratingData && (node.title || node.name) && (node.id || node.seoname)) {
      const rd = node.ratingData;
      found = {
        yandex_id: String(node.id),
        title: node.title ?? node.name ?? null,
        address: node.fullAddress ?? node.address ?? null,
        categories: (node.categories ?? []).map((c) => (typeof c === 'object' ? c.name : c)),
        rating: rd.ratingValue != null ? Number(rd.ratingValue) : null,
        ratings_count: Number(rd.ratingCount ?? 0),
        reviews_count: Number(rd.reviewCount ?? 0),
      };
      return;
    }
    for (const v of Array.isArray(node) ? node : Object.values(node)) visit(v);
  };
  visit(state);
  return found;
}

/** Pull the first-page reviews Yandex server-renders into the state. */
function extractEmbeddedReviews(state) {
  let reviews = [];
  const visit = (node) => {
    if (!node || typeof node !== 'object') return;
    if (node.reviewResults && Array.isArray(node.reviewResults.reviews)) {
      reviews = node.reviewResults.reviews;
      return;
    }
    for (const v of Array.isArray(node) ? node : Object.values(node)) visit(v);
  };
  visit(state);
  return reviews;
}

function addReview(map, r) {
  const id = r?.reviewId ?? r?.id;
  if (!id || map.has(id)) return;
  map.set(id, {
    external_id: String(id),
    author: r?.author?.name ?? null,
    rating: r?.rating != null ? Number(r.rating) : null,
    text: typeof r?.text === 'string' ? r.text.trim() : null,
    reviewed_at: r?.updatedTime ?? r?.time ?? null,
  });
}

const REVIEWS_LIST_SELECTORS = [
  '.business-reviews-card-view__reviews-container',
  '.scroll__container',
];

/** Move the mouse over the reviews list so wheel events are delivered to it. */
async function positionOverReviews(page) {
  for (const sel of REVIEWS_LIST_SELECTORS) {
    const box = await page.locator(sel).first().boundingBox().catch(() => null);
    if (box) {
      await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
      return;
    }
  }
}

/**
 * Trigger lazy-loading. A real wheel event is what Yandex's infinite scroll
 * listens for; a programmatic scrollTop alone does not fire it. We send the
 * wheel and also nudge scrollTop as a belt-and-braces fallback.
 */
async function scrollReviews(page) {
  await page.mouse.wheel(0, 3500);
  await page.evaluate((selectors) => {
    for (const sel of selectors) {
      const node = document.querySelector(sel);
      if (node && node.scrollHeight > node.clientHeight) {
        node.scrollTop = node.scrollHeight;
        return;
      }
    }
    document.scrollingElement.scrollBy(0, 3000);
  }, REVIEWS_LIST_SELECTORS);
}
