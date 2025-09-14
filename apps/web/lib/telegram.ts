const TOKEN = process.env.TELEGRAM_BOT_TOKEN!
const API = `https://api.telegram.org/bot${TOKEN}`

export async function sendMessage(opts: {
  chat_id: number | string;
  text: string;
  message_thread_id?: number; // направить в topic
  parse_mode?: 'HTML' | 'MarkdownV2' | 'Markdown';
  link_preview_options?: { is_disabled?: boolean };
}) {
  const res = await fetch(`${API}/sendMessage`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(opts),
  })
  if (!res.ok) throw new Error(`sendMessage failed: ${res.status}`)
  return res.json()
}

export async function createForumTopic(chat_id: number | string, name: string) {
  const res = await fetch(`${API}/createForumTopic`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ chat_id, name }),
  })
  if (!res.ok) throw new Error(`createForumTopic failed: ${res.status}`)
  return res.json()
}
