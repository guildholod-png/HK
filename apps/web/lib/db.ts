
import { Pool, QueryResultRow } from 'pg';

const globalForPg = globalThis as unknown as { _pgPool?: Pool };

export const pool =
    globalForPg._pgPool ??
    new Pool({
        connectionString: process.env.DATABASE_URL,
        max: 3,
        idleTimeoutMillis: 30000,
        ssl: { rejectUnauthorized: false }, // 👈 добавляем
    });

if (!globalForPg._pgPool) globalForPg._pgPool = pool;

export async function q<T extends QueryResultRow = QueryResultRow>(
    sql: string,
    params: any[] = []
) {
    const { rows } = await pool.query(sql, params);
    return rows as T[];
}
