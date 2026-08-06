'use strict';

const BASE = process.env.BASE || 'http://localhost:3000';
const KEY = process.env.KEY || 'clave_test_gecros_2026';

let fallas = 0;

function check(nombre, cond, detalle) {
  console.log(`${cond ? 'PASS' : 'FAIL'}  ${nombre}${cond ? '' : '  -> ' + JSON.stringify(detalle)}`);
  if (!cond) fallas += 1;
}

async function call(path, params = {}) {
  const url = new URL(BASE + path);
  for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v);
  const res = await fetch(url, { headers: { 'X-API-Key': KEY } });
  return { status: res.status, body: await res.json() };
}

(async () => {
  const health = await call('/health');
  check('health 200', health.status === 200, health);

  const garcia = await call('/afiliado', { dni: '30111222' });
  check('GARCIA found', garcia.body.found === true, garcia.body);
  check('GARCIA grupo', garcia.body.grupo === '10001', garcia.body);
  check('GARCIA plan', garcia.body.plan === 'PREMIUM PLATA', garcia.body.plan);
  check('GARCIA activo', garcia.body.activo === true, garcia.body);
  check('GARCIA sin baja', garcia.body.tiene_baja === false, garcia.body);
  check('GARCIA 3 integrantes', (garcia.body.grupoFamiliar || []).length === 3, garcia.body);
  const gJ = (garcia.body.grupoFamiliar || [])[0];
  check('GARCIA titular', gJ && gJ.parentesco === 'Titular', gJ);
  check('GARCIA hijo edad 13', (garcia.body.grupoFamiliar || [])[2].edad === 13, garcia.body.grupoFamiliar);
  check('GARCIA vendedor FRANCO ROMINA', garcia.body.vendedor && garcia.body.vendedor.nombre === 'FRANCO ROMINA', garcia.body.vendedor);
  check('GARCIA venafi_id 145', garcia.body.vendedor && garcia.body.vendedor.venafi_id === 145, garcia.body.vendedor);
  check('GARCIA integrante con vendedor', (garcia.body.grupoFamiliar || [])[0].vendedor?.nombre === 'FRANCO ROMINA', garcia.body.grupoFamiliar);
  console.log('afiliado GARCIA:', JSON.stringify(garcia.body, null, 1).slice(0, 700));

  const vendedores = await call('/vendedores');
  check('vendedores catalogo', vendedores.body.vendedores.length >= 15, vendedores.body);
  check('vendedores incluye ORTIZ', vendedores.body.vendedores.some((v) => v.venafi_id === 63 && /ORTIZ GLADYS/.test(v.nombre)), vendedores.body);
  check('vendedores incluye ANZELMO', vendedores.body.vendedores.some((v) => v.venafi_id === 70), vendedores.body);
  check('vendedores incluye DETRY', vendedores.body.vendedores.some((v) => v.venafi_id === 135), vendedores.body);

  const pares = await call('/venafi-por-dni');
  check('venafi-por-dni devuelve pares', pares.body.pares.length >= 6, pares.body);
  check('venafi-por-dni mapea GARCIA 145', pares.body.pares.some((p) => p.dni === '30111222' && p.venafi_id === 145), pares.body.pares);
  check('venafi-por-dni mapea LOPEZ 148', pares.body.pares.some((p) => p.dni === '27555444' && p.venafi_id === 148), pares.body.pares);

  const perez = await call('/afiliado', { dni: '33333888' });
  check('PEREZ activo (baja futura ignorada)', perez.body.activo === true && perez.body.tiene_baja === false, perez.body);

  const lopez = await call('/afiliado', { dni: '27555444' });
  check('LOPEZ con baja', lopez.body.tiene_baja === true, lopez.body);

  const noExiste = await call('/afiliado', { dni: '99999999' });
  check('DNI inexistente found:false', noExiste.body.found === false, noExiste.body);

  const sinKey = await fetch(BASE + '/afiliado?dni=30111222');
  check('sin API key -> 401', sinKey.status === 401, sinKey.status);

  const ab = await call('/altas-bajas', { desde: '2026-06-26', hasta: '2026-07-25' });
  const gruposAltaJul = new Set(ab.body.altas.map((a) => a.grupo)).size;
  const gruposBajaJul = new Set(ab.body.bajas.map((b) => b.grupo)).size;
  check('cierre JUL altas = 1 grupo (3 integrantes)', ab.body.altas.length === 3 && gruposAltaJul === 1, ab.body.altas);
  check('cierre JUL bajas = 1 grupo (2 integrantes)', ab.body.bajas.length === 2 && gruposBajaJul === 1, ab.body.bajas);
  const bJul = ab.body.bajas[0] || {};
  check('baja JUL = LOPEZ', bJul.numero === '1000100', bJul);
  check('baja JUL fecha', bJul.fecha === '2026-07-20', bJul);
  check('baja JUL vendedor FERREIRA', bJul.vendedor && bJul.vendedor.nombre === 'FERREIRA VIVIANA', bJul.vendedor);
  check('alta JUL vendedor FRANCO', ab.body.altas[0].vendedor && ab.body.altas[0].vendedor.nombre === 'FRANCO ROMINA', ab.body.altas[0].vendedor);

  const ab2 = await call('/altas-bajas', { desde: '2026-01-26', hasta: '2026-07-25' });
  const porMes = {};
  ab2.body.altas.forEach((a) => {
    const m = (a.fecha || '').slice(0, 7);
    porMes[m] = new Set(ab2.body.altas.filter((x) => (x.fecha || '').slice(0, 7) === m).map((x) => x.grupo)).size;
  });
  const esperado = { '2026-02': 1, '2026-03': 1, '2026-04': 1, '2026-05': 1, '2026-06': 1, '2026-07': 1 };
  const ordena = (o) => JSON.stringify(o, Object.keys(o).sort());
  check('altas por mes (1 grupo c/mes feb-jul)', ordena(porMes) === ordena(esperado), porMes);
  check('bajas totales = 1 grupo (temporal NO cuenta)',
    new Set(ab2.body.bajas.map((b) => b.grupo)).size === 1, ab2.body.bajas);
  console.log('altas por mes:', JSON.stringify(porMes));
  console.log('bajas (ene-jul):', ab2.body.bajas.map((b) => `${b.dni} ${b.apellido} ${b.fecha}`).join('; '));

  const mal = await call('/altas-bajas', { desde: 'invalido', hasta: '2026-07-25' });
  check('fechas invalidas -> 400', mal.status === 400, mal.status);

  console.log(fallas === 0 ? '\nTODOS LOS CHECKS OK' : `\n${fallas} CHECKS FALLARON`);
  process.exit(fallas === 0 ? 0 : 1);
})();
