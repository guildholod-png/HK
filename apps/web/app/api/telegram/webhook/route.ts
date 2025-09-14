export const runtime = 'nodejs';
export const dynamic = 'force-dynamic';

import { NextRequest, NextResponse } from 'next/server';
import { q } from '@/lib/db';

type Update = {
    message?: any;
    edited_message?: any;
};

export async function POST(req: NextRequest) {
    const update = (await req.json()) as Update;
    const msg = update.message ?? update.edited_message;
    if (!msg) return NextResponse.json({ ok: true }); // ничего интересного

    // Рабочие поля
    const chatId: number | undefined = msg.chat?.id;               // -100...
    const threadId: number | undefined = msg.message_thread_id;     // topic id в группе
    const createdAt: string | undefined = msg.date
        ? new Date(msg.date * 1000).toISOString()
        : undefined;

    if (!chatId || !threadId) {
        return NextResponse.json({ ok: true }); // не топик, игнорируем
    }

    // 1) Создание/переименование топика — сохраняем ЧЕЛОВЕЧЕСКОЕ имя
    if (msg.forum_topic_created?.name) {
        const title = String(msg.forum_topic_created.name);
        await q(
            `INSERT INTO topics (id, section_id, tg_chat_id, tg_thread_id, title, updated_at)
       VALUES (
         (SELECT COALESCE(MAX(id),0)+1 FROM topics),
         1, $1, $2, $3, NOW()
       )
       ON CONFLICT (tg_chat_id, tg_thread_id)
       DO UPDATE SET title = EXCLUDED.title, updated_at = NOW()`,
            [chatId, threadId, title]
        );
        return NextResponse.json({ ok: true, event: 'forum_topic_created' });
    }

    if (msg.forum_topic_edited?.name) {
        const title = String(msg.forum_topic_edited.name);
        await q(
            `UPDATE topics
         SET title = $3, updated_at = NOW()
       WHERE tg_chat_id = $1 AND tg_thread_id = $2`,
            [chatId, threadId, title]
        );
        return NextResponse.json({ ok: true, event: 'forum_topic_edited' });
    }

    // 2) Обычное сообщение внутри топика — убеждаемся, что топик есть; если не было заголовка, не ставим название группы
    await q(
        `INSERT INTO topics (id, section_id, tg_chat_id, tg_thread_id, title, updated_at)
     VALUES (
       (SELECT COALESCE(MAX(id),0)+1 FROM topics),
       1, $1, $2,
       COALESCE(
         (SELECT title FROM topics WHERE tg_chat_id=$1 AND tg_thread_id=$2),
         NULL
       ),
       NOW()
     )
     ON CONFLICT (tg_chat_id, tg_thread_id)
     DO UPDATE SET updated_at = NOW()`,
        [chatId, threadId]
    );

    // 3) Сохраняем пост
    const author =
        msg.from?.first_name || msg.from?.last_name
            ? [msg.from?.first_name, msg.from?.last_name].filter(Boolean).join(' ')
            : msg.from?.username
                ? '@' + msg.from.username
                : 'Гость';

    const text: string =
        msg.text ?? msg.caption ?? (msg.entities ? msg.text : '') ?? '';

    if (msg.message_id) {
        await q(
            `INSERT INTO posts (id, topic_id, tg_message_id, author_display, content_md, created_at)
       VALUES (
         (SELECT COALESCE(MAX(id),0)+1 FROM posts),
         (SELECT id FROM topics WHERE tg_chat_id=$1 AND tg_thread_id=$2),
         $3, $4, $5, COALESCE($6, NOW())
       )
       ON CONFLICT DO NOTHING`,
            [chatId, threadId, msg.message_id, author, text, createdAt]
        );
    }

    return NextResponse.json({ ok: true });
}
