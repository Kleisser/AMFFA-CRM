'use strict';

const express = require('express');
const crypto = require('crypto');
const config = require('./config');
const GecrosRepository = require('./gecros');

const app = express();
const repo = new GecrosRepository();

const rateLimit = new Map();
const RATE_LIMIT_MAX = 60;
const RATE_LIMIT_WINDOW_MS = 60 * 1000;

// Middleware: la petición debe venir con la API key correcta.
app.use((req, res, next) => {
  const provided = req.header('X-API-Key') || req.query.api_key;

  if (!config.apiKey || !provided || provided !== config.apiKey) {
    return res.status(401).json({ error: 'API key inválida' });
  }

  // Rate limit simple por IP (evita abuso si la URL se filtra).
  const ip = req.ip;
  const now = Date.now();
  const entry = rateLimit.get(ip);
  if (!entry || now - entry.resetAt > RATE_LIMIT_WINDOW_MS) {
    rateLimit.set(ip, { count: 1, resetAt: now + RATE_LIMIT_WINDOW_MS });
  } else {
    entry.count += 1;
    if (entry.count > RATE_LIMIT_MAX) {
      return res.status(429).json({ error: 'Demasiadas consultas' });
    }
  }

  next();
});

app.get('/health', (req, res) => {
  res.json({ status: 'ok' });
});

app.get('/afiliado', async (req, res) => {
  const dni = String(req.query.dni || req.query.numero || '').trim();

  if (!dni || !/^\d{6,9}$/.test(dni)) {
    return res.status(400).json({ error: 'Número de documento inválido' });
  }

  try {
    const data = await repo.findByNumero(dni);

    if (!data) {
      return res.json({ found: false, dni });
    }

    res.json(data);
  } catch (err) {
    console.error('[gecros] error consultando:', err.message);
    res.status(502).json({ error: 'Error interno consultando el sistema' });
  }
});

app.get('/altas-bajas', async (req, res) => {
  const desde = String(req.query.desde || '').trim();
  const hasta = String(req.query.hasta || '').trim();

  if (!/^\d{4}-\d{2}-\d{2}$/.test(desde) || !/^\d{4}-\d{2}-\d{2}$/.test(hasta)) {
    return res.status(400).json({ error: 'Formato inválido. Usar desde=YYYY-MM-DD&hasta=YYYY-MM-DD' });
  }

  const rangoDias = (new Date(hasta) - new Date(desde)) / 86400000;
  if (rangoDias < 0 || rangoDias > 370) {
    return res.status(400).json({ error: 'Rango de fechas inválido (máximo 12 meses)' });
  }

  try {
    res.json(await repo.findAltasBajas(desde, hasta));
  } catch (err) {
    console.error('[gecros] error altas-bajas:', err.message);
    res.status(502).json({ error: 'Error interno consultando el sistema' });
  }
});

app.get('/vendedores', async (req, res) => {
  try {
    res.json({ vendedores: await repo.listVendedores() });
  } catch (err) {
    console.error('[gecros] error vendedores:', err.message);
    res.status(502).json({ error: 'Error interno consultando el sistema' });
  }
});

app.get('/venafi-por-dni', async (req, res) => {
  try {
    res.json({ pares: await repo.listVendedoresPorDni() });
  } catch (err) {
    console.error('[gecros] error venafi-por-dni:', err.message);
    res.status(502).json({ error: 'Error interno consultando el sistema' });
  }
});

// Registro mínimo en consola de cada consulta (auditoría).
app.use((req, res, next) => {
  res.on('finish', () => {
    console.log(
      JSON.stringify({
        ts: new Date().toISOString(),
        ip: req.ip,
        method: req.method,
        path: req.path,
        query: req.query.dni || req.query.numero || '',
        status: res.statusCode,
      })
    );
  });
  next();
});

app.listen(config.port, () => {
  console.log(`Puente GECROS escuchando en el puerto ${config.port}`);
  console.log(`Conectando a SQL Server ${config.db.server}:${config.db.port}/${config.db.database}`);
});
