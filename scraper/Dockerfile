# Официальный образ Playwright уже содержит Chromium и все нужные ему системные библиотеки.
FROM mcr.microsoft.com/playwright:v1.47.2-jammy

WORKDIR /app

COPY scraper/package.json scraper/package-lock.json* ./
RUN npm install --omit=dev

COPY scraper/ ./

ENV NODE_ENV=production
ENV PORT=3000
EXPOSE 3000

CMD ["node", "src/index.js"]
