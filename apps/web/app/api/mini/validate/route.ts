import { NextRequest, NextResponse } from "next/server";
import { validate } from "@telegram-apps/init-data-node";

export async function POST(req: NextRequest) {
  const { initDataRaw } = await req.json();
  try {
    validate(initDataRaw, process.env.TELEGRAM_BOT_TOKEN!);
    return NextResponse.json({ ok: true });
  } catch (e: any) {
    return NextResponse.json({ ok: false, error: e.message }, { status: 400 });
  }
}
