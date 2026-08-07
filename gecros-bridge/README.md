# Puente GECROS (API de solo lectura)

API mínima que expone SOLO la consulta de afiliados de la BBDD GECROS (SQL Server) hacia el CRM. Corre **fuera del hosting del CRM** (máquina en la red AMFFA, VPS o servicio tipo Render/Railway) y se publica por HTTPS: el CRM jamás ve la base de GECROS, solo consumen esta API.

```
GECROS (SQL Server) ← SELECT (usuario read-only) ← [puente:3000] ← HTTPS ← CRM (Railway hoy, Neolo mañana)
```

El CRM se conecta solo con `GECROS_BRIDGE_URL` + `GECROS_BRIDGE_KEY` (ver más abajo). Si mañana el puente deja de leer SQL y pasa a llamar un Web Service de WS_Activia, el CRM no cambia nada.

## Qué hace

Endpoints read-only:

```
GET /health                           → estado del puente + conectividad con la DB (público, sin API key)
GET /afiliado?numero=12345678        → JSON con afiliado + grupo familiar + plan
GET /altas-bajas?desde=2026-06-26&hasta=2026-07-25   → altas y bajas del período
GET /vendedores                      → catálogo de vendedores de afiliación
GET /venafi-por-dni                  → mapa DNI → venafi_id
```

- Solo acepta DNI numérico (6-9 dígitos)
- Requiere API key en el header `X-API-Key` (misma clave que el CRM envía)
- Rate limit 60 consultas/minuto por IP
- Log de auditoría en consola por consulta (IP, DNI, hora)
- Usuario SQL dedicado con permisos **solo de SELECT**

## Altas y bajas por mes

El CRM arma el KPI de "Altas y Bajas" consultando `/altas-bajas` con el período del **cierre mensual (día 26 del mes anterior al 25 del mes)**:

- **Altas**: filas de `dbo.Benef` con `fecha_alta` dentro del período (plan vigente al cierre del período)
- **Bajas**: filas de `dbo.Benef` con una baja definitiva (`tcambio_id = 3`) en el período (plan vigente al momento de la baja)
- Se devuelve **una fila por integrante** (el grupo familiar comparte `numero`); el CRM cuenta por `numero` distinto para medir afiliaciones
- Las bajas temporales (`tcambio_id = 18`) NO se cuentan como bajas

## Cómo determina plan, edad y grupo familiar

Esquema real de GECROS (verificado contra código existente del cliente):

| Tabla | Columnas relevantes |
|---|---|
| `dbo.Benef` | `ben_id`, `numero` (DNI), `ben_ape`, `ben_nom`, `fecha_alta` (+ `fecha_nacimiento`, `parentesco` si existen) |
| `dbo.BenefCambio` | `ben_id`, `bc_datonue`, `bc_fecha`, `bc_id`, `tcambio_id`, `plan_id` |
| `dbo.planes` | `plan_id`, `plan_nombre` |
| `dbo.tipomov` | `tcambio_id` 15=estado, 6=cambio plan, 3=baja, 18=baja temporal |

- **Grupo familiar**: todas las filas de `dbo.Benef` con el mismo `numero` (cada fila = un integrante)
- **Plan vigente**: última fila de `BenefCambio` con `plan_id NOT NULL` y `bc_fecha <= hoy` (mismo criterio que el script de riesgo de fuga: `ORDER BY bc_fecha DESC, bc_id DESC`)
- **Estado**: última fila de `BenefCambio` con `tcambio_id = 15`; `bc_datonue = 1` → activo
- **Baja**: existe una fila con `tcambio_id IN (3, 18)`
- **Edad**: calculada desde `fecha_nacimiento` si la columna existe (se detecta automáticamente al iniciar; si no existe, `edad: null` sin romper la consulta)

Respuesta del endpoint:

```json
{
  "found": true,
  "numero": "12345678",
  "afiliado": { "apellido": "...", "nombre": "...", "fecha_alta": "...", "activo": true, "tiene_baja": false },
  "plan": { "nombre": "...", "planes_del_grupo": ["..."] },
  "grupo_familiar": [
    { "ben_id": 1, "numero": "12345678", "apellido": "...", "nombre": "...", "parentesco": "...", "edad": 42 }
  ],
  "consultado_el": "..."
}
```

