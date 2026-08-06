'use strict';

const desde = process.argv[2] || '2026-06-26';
const hasta = process.argv[3] || '2026-07-25';
const KEY = process.env.KEY || 'clave_test_gecros_2026';

(async () => {
  const res = await fetch(`http://localhost:3000/altas-bajas?desde=${desde}&hasta=${hasta}`, {
    headers: { 'X-API-Key': KEY },
  });
  const body = await res.json();
  console.log('status:', res.status);
  const gruposA = new Set(body.altas.map((a) => a.grupo)).size;
  const gruposB = new Set(body.bajas.map((b) => b.grupo)).size;
  console.log(`altas: ${body.altas.length} integrantes / ${gruposA} grupos`);
  console.log(`bajas: ${body.bajas.length} integrantes / ${gruposB} grupos`);
  console.log('ejemplos alta:', JSON.stringify(body.altas.slice(0, 3), null, 1));
  console.log('ejemplos baja:', JSON.stringify(body.bajas.slice(0, 3), null, 1));
})().catch((e) => {
  console.error(e.message);
  process.exit(1);
});
