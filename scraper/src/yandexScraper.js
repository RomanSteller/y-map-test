import { getBrowser, newContext } from './browser.js';

/**
 * Скрапит одну организацию с Яндекс.Карт.
 *
 * Стратегия (полное обоснование — в README):
 *   1. Открываем страницу /reviews/ организации в настоящем headless-браузере.
 *   2. Читаем состояние приложения, отрендеренное сервером в
 *      <script class="state-view">. Там уже есть мета организации (рейтинг, оба
 *      счётчика) плюс первые ~50 отзывов — и никакого антибота.
 *   3. Вешаем слушатель на XHR-ы `fetchReviews`, которые страница шлёт сама.
 *      Эти запросы подписаны прямо на странице (параметры `s=` / csrfToken),
 *      поэтому, позволяя странице делать их самой и просто собирая JSON, мы
 *      вообще не воспроизводим подпись Яндекса руками.
 *   4. Прокручиваем список отзывов, чтобы запустить подгрузку, пока не соберём
 *      все (или не упрёмся в maxReviews), попутно отдавая прогресс.
 *
 * Отдаёт события: {type:'progress',progress,message} … затем одно
 * {type:'result',data} или {type:'error',reason,message}.
 */
export async function* scrapeOrganization({ url, maxReviews = 600 }) {
  const reviewsUrl = ensureReviewsPath(url);
  const browser = await getBrowser();
  const context = await newContext(browser);
  const page = await context.newPage();

  // Собираем отзывы из подписанных XHR самой страницы, ключ — id (для дедупа).
  const collected = new Map();
  page.on('response', async (response) => {
    const u = response.url();
    if (!u.includes('fetchReviews')) return;
    try {
      const json = await response.json();
      // Успешный запрос страницы заворачивает их в {data:{reviews:[…]}};
      // серверный рендер / прочие вызовы — в {reviews:[…]}. А ответы с ротацией
      // CSRF ({csrfToken}) не содержат ни того, ни другого.
      const list = json?.data?.reviews ?? json?.reviews ?? [];
      for (const r of list) addReview(collected, r);
    } catch {
      /* не-JSON / ответ с ротацией токена — пропускаем */
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

    // Затравка — первая страница, отрендеренная сервером.
    for (const r of extractEmbeddedReviews(state)) addReview(collected, r);

    const target = Math.min(org.reviews_count || collected.size, maxReviews);
    yield progress(15, `Найдено ${collected.size} из ~${target} отзывов. Подгружаю остальные…`);

    // Ставим мышь над списком отзывов, чтобы настоящие события колеса прилетали
    // именно туда — подгрузку у Яндекса запускает это, а не программный scrollTop.
    await positionOverReviews(page);

    // Подгружаем остальное, прокручивая контейнер с отзывами.
    let stagnant = 0;
    for (let i = 0; i < 200 && collected.size < target; i++) {
      const before = collected.size;
      await scrollReviews(page);
      await page.waitForTimeout(700 + Math.floor(Math.random() * 500)); // вежливый разброс пауз

      if (await looksLikeCaptcha(page)) {
        yield error('blocked', 'Капча появилась во время подгрузки отзывов.');
        return;
      }

      if (collected.size === before) {
        if (++stagnant >= 6) break; // больше не грузится — видимо, дошли до конца
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

/* --------------------------- вспомогательное --------------------------- */

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

/** Читаем и парсим JSON из встроенного <script class="state-view">. */
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

/** Обходим дерево state в поисках карточки организации с ratingData + title. */
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

/** Достаём отзывы первой страницы, которые Яндекс кладёт в state на сервере. */
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

/** Наводим мышь на список отзывов, чтобы события колеса шли именно туда. */
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
 * Запускаем подгрузку. Бесконечная прокрутка Яндекса слушает именно настоящее
 * событие колеса; один программный scrollTop её не триггерит. Поэтому шлём
 * колесо и на всякий случай ещё и подталкиваем scrollTop.
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
