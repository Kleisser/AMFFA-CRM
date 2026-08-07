'use strict';

const sql = require('mssql');
const config = require('/gecros-bridge/src/config');

(async () => {
  const pool = await sql.connect(config.db);

  const zonaCols = await pool.request().query(`
    SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'zonas' ORDER BY ORDINAL_POSITION
  `);
  console.log('COLUMNAS zonas:', zonaCols.recordset.map((c) => `${c.COLUMN_NAME}:${c.DATA_TYPE}`).join(', '));

  const zonas = await pool.request().query(`SELECT * FROM dbo.zonas ORDER BY 1`);
  console.log('\nzonas (' + zonas.recordset.length + '):');
  zonas.recordset.forEach((r) => console.log('  ' + JSON.stringify(Object.values(r))));

  const radCols = await pool.request().query(`
    SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'BenRadic' ORDER BY ORDINAL_POSITION
  `);
  console.log('\nCOLUMNAS BenRadic:', radCols.recordset.map((c) => `${c.COLUMN_NAME}:${c.DATA_TYPE}`).join(', '));

  const rad = await pool.request().query(`
    SELECT TOP 10 * FROM dbo.BenRadic
  `);
  console.log('\nBenRadic (muestra):');
  rad.recordset.forEach((r) => console.log('  ' + JSON.stringify(Object.values(r))));

  const localCols = await pool.request().query(`
    SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'localidades' ORDER BY ORDINAL_POSITION
  `);
  console.log('\nCOLUMNAS localidades:', localCols.recordset.map((c) => `${c.COLUMN_NAME}:${c.DATA_TYPE}`).join(', '));

  const local = await pool.request().query(`SELECT TOP 5 * FROM dbo.localidades`);
  console.log('\nlocalidades (muestra):');
  local.recordset.forEach((r) => console.log('  ' + JSON.stringify(Object.values(r))));

  await sql.close();
})().catch((e) => {
  console.error('ERROR:', e.message);
  process.exit(1);
});
