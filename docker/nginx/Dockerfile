# Этап 1 — собираем SPA на Vue.
FROM node:20-alpine AS build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm install
COPY frontend/ ./
RUN npm run build

# Этап 2 — nginx: отдаёт SPA и проксирует API на php-fpm.
FROM nginx:1.27-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
