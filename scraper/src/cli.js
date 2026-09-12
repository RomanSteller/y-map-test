import { scrapeOrganization } from './yandexScraper.js';
import { closeBrowser } from './browser.js';

/**
 * Хелпер для ручной проверки:
 *   node src/cli.js "https://yandex.ru/maps/org/twins_garden/192990200894/" 120
 * Прогресс печатает в stderr, финальный JSON — в stdout.
 */
const url = process.argv[2];
const maxReviews = Number(process.argv[3]) || 200;

if (!url) {
  console.error('usage: node src/cli.js <yandex-maps-org-url> [maxReviews]');
  process.exit(1);
}

const run = async () => {
  for await (const event of scrapeOrganization({ url, maxReviews })) {
    if (event.type === 'result') {
      const { organization, reviews } = event.data;
      console.error(
        `\nDONE: ${organization.title} — rating ${organization.rating}, ` +
          `${organization.ratings_count} ratings, ${organization.reviews_count} reviews; ` +
          `scraped ${reviews.length}`
      );
      process.stdout.write(JSON.stringify(event.data, null, 2));
    } else if (event.type === 'error') {
      console.error(`ERROR [${event.reason}]: ${event.message}`);
      process.exitCode = 1;
    } else {
      console.error(`  ${event.progress}% ${event.message}`);
    }
  }
  await closeBrowser();
};

run();
