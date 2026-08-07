'use strict';

const sql = require('mssql');
const config = require('/gecros-bridge/src/config');

(async () => {
  const pool = await sql.connect(config.db);

  const vafi = await pool.request().query(`
    SELECT venafi_id, LTRIM(RTRIM(venafi_nombre)) AS nombre, venafi_Cuit AS cuit
    FROM dbo.vendedoresafi
    ORDER BY venafi_id
  `);
  console.log('vendedoresafi (' + vafi.recordset.length + '):');
  vafi.recordset.forEach((r) => console.log(`  ${r.venafi_id}\t${r.nombre}\t${r.cuit || ''}`));

  const v = await pool.request().query(`
    SELECT ven_id, LTRIM(RTRIM(ven_nombre)) AS nombre, ven_email AS email
    FROM dbo.vendedores
    ORDER BY ven_id
  `);
  console.log('\nvendedores (' + v.recordset.length + '):');
  v.recordset.forEach((r) => console.log(`  ${r.ven_id}\t${r.nombre}\t${r.email || ''}`));

  await sql.close();
})().catch((e) => {
  console.error('ERROR:', e.message);
  process.exit(1);
});
