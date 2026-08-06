'use strict';

const dni = process.argv[2] || '19004591';
const KEY = process.env.KEY || 'clave_test_gecros_2026';

(async () => {
  const res = await fetch(`http://localhost:3000/afiliado?dni=${dni}`, {
    headers: { 'X-API-Key': KEY },
  });
  console.log('status:', res.status);
  console.log(JSON.stringify(await res.json(), null, 2));
})().catch((e) => {
  console.error(e.message);
  process.exit(1);
});
