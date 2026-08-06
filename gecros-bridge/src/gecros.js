'use strict';

/**
 * Repositorio de consultas a la base GECROS (SOLO LECTURA).
 *
 * Esquema real verificado contra producción (2026-08):
 *  - dbo.Benef        : ben_id, ben_ape, ben_nom, numero (nº de socio, char(15) con
 *                       espacios), doc_id (DNI, bigint), par_id (parentesco),
 *                       ben_gr_id (grupo familiar, bigint), fechanac, fecha_alta.
 *  - dbo.BenefCambio  : ben_id, tcambio_id (15=estado, 3=baja definitiva,
 *                       18=baja temporal, 6=cambio de plan...), plan_id,
 *                       bc_datonue, bc_fecha (fecha de vigencia del cambio).
 *  - dbo.planes       : plan_id, plan_nombre.
 *
 * Reglas de negocio:
 *  - Grupo familiar  : integrantes de dbo.Benef con el mismo ben_gr_id.
 *  - Parentesco      : par_id -> 2=Titular, 3=Cónyuge, 4=Hijo/a, 5=Familiar a cargo.
 *  - Estado          : última fila tcambio_id=15 con bc_fecha <= hoy;
 *                      bc_datonue 1=Con Cobertura, 2=Sin Cobertura por Baja,
 *                      3=Momentáneamente sin Cobertura, 4=En gestión judicial.
 *                      OJO: GECROS precarga cambios a fin de mes (bc_fecha futura),
 *                      por eso SIEMPRE filtrar por bc_fecha <= hoy.
 *  - Plan vigente    : última fila con plan_id NOT NULL y bc_fecha <= fecha
 *                      (hoy para estado, fecha del alta/baja para movimientos).
 *  - Baja definitiva : tcambio_id=3. La temporal (18) NO cuenta como baja.
 */

const sql = require('mssql');
const config = require('./config');

const PARENTESCOS = {
  1: null,
  2: 'Titular',
  3: 'Cónyuge',
  4: 'Hijo/a',
  5: 'Familiar a cargo',
};

const ESTADOS = {
  1: 'Con Cobertura',
  2: 'Sin Cobertura por Baja',
  3: 'Momentáneamente sin Cobertura',
  4: 'Sin Cobertura en Gestión Judicial',
};

const FECHA_NULA = '1900-01-01';

class GecrosRepository {
  /**
   * Busca un afiliado por DNI (doc_id). Devuelve el grupo familiar completo
   * (mismo ben_gr_id) o null si el DNI no existe.
   */
  async findByNumero(dni) {
    let pool;
    try {
      pool = await sql.connect(config.db);
      const hoy = new Date().toISOString().slice(0, 10);

      const result = await pool
        .request()
        .input('dni', sql.BigInt, Number(dni))
        .input('hoy', sql.VarChar(10), hoy)
        .query(`
          ;WITH PlanVigente AS (
            SELECT
              ben_id,
              plan_id,
              ROW_NUMBER() OVER (PARTITION BY ben_id ORDER BY bc_fecha DESC, bc_id DESC) AS rn
            FROM dbo.BenefCambio
            WHERE plan_id IS NOT NULL
              AND bc_fecha <= CAST(@hoy AS DATE)
          ),
          EstadoActual AS (
            SELECT
              ben_id,
              bc_datonue,
              ROW_NUMBER() OVER (PARTITION BY ben_id ORDER BY bc_fecha DESC, bc_id DESC) AS rn
            FROM dbo.BenefCambio
            WHERE tcambio_id = 15
              AND bc_fecha <= CAST(@hoy AS DATE)
          ),
          Baja AS (
            SELECT DISTINCT ben_id
            FROM dbo.BenefCambio
            WHERE tcambio_id IN (3, 18)
              AND bc_fecha <= CAST(@hoy AS DATE)
          )
          SELECT
            b.ben_id,
            LTRIM(RTRIM(CAST(b.doc_id AS VARCHAR(20)))) AS dni,
            LTRIM(RTRIM(b.numero)) AS numero,
            b.ben_ape,
            b.ben_nom,
            b.ben_gr_id,
            b.par_id,
            b.fechanac,
            b.fecha_alta,
            b.venafi_id,
            LTRIM(RTRIM(v.venafi_nombre)) AS vendedor_nombre,
            p.plan_nombre,
            ea.bc_datonue AS estado_codigo,
            CASE WHEN br.ben_id IS NOT NULL THEN 1 ELSE 0 END AS tiene_baja
          FROM dbo.Benef b
          LEFT JOIN dbo.vendedoresafi v ON b.venafi_id = v.venafi_id
          LEFT JOIN PlanVigente pv ON b.ben_id = pv.ben_id AND pv.rn = 1
          LEFT JOIN dbo.planes p ON pv.plan_id = p.plan_id
          LEFT JOIN EstadoActual ea ON b.ben_id = ea.ben_id AND ea.rn = 1
          LEFT JOIN Baja br ON b.ben_id = br.ben_id
          WHERE b.ben_gr_id IN (
            SELECT DISTINCT ben_gr_id
            FROM dbo.Benef
            WHERE doc_id = @dni
          )
          ORDER BY b.par_id, b.ben_id
        `);

      const rows = result.recordset || [];
      if (rows.length === 0) {
        return null;
      }

      const querido = rows.find((r) => r.dni === String(dni)) || rows[0];

      return {
        found: true,
        dni: querido.dni,
        numero: querido.numero,
        grupo: String(querido.ben_gr_id),
        plan: querido.plan_nombre || null,
        activo: String(querido.estado_codigo || '') === '1',
        estado: ESTADOS[Number(querido.estado_codigo)] || null,
        tiene_baja: Number(querido.tiene_baja) === 1,
        fecha_alta: this.#fechaValida(querido.fecha_alta),
        relacion: PARENTESCOS[Number(querido.par_id)] || null,
        vendedor: querido.vendedor_nombre
          ? { venafi_id: querido.venafi_id, nombre: querido.vendedor_nombre }
          : null,
        grupoFamiliar: rows.map((row) => ({
          dni: row.dni,
          numero: row.numero,
          apellido: row.ben_ape || null,
          nombre: row.ben_nom || null,
          parentesco: PARENTESCOS[Number(row.par_id)] || null,
          edad: this.#edadDesde(row.fechanac, hoy),
          fecha_alta: this.#fechaValida(row.fecha_alta),
          vendedor: row.vendedor_nombre
            ? { venafi_id: row.venafi_id, nombre: row.vendedor_nombre }
            : null,
        })),
      };
    } finally {
      if (pool) {
        sql.close();
      }
    }
  }

