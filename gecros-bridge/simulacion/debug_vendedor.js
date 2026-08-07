'use strict';

const sql = require('mssql');
const config = require('/gecros-bridge/src/config');

(async () => {
  const pool = await sql.connect(config.db);

  const cols = await pool.request().query(`
    SELECT COLUMN_NAME, DATA_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'Benef'
    ORDER BY ORDINAL_POSITION
  `);
  console.log('COLUMNAS dbo.Benef:');
  cols.recordset.forEach((c) => console.log(`  ${c.COLUMN_NAME} (${c.DATA_TYPE})`));

  const colVendedor = cols.recordset.some((c) => /vend|ven_|asesor/i.test(c.COLUMN_NAME));
  console.log('\n¿Columna de vendedor en Benef?', colVendedor ? 'SI' : 'NO');

  const tc = await pool.request().query(`
    SELECT tcambio_id, COUNT(*) AS n, COUNT(DISTINCT bc_datonue) AS datonue_dist
    FROM dbo.BenefCambio
    GROUP BY tcambio_id
    ORDER BY n DESC
  `);
  console.log('\nBenefCambio por tcambio_id:');
  tc.recordset.forEach((r) => console.log(`  ${r.tcambio_id}  n=${r.n}  datonue_distintos=${r.datonue_dist}`));

  const tc8 = await pool.request().query(`
    SELECT TOP 8 b.ben_ape, b.ben_nom, bc.tcambio_id, bc.bc_datonue, bc.bc_fecha
    FROM dbo.BenefCambio bc JOIN dbo.Benef b ON b.ben_id = bc.ben_id
    WHERE bc.tcambio_id = 8
    ORDER BY bc.bc_fecha DESC
  `);
  console.log('\nEjemplos tcambio 8:');
  tc8.recordset.forEach((r) => console.log(`  ${r.ben_ape} ${r.ben_nom} | datonue=${r.bc_datonue} | ${r.bc_fecha?.toISOString().slice(0,10)}`));

  const tc4 = await pool.request().query(`
    SELECT TOP 8 b.ben_ape, b.ben_nom, bc.tcambio_id, bc.bc_datonue, bc.bc_fecha
    FROM dbo.BenefCambio bc JOIN dbo.Benef b ON b.ben_id = bc.ben_id
    WHERE bc.tcambio_id = 4
    ORDER BY bc.bc_fecha DESC
  `);
  console.log('\nEjemplos tcambio 4:');
  tc4.recordset.forEach((r) => console.log(`  ${r.ben_ape} ${r.ben_nom} | datonue=${r.bc_datonue} | ${r.bc_fecha?.toISOString().slice(0,10)}`));

  const tc16 = await pool.request().query(`
    SELECT TOP 8 b.ben_ape, b.ben_nom, bc.tcambio_id, bc.bc_datonue, bc.bc_fecha
    FROM dbo.BenefCambio bc JOIN dbo.Benef b ON b.ben_id = bc.ben_id
    WHERE bc.tcambio_id = 16
    ORDER BY bc.bc_fecha DESC
  `);
  console.log('\nEjemplos tcambio 16:');
  tc16.recordset.forEach((r) => console.log(`  ${r.ben_ape} ${r.ben_nom} | datonue=${r.bc_datonue} | ${r.bc_fecha?.toISOString().slice(0,10)}`));

  await sql.close();
})().catch((e) => {
  console.error('ERROR:', e.message);
  process.exit(1);
});
