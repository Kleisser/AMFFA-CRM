'use strict';

const sql = require('mssql');
const config = require('/gecros-bridge/src/config');

(async () => {
  const pool = await sql.connect(config.db);

  for (const t of ['vendedoresafi', 'vendedores']) {
    const cols = await pool.request().query(`
      SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_NAME = '${t}' ORDER BY ORDINAL_POSITION
    `);
    console.log(`\nCOLUMNAS ${t}:`, cols.recordset.map((c) => c.COLUMN_NAME + ':' + c.DATA_TYPE).join(', '));
  }

  const vafi = await pool.request().query(`
    SELECT TOP 30 * FROM dbo.vendedoresafi ORDER BY ven_id
  `);
  console.log('\nvendedoresafi (muestra):');
  console.log(JSON.stringify(vafi.recordset, null, 1));

  const vendedores = await pool.request().query(`
    SELECT TOP 20 * FROM dbo.vendedores ORDER BY ven_id
  `);
  console.log('\nvendedores (muestra):');
  console.log(JSON.stringify(vendedores.recordset, null, 1));

  await sql.close();
})().catch((e) => {
  console.error('ERROR:', e.message);
  process.exit(1);
});
