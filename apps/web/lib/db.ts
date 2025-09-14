import { Pool, QueryResultRow } from 'pg';

// TEMP: на всякий случай выключим проверку цепочки в проде на Vercel
if (process.env.VERCEL === '1') {
    process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
}

const globalForPg = globalThis as unknown as { _pgPool?: Pool };

export const pool =
    globalForPg._pgPool ??
    new Pool({
        connectionString: process.env.DATABASE_URL,
        max: 3,
        idleTimeoutMillis: 30000,
        ssl: { rejectUnauthorized: false }, // критично для Supabase на Vercel
    });

if (!globalForPg._pgPool) globalForPg._pgPool = pool;

export async function q<T extends QueryResultRow = QueryResultRow>(
    sql: string,
    params: any[] = []
) {
    const { rows } = await pool.query(sql, params);
    return rows as T[];
}