## Instalación (desarrollo local)

Requiere Node.js 18+ (https://nodejs.org).

```bash
cd gecros-bridge
npm install
cp .env.example .env
# editar .env con: servidor SQL, BBDD, usuario read-only, API key
npm start
```

Probar local: `curl "http://localhost:3000/afiliado?numero=12345678" -H "X-API-Key: TU_CLAVE"`

## Deploy standalone (Docker)

El puente es un servicio independiente del CRM. Hay una imagen lista en `gecros-bridge/Dockerfile` (Node 20-alpine, `npm ci`, HEALTHCHECK contra `/health`).

### Opción A — Servidor propio / VPS de AMFFA (recomendado)

El requisito clave: **el host del puente debe poder alcanzar el SQL Server de GECROS** (red AMFFA, VPN/WireGuard o túnel IT). Por eso lo natural es una máquina dentro de la red AMFFA o un VPS con VPN a esa red.

```bash
cd gecros-bridge
docker build -t gecros-bridge .
docker run -d --name gecros-bridge --restart unless-stopped -p 3000:3000 \
  -e BRIDGE_API_KEY=<openssl rand -hex 32> \
  -e DB_SERVER=127.0.0.1 \
  -e DB_PORT=1433 \
  -e DB_DATABASE=GECROS \
  -e DB_USER=crm_bridge \
  -e DB_PASSWORD=<password_read_only> \
  -e DB_ENCRYPT=false \
  gecros-bridge
```

Chequear: `curl http://localhost:3000/health` → `{"status":"ok","db":"ok"}`.

Exponerlo con **Cloudflare Tunnel** (conexión saliente, no se abre puerto en el firewall):

```bash
cloudflared tunnel login
cloudflared tunnel create crm-bridge
# config.yml → hostname: gecros.amffa.com.ar → service: http://localhost:3000
cloudflared tunnel route dns crm-bridge gecros.amffa.com.ar
cloudflared tunnel run crm-bridge
```

### Opción B — Render/Railway (cloud)

1. Subir el repo a GitHub (ya está) y crear un servicio desde el `Dockerfile` de `gecros-bridge`.
2. Variables de entorno: `BRIDGE_API_KEY`, `DB_SERVER`, `DB_PORT`, `DB_DATABASE`, `DB_USER`, `DB_PASSWORD`, `DB_ENCRYPT`.
3. OJO con la conectividad: el SQL de GECROS debe ser alcanzable desde la nube (VPN/WireGuard al SQL, o tunnel reverso del SQL hacia un server intermedio). Si no, esta opción no aplica y va la A.
4. En Render los planes gratuitos duermen tras inactividad: usar plan pagado o la opción A.

### Opción C — Solo desarrollo (compose actual)

En `docker-compose.yml` del repo el puente sigue corriendo dentro del contenedor `node` (puerto 3000 interno). No tocar: es solo para desarrollo local.

## Crear el usuario SQL de solo lectura

```sql
USE GECROS;
CREATE LOGIN crm_bridge WITH PASSWORD = 'una_password_fuerte';
CREATE USER crm_bridge FOR LOGIN crm_bridge;
GRANT SELECT ON dbo.Benef TO crm_bridge;
GRANT SELECT ON dbo.BenefCambio TO crm_bridge;
GRANT SELECT ON dbo.planes TO crm_bridge;
-- OJO: NO usar "DENY ... CONTROL ON SCHEMA" (bloquea también los SELECT
-- concedidos). Denegar solo escritura:
DENY INSERT, UPDATE, DELETE, ALTER ON SCHEMA::dbo TO crm_bridge;
```

## Configurar el CRM (Railway hoy, Neolo mañana)

En Railway → Variables de entorno del backend:

```
GECROS_BRIDGE_URL=https://gecros.amffa.com.ar
GECROS_BRIDGE_KEY=<misma API key del puente>
```

Pasos desde el dashboard de Railway:
1. Abrir el proyecto del backend → **Variables** → agregar `GECROS_BRIDGE_URL` (URL HTTPS del túnel, ej. `https://gecros.amffa.com.ar`) y `GECROS_BRIDGE_KEY` (la API key generada con `openssl rand -hex 32`).
2. **Redeploy** del servicio. No hay que tocar código: el backend lee esas variables en `config/services.php`.
3. Verificar en **Deployments → logs** que arranca sin errores y luego probar el login y la pantalla Altas y Bajas.
4. (Opcional, recomendado) Activar **Cloudflare Access** con un Service Token sobre el hostname del túnel: solo el backend (que envía el header `CF-Access-Client-Id/Secret`... si se usa Access) o la API key `X-API-Key` permiten pasar. Al menos mantener la API key larga y aleatoria.

> El login read-only `crm_bridge` en el SQL Server real lo crea el equipo de IT con el script `crear_login_crm_bridge.sql` (SELECT únicamente sobre las 4 tablas que consulta el puente). Hasta entonces el puente usa una cuenta con más permisos: reemplazar en `gecros-bridge/.env` `DB_USER`/`DB_PASSWORD` apenas esté creado.

> **Migración a Neolo**: el CRM deja de correr en Railway y pasa a Neolo; el puente NO cambia. Solo se copian `GECROS_BRIDGE_URL` y `GECROS_BRIDGE_KEY` a las variables de entorno del nuevo hosting. El puente sigue fuera del hosting (opción A o B), así Neolo nunca ve la base de GECROS.

## Probar en local (simulación completa)

Se puede simular GECROS con SQL Server 2022 en Docker y probar todo el flujo sin tocar la base real. Así se hizo la validación de este puente:

```bash
# 1. SQL Server simulado (mismo esquema + datos de prueba con familias y cierres)
docker run -d --name gecros-sql --network amffa-crm_default \
  -e ACCEPT_EULA=Y -e "MSSQL_SA_PASSWORD=Simulacion2026!" -e MSSQL_PID=Developer \
  -p 1434:1433 mcr.microsoft.com/mssql/server:2022-latest

# 2. Esperar a que responda, luego crear esquema + datos + login read-only
docker exec gecros-sql /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "Simulacion2026!" -C \
  -i /tmp/gecros_local.sql   # (copiar antes simulacion/gecros_local.sql al contenedor)

# 3. Configurar y correr el puente (cp .env.local -> .env, ajustar servidor a "gecros-sql")
cd gecros-bridge && npm install && node src/server.js

# 4. Conectar el CRM local: en backend/.env
GECROS_BRIDGE_URL=http://node:3000
GECROS_BRIDGE_KEY=clave_test_gecros_2026

# 5. (Opcional) Probar el túnel real de Cloudflare sin dominio:
docker run --rm --network amffa-crm_default cloudflare/cloudflared:latest tunnel --no-autoupdate --url http://node:3000
# copiar la URL https://...trycloudflare.com que imprime a GECROS_BRIDGE_URL y reiniciar el app
```

El script `simulacion/gecros_local.sql` incluye los mismos permisos de solo lectura de producción y datos de prueba (grupos familiares con `numero` compartido, altas en varios cierres, una baja definitiva y una baja temporal que no debe contar).

## Importante (seguridad)

- El puente NO expone tablas ni permite escritura: solo GET de negocio (`/afiliado`, `/altas-bajas`, `/vendedores`, `/venafi-por-dni`) y `/health` público
- La BBDD solo es alcanzable desde la máquina del puente (VPS propio con VPN/túnel IT, o máquina en la red AMFFA)
- La API key del puente y la del CRM deben ser largas y aleatorias (`openssl rand -hex 32`)
- No commitear el `.env` del puente
- Verificar los valores reales de `dbo.planes.plan_nombre` y mapearlos a los planes del CRM (START, BALANCE, PLATA, ORO, SENIOR, FAMILY, FAMILY PROMO, GO) en `src/gecros.js`
