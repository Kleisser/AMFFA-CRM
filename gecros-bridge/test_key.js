const http = require('http');

const key = process.env.KEY || '';
function call(path, headers = {}) {
  return new Promise((resolve) => {
    http.get('http://localhost:3000' + path, { headers }, (res) => {
      let d = '';
      res.on('data', (c) => (d += c));
      res.on('end', () => resolve({ status: res.statusCode, body: d.slice(0, 120) }));
    }).on('error', (e) => resolve({ status: 0, body: e.message }));
  });
}

(async () => {
  const r1 = await call('/health', { 'X-API-Key': key });
  console.log('health con clave nueva:', r1.status);
  const r2 = await call('/health', { 'X-API-Key': 'clave_test_gecros_2026' });
  console.log('health con clave vieja:', r2.status, '(esperado 401)');
  const r3 = await call('/vendedores', { 'X-API-Key': key });
  console.log('vendedores con clave nueva:', r3.status);
})();
