# MVP «Форум × Telegram»

Гибрид: читаем темы на сайте (SEO/структура), пишем в Telegram (скорость и привычность).
В каталоге `apps/web` — Next.js (App Router, TS) с API‑роутами и SSR‑страницами.

## Быстрый старт
1. Установите Node 18+ и pnpm или npm.
2. Скопируйте `.env.example` в `.env` и заполните значения.
3. Создайте БД Postgres и примените `schema.sql`.
4. Поставьте вебхук боту: `https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://<домен>/api/telegram/webhook`.
5. Запустите:
   ```bash
   cd apps/web
   pnpm i
   pnpm dev
   # или npm install && npm run dev
   ```

## Структура
- `apps/web/app/api/telegram/webhook/route.ts` — приём апдейтов бота, запись постов
- `apps/web/app/api/topics/route.ts` — список тем (JSON)
- `apps/web/app/api/topic/[id]/route.ts` — тема и посты (JSON)
- `apps/web/app/api/mini/validate/route.ts` — проверка initData из Mini App
- `apps/web/app/(site)/page.tsx` — список последних тем
- `apps/web/app/(site)/topic/[id]/page.tsx` — лента темы + кнопка «Ответить в Telegram»
- `apps/web/lib/*` — БД, утилиты ссылок и Bot API

## Примечания
- Для приватных групп используйте ссылки `t.me/c/<abs_chat_id>/<topicId>`.
- Для публичных групп — `t.me/<username>/<topicId>`.
- Минимум персональных данных: отображаем `author_display`, храним tg_user_id только при необходимости.
