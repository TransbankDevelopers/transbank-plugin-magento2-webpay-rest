import mysql from "mysql2/promise";
import { createHash } from "crypto";

const DB_CONFIG = {
    host: process.env.DB_HOST,
    port: Number.parseInt(process.env.DB_PORT, 10),
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
};

let pool;

function getPool() {
    if (!pool) {
        pool = mysql.createPool({
            ...DB_CONFIG,
            waitForConnections: true,
            connectionLimit: 5,
        });
    }

    return pool;
}

export async function closePool() {
    if (pool) {
        await pool.end();
        pool = null;
    }
}

const queryScalar = async (sql, params = []) => {
    const [rows] = await getPool().execute(sql, params);

    return rows[0] ? Object.values(rows[0])[0] : null;
};

function buildLockName(token) {
    const hash = createHash("sha256").update(token).digest("hex").slice(0, 40);
    return "transbank_webpay_lock_" + hash;
}

export async function holdLock(token) {
    const lockName = buildLockName(token);
    const connection = await mysql.createConnection(DB_CONFIG);
    const [rows] = await connection.execute(
        "SELECT GET_LOCK(?, 0) AS acquired",
        [lockName],
    );
    const acquired = rows[0].acquired === 1;

    if (!acquired) {
        await connection.end();
        throw new Error(`Could not acquire lock for token: ${token}`);
    }

    return {
        release: async () => {
            await connection.execute("SELECT RELEASE_LOCK(?)", [lockName]);
            await connection.end();
        },
    };
}

export async function isLockHeld(token) {
    const lockName = buildLockName(token);
    const result = await queryScalar("SELECT IS_USED_LOCK(?)", [lockName]);

    return result !== null;
}

export async function getOrderCountByToken(token) {
    const result = await queryScalar(
        "SELECT COUNT(*) FROM sales_order WHERE entity_id = (SELECT order_id FROM webpay_orders_data WHERE token = ? LIMIT 1)",
        [token],
    );

    return Number(result);
}

export async function getTransactionStatus(token) {
    const result = await queryScalar(
        "SELECT payment_status FROM webpay_orders_data WHERE token = ? LIMIT 1",
        [token],
    );

    return result;
}
