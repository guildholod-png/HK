import { NextResponse } from 'next/server'
import { q } from '@/lib/db'
export const runtime = 'nodejs';
export const dynamic = 'force-dynamic'
export async function GET() {
  const rows = await q(`SELECT id, title, tg_thread_id FROM topics ORDER BY updated_at DESC LIMIT 200`)
  return NextResponse.json(rows)
}
