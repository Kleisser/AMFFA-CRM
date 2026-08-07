'use strict';

const sql = require('mssql');
const config = require('/gecros-bridge/src/config');

(async () => {
  const pool = await sql.connect(config.db);

  const tabs = await pool.request().query(`
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_TYPE = 'BASE TABLE'
      AND (TABLE_NAME LIKE '%vend%' OR TABLE_NAME LIKE '%vendedor%' OR TABLE_NAME LIKE '%asesor%' OR TABLE_NAME LIKE '%empleado%')
  `);
  console.log('TABLAS con vend/asesor/empleado:');
  tabs.recordset.forEach((r) => console.log('  ' + r.TABLE_NAME));

  const all = await pool.request().query(`
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME
  `);
  console.log('\nTODAS las tablas dbo:');
  console.log(all.recordset.map((r) => r.TABLE_NAME).join(', '));

  const ven = await pool.request().query(`
    SELECT ven_id, COUNT(*) AS n FROM dbo.Benef GROUP BY ven_id ORDER BY n DESC
  `);
  console.log('\ndistribucion ven_id en Benef:');
  ven.recordset.forEach((r) => console.log(`  ven_id=${r.ven_id}  n=${r.n}`));

  const venafi = await pool.request().query(`
    SELECT venafi_id, COUNT(*) AS n FROM dbo.Benef GROUP BY venafi_id ORDER BY n DESC
  `);
  console.log('\ndistribucion venafi_id en Benef:');
  venafi.recordset.forEach((r) => console.log(`  venafi_id=${r.venafi_id}  n=${r.n}`));

  const yo = await pool.request().query(`
    SELECT ben_ape, ben_nom, ven_id, venafi_id FROM dbo.Benef WHERE doc_id = 19004591
  `);
  console.log('\nmi registro:', JSON.stringify(yo.recordset[0]));

  await sql.close();
})().catch((e) => {
  console.error('ERROR:', e.message);
  process.exit(1);
});
