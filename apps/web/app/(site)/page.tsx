import Link from 'next/link'
import { q } from '@/lib/db'

export const dynamic = 'force-dynamic'

export default async function Home() {
  const topics = await q<{id:number,title:string}>(`SELECT id, title FROM topics ORDER BY updated_at DESC LIMIT 20`)
  return (
    <main className="mx-auto max-w-4xl p-6">
      <h1 className="text-2xl font-semibold mb-4">Последние темы</h1>
      <ul className="space-y-2">
        {topics.map(t => (
          <li key={t.id} className="p-3 rounded border">
            <Link href={`/topic/${t.id}`} className="hover:underline">{t.title}</Link>
          </li>
        ))}
      </ul>
    </main>
  )
}
