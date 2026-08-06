'use strict';
const KEY = process.env.KEY || 'clave_test_gecros_2026';
const { execSync } = require('child_process');
(async () => {
  const res = await fetch('http://localhost:3000/health', { headers: { 'X-API-Key': KEY } });
  console.log('bridge status:', res.status);
})().catch((e) => { console.error(e.message); process.exit(1); });
