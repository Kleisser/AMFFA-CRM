'use strict';

const sql = require('mssql');
const config = require('/gecros-bridge/src/config');

(async () => {
  const pool = await sql.connect(config.db);

  const total = await pool.request().query(`
    SELECT COUNT(*) AS n FROM dbo.Benef WHERE venafi_id > 0 AND doc_id > 0
  `);
  console.log('benef con venafi_id+doc_id:', total.recordset[0].n);

  const sample = await pool.request().query(`
    SELECT TOP 5
      LTRIM(RTRIM(CAST(b.doc_id AS VARCHAR(20)))) AS dni,
      b.venafi_id,
      LTRIM(RTRIM(v.venafi_nombre)) AS vendedor
    FROM dbo.Benef b
    JOIN dbo.vendedoresafi v ON b.venafi_id = v.venafi_id
    WHERE b.venafi_id > 0 AND b.doc_id > 0
  `);
  sample.recordset.forEach((r) => console.log(`  ${r.dni}\t${r.venafi_id}\t${r.vendedor}`));

  await sql.close();
})().catch((e) => {
  console.error('ERROR:', e.message);
  process.exit(1);
});
