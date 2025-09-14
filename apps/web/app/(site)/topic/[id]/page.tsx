import { q } from '@/lib/db'
import { topicLink } from '@/lib/links'

export const dynamic = 'force-dynamic'

export default async function TopicPage({ params }: { params: { id: string } }) {
  const [topic] = await q<{id:number,title:string,tg_thread_id:number}>(`SELECT id, title, tg_thread_id FROM topics WHERE id=$1`, [params.id])
  if (!topic) return <main className="mx-auto max-w-3xl p-6">Тема не найдена</main>
    const posts = await q<{
        tg_message_id: number;
        author_display: string;
        content_md: string;
        created_at: string;
    }>(
        `SELECT tg_message_id, author_display, content_md, created_at
   FROM posts WHERE topic_id=$1 ORDER BY created_at ASC`,
        [params.id]
    );
  const link = topicLink(Number(topic.tg_thread_id))
  return (
    <main className="mx-auto max-w-3xl p-6">
      <div className="flex items-center justify-between mb-4">
        <h1 className="text-xl font-semibold">{topic.title}</h1>
        <a href={link} className="px-4 py-2 rounded bg-blue-600 text-white" target="_blank">Ответить в Telegram</a>
      </div>
      <ol className="space-y-4">
        {posts.map((p) => (
          <li key={p.tg_message_id} className="p-3 rounded border">
            <div className="text-sm text-gray-500">{new Date(p.created_at).toLocaleString()} — {p.author_display}</div>
            <div className="whitespace-pre-wrap mt-1">{p.content_md}</div>
          </li>
        ))}
      </ol>
    </main>
  )
}
