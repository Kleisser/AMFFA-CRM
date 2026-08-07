'use strict';

const sql = require('mssql');

(async () => {
  const pool = await sql.connect({
    server: 'gecros-sql',
    database: 'GECROS',
    user: 'crm_bridge',
    password: 'Bridge2026!',
    options: { encrypt: false, enableArithAbort: true },
  });

  const intentos = [
    ['INSERT en Benef (escribir)', "INSERT INTO dbo.Benef (numero, ben_ape, ben_nom, fecha_alta) VALUES ('1','X','X','2026-01-01')"],
    ['DELETE en BenefCambio', 'DELETE FROM dbo.BenefCambio'],
    ['ALTER tabla (DDL)', 'ALTER TABLE dbo.Benef ADD col_test INT'],
    ['SELECT tabla sin permiso (encauto)', 'SELECT TOP 1 * FROM dbo.encauto'],
  ];

  for (const [nombre, sqlText] of intentos) {
    try {
      await pool.request().query(sqlText);
      console.log(`[FALLO DE SEGURIDAD] ${nombre} -> SE PUDO EJECUTAR`);
    } catch (e) {
      console.log(`[BLOQUEADO OK] ${nombre} -> ${String(e.message).slice(0, 70)}`);
    }
  }

  await sql.close();
})();
