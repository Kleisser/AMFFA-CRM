# Puente GECROS (API de solo lectura)

API mínima que expone SOLO la consulta de afiliados de la BBDD GECROS (SQL Server) hacia el CRM. Corre **dentro de la red AMFFA** y se publica con **Cloudflare Tunnel**: la conexión es saliente desde la red, no se abre ningún puerto, y la base de producción nunca queda expuesta.

```
GECROS (SQL Server) ← SELECT (usuario read-only) ← [puente:3000] ← cloudflared ← HTTPS ← CRM (Railway)
```

## Qué hace

Un único endpoint útil, read-only:

```
GET /afiliado?numero=12345678        → JSON con afiliado + grupo familiar + plan
GET /altas-bajas?desde=2026-06-26&hasta=2026-07-25   → altas y bajas del período
GET /health                          → estado del puente
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

## Instalación (en la máquina de la red AMFFA)

Requiere Node.js 18+ (https://nodejs.org).

```bash
cd gecros-bridge
npm install
cp .env.example .env
# editar .env con: servidor SQL, BBDD, usuario read-only, API key
npm start
```

Probar local: `curl "http://localhost:3000/afiliado?numero=12345678" -H "X-API-Key: TU_CLAVE"`

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

## Publicar con Cloudflare Tunnel

1. Instalar cloudflared (https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/)
2. Login y crear túnel:
   ```bash
   cloudflared tunnel login
   cloudflared tunnel create crm-bridge
   ```
3. Configurar `~/.cloudflared/config.yml`:
   ```yaml
   tunnel: crm-bridge
   credentials-file: C:\Users\TU_USUARIO\.cloudflared\<id-del-tunel>.json

   ingress:
     - hostname: gecros.amffa.com.ar   # o el subdominio que uses
       service: http://localhost:3000
     - service: http_status:404
   ```
4. DNS (CNAME al túnel) y lanzar:
   ```bash
   cloudflared tunnel route dns crm-bridge gecros.amffa.com.ar
   cloudflared tunnel run crm-bridge
   ```
5. (Recomendado) Activar **Cloudflare Access** sobre ese hostname para exigir login corporativo además de la API key.

## Configurar el CRM

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

- El puente NO expone tablas ni permite escritura: solo `GET /afiliado`
- La BBDD solo es alcanzable desde la máquina del puente
- La API key del puente y la del CRM deben ser largas y aleatorias (`openssl rand -hex 32`)
- No commitear el `.env` del puente
- Verificar los valores reales de `dbo.planes.plan_nombre` y mapearlos a los planes del CRM (START, BALANCE, PLATA, ORO, SENIOR, FAMILY, FAMILY PROMO, GO) en `src/gecros.js`