  /**
   * Altas y bajas en un período [desde, hasta] (cierre mensual: 26 al 25).
   *  - Altas : dbo.Benef.fecha_alta dentro del período.
   *  - Bajas : fila de BenefCambio con tcambio_id = 3 dentro del período
   *            (la temporal 18 no cuenta).
   *  - Plan  : vigente al momento del alta / de la baja.
   * Devuelve una fila por integrante (el grupo familiar comparte ben_gr_id);
   * el CRM cuenta por ben_gr_id distinto para medir afiliaciones.
   */
  async findAltasBajas(desde, hasta) {
    let pool;
    try {
      pool = await sql.connect(config.db);

      const altas = await pool
        .request()
        .input('desde', sql.VarChar(10), desde)
        .input('hasta', sql.VarChar(10), hasta)
        .query(`
          SELECT
            b.ben_id,
            LTRIM(RTRIM(CAST(b.doc_id AS VARCHAR(20)))) AS dni,
            LTRIM(RTRIM(b.numero)) AS numero,
            b.ben_ape,
            b.ben_nom,
            b.ben_gr_id,
            b.par_id,
            b.fechanac,
            b.fecha_alta,
            b.venafi_id,
            LTRIM(RTRIM(v.venafi_nombre)) AS vendedor_nombre,
            p.plan_nombre
          FROM dbo.Benef b
          LEFT JOIN dbo.vendedoresafi v ON b.venafi_id = v.venafi_id
          OUTER APPLY (
            SELECT TOP 1 bc.plan_id
            FROM dbo.BenefCambio bc
            WHERE bc.ben_id = b.ben_id
              AND bc.plan_id IS NOT NULL
              AND bc.bc_fecha <= b.fecha_alta
            ORDER BY bc.bc_fecha DESC, bc.bc_id DESC
          ) pp
          LEFT JOIN dbo.planes p ON pp.plan_id = p.plan_id
          WHERE b.fecha_alta >= CAST(@desde AS DATE)
            AND b.fecha_alta <= CAST(@hasta AS DATE)
        `);

      const bajas = await pool
        .request()
        .input('desde', sql.VarChar(10), desde)
        .input('hasta', sql.VarChar(10), hasta)
        .query(`
          SELECT
            b.ben_id,
            LTRIM(RTRIM(CAST(b.doc_id AS VARCHAR(20)))) AS dni,
            LTRIM(RTRIM(b.numero)) AS numero,
            b.ben_ape,
            b.ben_nom,
            b.ben_gr_id,
            b.par_id,
            b.fechanac,
            b.venafi_id,
            LTRIM(RTRIM(v.venafi_nombre)) AS vendedor_nombre,
            baj.fecha_baja,
            p.plan_nombre
          FROM dbo.Benef b
          LEFT JOIN dbo.vendedoresafi v ON b.venafi_id = v.venafi_id
          JOIN (
            SELECT ben_id, MAX(bc_fecha) AS fecha_baja
            FROM dbo.BenefCambio
            WHERE tcambio_id = 3
              AND bc_fecha >= CAST(@desde AS DATE)
              AND bc_fecha <= CAST(@hasta AS DATE)
            GROUP BY ben_id
          ) baj ON b.ben_id = baj.ben_id
          OUTER APPLY (
            SELECT TOP 1 bc.plan_id
            FROM dbo.BenefCambio bc
            WHERE bc.ben_id = b.ben_id
              AND bc.plan_id IS NOT NULL
              AND bc.bc_fecha <= baj.fecha_baja
            ORDER BY bc.bc_fecha DESC, bc.bc_id DESC
          ) pp
          LEFT JOIN dbo.planes p ON pp.plan_id = p.plan_id
        `);

      const hoy = new Date().toISOString().slice(0, 10);

      return {
        desde,
        hasta,
        altas: (altas.recordset || []).map((row) => this.#mapMovimiento(row, 'fecha_alta', hoy)),
        bajas: (bajas.recordset || []).map((row) => this.#mapMovimiento(row, 'fecha_baja', hoy)),
      };
    } finally {
      if (pool) {
        sql.close();
      }
    }
  }

  /**
   * Mapa doc_id (DNI) => venafi_id de todos los afiliados que tienen
   * vendedor asignado. El CRM lo usa para mapear vendedores de GECROS
   * a usuarios del CRM cruzando DNI con sus contactos.
   */
  async listVendedoresPorDni() {
    let pool;
    try {
      pool = await sql.connect(config.db);
      const result = await pool.request().query(`
        SELECT
          LTRIM(RTRIM(CAST(doc_id AS VARCHAR(20)))) AS dni,
          venafi_id
        FROM dbo.Benef
        WHERE venafi_id > 0
          AND doc_id > 0
      `);
      return result.recordset || [];
    } finally {
      if (pool) {
        sql.close();
      }
    }
  }

  #mapMovimiento(row, fechaKey, hoy) {
    return {
      dni: row.dni,
      numero: row.numero,
      grupo: String(row.ben_gr_id),
      apellido: row.ben_ape ?? null,
      nombre: row.ben_nom ?? null,
      plan: row.plan_nombre ?? null,
      parentesco: PARENTESCOS[Number(row.par_id)] || null,
      edad: this.#edadDesde(row.fechanac, hoy),
      fecha: this.#fechaValida(row[fechaKey]),
      vendedor: row.vendedor_nombre
        ? { venafi_id: row.venafi_id, nombre: row.vendedor_nombre }
        : null,
    };
  }

  /**
   * Catálogo de vendedores de afiliación (dbo.vendedoresafi).
   * El CRM lo sincroniza para mapear cada vendedor a su equipo.
   */
  async listVendedores() {
    let pool;
    try {
      pool = await sql.connect(config.db);
      const result = await pool.request().query(`
        SELECT
          venafi_id,
          LTRIM(RTRIM(venafi_nombre)) AS nombre
        FROM dbo.vendedoresafi
        WHERE venafi_id > 0
        ORDER BY nombre
      `);
      return result.recordset || [];
    } finally {
      if (pool) {
        sql.close();
      }
    }
  }

  #fechaValida(value) {
    if (!value) {
      return null;
    }
    const iso = new Date(value).toISOString().slice(0, 10);
    return iso === FECHA_NULA ? null : iso;
  }

  #edadDesde(fechanac, hoy) {
    if (!fechanac) {
      return null;
    }
    const nac = new Date(fechanac);
    if (Number.isNaN(nac.getTime())) {
      return null;
    }
    const [y, m, d] = hoy.split('-').map(Number);
    let edad = y - nac.getFullYear();
    const mesNac = nac.getMonth() + 1;
    if (m < mesNac || (m === mesNac && d < nac.getDate())) {
      edad -= 1;
    }
    return edad;
  }
}

module.exports = GecrosRepository;
