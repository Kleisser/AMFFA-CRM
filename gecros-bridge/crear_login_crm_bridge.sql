-- ============================================================
-- Login de SOLO LECTURA para el puente GECROS (crm_bridge)
-- Ejecutar en el SQL Server de producción (180.120.0.191) con
-- una cuenta con permisos de administrador (sysadmin/securityadmin).
--
-- PASO 1: reemplazar 'CambiarPorClaveFuerte2026#' por una clave
--         larga y aleatoria ANTES de ejecutar.
-- PASO 2: avisar al equipo para cargar la misma clave en
--         gecros-bridge/.env (DB_PASSWORD) y probar el puente.
-- ============================================================

USE [master];
GO

-- El login se puede recrear si se pierde la clave (no borra el user).
IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = 'crm_bridge')
BEGIN
    CREATE LOGIN [crm_bridge]
        WITH PASSWORD = 'CambiarPorClaveFuerte2026#',
             CHECK_POLICY = ON,
             CHECK_EXPIRATION = OFF;
END
GO

USE [Gecros];
GO

IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = 'crm_bridge')
BEGIN
    CREATE USER [crm_bridge] FOR LOGIN [crm_bridge];
END
GO

-- Únicamente SELECT sobre las tablas que consulta el puente.
-- (El puente NO escribe nada: solo consulta de afiliados/altas/bajas.)
GRANT SELECT ON [dbo].[Benef] TO [crm_bridge];
GRANT SELECT ON [dbo].[BenefCambio] TO [crm_bridge];
GRANT SELECT ON [dbo].[planes] TO [crm_bridge];
GRANT SELECT ON [dbo].[vendedoresafi] TO [crm_bridge];
GO

-- Verificación rápida:
--   SELECT TOP 1 * FROM dbo.Benef;  (debe devolver filas)
--   UPDATE dbo.Benef SET ben_ape = ben_ape;  (debe dar error de permiso)
