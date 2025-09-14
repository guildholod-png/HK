import { NextRequest, NextResponse } from 'next/server'
import { q } from '@/lib/db'

export const dynamic = 'force-dynamic' // важно для некоторых хостингов

export async function POST(req: NextRequest) {
  const update = await req.json()
  const msg = update.message || update.edited_message || update.channel_post
  if (!msg) return NextResponse.json({ ok: true })

  const chat = msg.chat
  const threadId = msg.message_thread_id
  const messageId = msg.message_id
  const date = msg.date
  const from = msg.from

  if (!threadId || !chat || !messageId) return NextResponse.json({ ok: true })

  // Создаём/обновляем тему
  const [topic] = await q(
    `INSERT INTO topics(section_id, slug, title, tg_chat_id, tg_thread_id)
     VALUES ($1, $2, $3, $4, $5)
     ON CONFLICT (tg_chat_id, tg_thread_id)
     DO UPDATE SET updated_at = now()
     RETURNING id`,
    [1, null, chat.title ?? 'Тема', chat.id, threadId]
  )

  const author = from ? (from.username ? `@${from.username}` : `${from.first_name ?? ''} ${from.last_name ?? ''}`.trim()) : 'anon'
  const text = msg.text ?? msg.caption ?? ''
  const media = msg.photo || msg.document || msg.video || null

  await q(
    `INSERT INTO posts(topic_id, tg_message_id, author_tg_id, author_display, content_md, created_at, media_json)
     VALUES ($1, $2, $3, $4, $5, to_timestamp($6), $7)
     ON CONFLICT DO NOTHING`,
    [topic.id, messageId, from?.id ?? null, author, text, date, media ? JSON.stringify(media) : null]
  )

  return NextResponse.json({ ok: true })
}
