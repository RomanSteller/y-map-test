import express from 'express';
import { scrapeOrganization } from './yandexScraper.js';
import { closeBrowser } from './browser.js';

const app = express();
app.use(express.json());

const PORT = process.env.PORT || 3000;

app.get('/health', (_req, res) => res.json({ status: 'ok' }));

/**
 * POST /scrape { url, orgId?, maxReviews? }
 *
 * Стримит JSON построчно: пачку событий {type:"progress"}, а затем ровно одно
 * {type:"result"} или {type:"error"}. На той стороне HeadlessBrowserParser из
 * Laravel читает этот поток и прокидывает прогресс в очередь/интерфейс.
 */
app.post('/scrape', async (req, res) => {
  const { url, maxReviews } = req.body ?? {};

  if (!url || typeof url !== 'string') {
    return res.status(422).json({ type: 'error', reason: 'invalid_url', message: 'url is required' });
  }

  res.set({
    'Content-Type': 'application/x-ndjson',
    'Cache-Control': 'no-cache',
    'X-Accel-Buffering': 'no',
  });

  const write = (event) => res.write(JSON.stringify(event) + '\n');

  try {
    for await (const event of scrapeOrganization({ url, maxReviews: Number(maxReviews) || 600 })) {
      write(event);
    }
  } catch (e) {
    write({ type: 'error', reason: 'source_unavailable', message: e.message });
  } finally {
    res.end();
  }
});

const server = app.listen(PORT, () => {
  console.log(`[scraper] listening on :${PORT}`);
});

async function shutdown() {
  console.log('[scraper] shutting down…');
  server.close();
  await closeBrowser();
  process.exit(0);
}
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
