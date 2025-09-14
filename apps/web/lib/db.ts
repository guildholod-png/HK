import { Pool, QueryResultRow } from 'pg';

export const pool = new Pool({ connectionString: process.env.DATABASE_URL });

export async function q<T extends QueryResultRow = QueryResultRow>(
    sql: string,
    params: any[] = []
) {
    const { rows } = await pool.query(sql, params);
    return rows as T[];
}
