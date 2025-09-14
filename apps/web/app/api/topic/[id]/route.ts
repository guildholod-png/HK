import { NextRequest, NextResponse } from 'next/server'
import { q } from '@/lib/db'

export const runtime = 'nodejs';
export const dynamic = 'force-dynamic'
export async function GET(_req: NextRequest, { params }: { params: { id: string } }) {
  const [topic] = await q(`SELECT id, title, tg_thread_id, tg_chat_id FROM topics WHERE id = $1`, [params.id])
  if (!topic) return NextResponse.json({ error: 'not found' }, { status: 404 })
  const posts = await q(`SELECT tg_message_id, author_display, content_md, created_at FROM posts WHERE topic_id=$1 ORDER BY created_at ASC`, [params.id])
  return NextResponse.json({ topic, posts })
}
