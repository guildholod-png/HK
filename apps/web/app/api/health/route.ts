export const runtime = 'nodejs';
export const dynamic = 'force-dynamic';

import { NextResponse } from 'next/server';
import { q } from '@/lib/db';

export async function GET() {
    const [row] = await q<{ now: string }>('select now()');
    return NextResponse.json({ ok: true, now: row?.now ?? null });
}
