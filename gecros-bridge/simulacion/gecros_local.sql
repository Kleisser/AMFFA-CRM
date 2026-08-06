-- ============================================================
-- Simulación local de la BBDD GECROS para probar el puente.
-- Esquema espejo del real (verificado contra producción 2026-08):
--   dbo.Benef: numero char(15) con espacios, doc_id = DNI (bigint),
--              par_id (2=Titular,3=Conyuge,4=Hijo/a), ben_gr_id (grupo
--              familiar), fechanac, fecha_alta.
--   dbo.BenefCambio: tcambio_id (15=estado, 6=plan, 3=baja, 18=baja
--              temporal), bc_datonue, bc_fecha.
-- ============================================================
IF DB_ID('GECROS') IS NULL
    CREATE DATABASE GECROS;
GO

USE GECROS;
GO

IF OBJECT_ID('dbo.BenefCambio') IS NOT NULL DROP TABLE dbo.BenefCambio;
IF OBJECT_ID('dbo.Benef') IS NOT NULL DROP TABLE dbo.Benef;
IF OBJECT_ID('dbo.planes') IS NOT NULL DROP TABLE dbo.planes;
IF OBJECT_ID('dbo.parentescos') IS NOT NULL DROP TABLE dbo.parentescos;
IF OBJECT_ID('dbo.estadosBenef') IS NOT NULL DROP TABLE dbo.estadosBenef;
IF OBJECT_ID('dbo.vendedoresafi') IS NOT NULL DROP TABLE dbo.vendedoresafi;
GO

CREATE TABLE dbo.parentescos (
    par_id      INT PRIMARY KEY,
    par_nombre  VARCHAR(80) NOT NULL
);

CREATE TABLE dbo.estadosBenef (
    est_id      INT PRIMARY KEY,
    est_nombre  VARCHAR(80) NOT NULL
);

CREATE TABLE dbo.planes (
    plan_id     INT PRIMARY KEY,
    plan_nombre VARCHAR(80) NOT NULL
);

CREATE TABLE dbo.vendedoresafi (
    venafi_id      SMALLINT PRIMARY KEY,
    venafi_nombre  CHAR(50) NOT NULL,
    venafi_Cuit    NVARCHAR(20) NULL
);

CREATE TABLE dbo.Benef (
    ben_id     INT IDENTITY(1,1) PRIMARY KEY,
    ben_ape    VARCHAR(50) NOT NULL,
    ben_nom    VARCHAR(50) NOT NULL,
    numero     CHAR(15) NOT NULL,      -- nº de socio, char con espacios
    doc_id     BIGINT NOT NULL,        -- DNI
    par_id     SMALLINT NOT NULL,
    ben_gr_id  BIGINT NOT NULL,        -- grupo familiar
    venafi_id  SMALLINT NULL REFERENCES dbo.vendedoresafi (venafi_id),
    fechanac   DATETIME NULL,
    fecha_alta DATETIME NULL
);
CREATE INDEX ix_benef_doc ON dbo.Benef (doc_id);
CREATE INDEX ix_benef_gr  ON dbo.Benef (ben_gr_id);

CREATE TABLE dbo.BenefCambio (
    bc_id      INT IDENTITY(1,1) PRIMARY KEY,
    ben_id     INT NOT NULL REFERENCES dbo.Benef (ben_id),
    tcambio_id SMALLINT NOT NULL,
    plan_id    INT NULL REFERENCES dbo.planes (plan_id),
    bc_datonue VARCHAR(50) NULL,
    bc_fecha   DATETIME NOT NULL
);
CREATE INDEX ix_bc_ben ON dbo.BenefCambio (ben_id, bc_fecha DESC, bc_id DESC);

INSERT INTO dbo.parentescos (par_id, par_nombre) VALUES
    (1, 'S/D'), (2, 'TITULAR'), (3, 'CONYUGE'), (4, 'HIJO/A'), (5, 'FAMILIAR A CARGO');

INSERT INTO dbo.estadosBenef (est_id, est_nombre) VALUES
    (1, 'Con Cobertura'), (2, 'Sin Cobertura por Baja'),
    (3, 'Momentaneamente sin Cobertura'), (4, 'Sin Cob. En Gestion Judicial');

INSERT INTO dbo.planes (plan_id, plan_nombre) VALUES
    (0,  'SIN DATOS'),   (36, 'PREMIUM PLATA'), (37, 'INTEGRAL (AD. C/M)'),
    (39, 'INTEGRAL (AD. S/M)'), (46, 'REGIONAL'), (47, 'SOCIAL'),
    (51, 'SENIOR'),      (53, 'BALANCE'),  (54, 'LITE'),
    (55, 'PREMIUM ORO'), (57, 'START'),    (58, 'RECIPROCIDAD'),
    (68, 'GO+'),         (69, 'FAMILY');

-- Vendedores de afiliación (venafi_id reales de producción)
INSERT INTO dbo.vendedoresafi (venafi_id, venafi_nombre, venafi_Cuit) VALUES
    (63,  'ORTIZ GLADYS LEONOR',        '27210722055'),
    (70,  'ANZELMO IGNACIO ANDRES',     '20296839311'),
    (73,  'PISCINNE VALENTINA',         '27445931360'),
    (86,  'PERTA CRISTIAN',             '20317810181'),
    (135, 'MARIA DETRY',                '27288642503'),
    (137, 'CASCO MATEO',                '20453029175'),
    (138, 'BRIZUELA PAOLA',             '27406687096'),
    (139, 'BEREDO ANDREA VANESA',       '27346130461'),
    (140, 'BENITEZ GONZALEZ LORENA',    '27944205514'),
    (141, 'INFRAN GASTON',              '20338128593'),
    (142, 'ROJAS MICAELA',              '23428217764'),
    (143, 'TASLAK JESSICA',             '23261015544'),
    (145, 'FRANCO ROMINA',              '27286414392'),
    (148, 'FERREIRA VIVIANA',           '27303481546'),
    (150, 'BAZAN NATALIA',              '27342365677'),
    (153, 'PEÑA JUAN PABLO',            '20272799181');
GO

-- ============================================================
-- Datos de prueba
-- Cierres: feb=01-26..02-25, mar, abr, may, jun, jul=06-26..07-25
-- Resultado esperado: altas feb=1, mar=1, abr=1, may=1, jun=1, jul=1
--                     bajas jul=1 (LOPEZ). Baja temporal NO cuenta.
-- PEREZ tiene una baja precargada a futuro (2026-08-31) que NO debe
-- afectar su estado actual (valida el filtro bc_fecha <= hoy).
-- ============================================================

-- 1) GARCIA (30111222): titular + conyuge + hijo, alta jul/2026, PREMIUM PLATA, vende FRANCO
INSERT INTO dbo.Benef (ben_ape, ben_nom, numero, doc_id, par_id, ben_gr_id, venafi_id, fechanac, fecha_alta) VALUES
    ('GARCIA', 'JUAN',  '1000001', 30111222, 2, 10001, 145, '1984-03-15', '2026-07-10'),
    ('GARCIA', 'MARIA', '1000002', 30111223, 3, 10001, 145, '1986-07-20', '2026-07-10'),
    ('GARCIA', 'TOMAS', '1000003', 30111224, 4, 10001, 145, '2013-01-05', '2026-07-10');

-- 2) LOPEZ (27555444): titular + conyuge, alta abr/2026 PREMIUM ORO, baja jul/2026, vende FERREIRA
INSERT INTO dbo.Benef (ben_ape, ben_nom, numero, doc_id, par_id, ben_gr_id, venafi_id, fechanac, fecha_alta) VALUES
    ('LOPEZ', 'CARLOS', '1000100', 27555444, 2, 10002, 148, '1978-11-02', '2026-04-15'),
    ('LOPEZ', 'ANA',    '1000101', 27555445, 3, 10002, 148, '1980-05-30', '2026-04-15');

-- 3) PEREZ (33333888): sola, alta jun/2026 START + baja precargada a futuro, vende DETRY
INSERT INTO dbo.Benef (ben_ape, ben_nom, numero, doc_id, par_id, ben_gr_id, venafi_id, fechanac, fecha_alta) VALUES
    ('PEREZ', 'LUCIA', '1000200', 33333888, 2, 10003, 135, '1990-02-10', '2026-06-01');

-- 4) GOMEZ (29111999): titular + conyuge, alta feb/2026 SENIOR, vende ANZELMO
INSERT INTO dbo.Benef (ben_ape, ben_nom, numero, doc_id, par_id, ben_gr_id, venafi_id, fechanac, fecha_alta) VALUES
    ('GOMEZ',  'PEDRO', '1000300', 29111999, 2, 10004, 70, '1970-01-01', '2026-02-10'),
    ('GOMEZ',  'SILVIA','1000301', 29112000, 3, 10004, 70, '1975-06-15', '2026-02-10');

-- 5) RODRIGUEZ (31999888): sola, alta mar/2026 BALANCE, vende ORTIZ
INSERT INTO dbo.Benef (ben_ape, ben_nom, numero, doc_id, par_id, ben_gr_id, venafi_id, fechanac, fecha_alta) VALUES
    ('RODRIGUEZ', 'PABLO', '1000400', 31999888, 2, 10005, 63, '1988-08-08', '2026-03-20');

-- 6) FERNANDEZ (25222333): titular + conyuge, alta may/2026 FAMILY, baja TEMPORAL jul/2026, vende CASCO
INSERT INTO dbo.Benef (ben_ape, ben_nom, numero, doc_id, par_id, ben_gr_id, venafi_id, fechanac, fecha_alta) VALUES
    ('FERNANDEZ', 'LAURA', '1000500', 25222333, 2, 10006, 137, '1982-12-25', '2026-05-18'),
    ('FERNANDEZ', 'JORGE', '1000501', 25222334, 3, 10006, 137, '1981-04-11', '2026-05-18');

GO

-- Estado inicial: todos con cobertura al alta
INSERT INTO dbo.BenefCambio (ben_id, tcambio_id, plan_id, bc_datonue, bc_fecha)
SELECT b.ben_id, 15, NULL, '1', b.fecha_alta FROM dbo.Benef b;
GO

-- Plan al alta (tcambio 6) por grupo
INSERT INTO dbo.BenefCambio (ben_id, tcambio_id, plan_id, bc_datonue, bc_fecha)
SELECT b.ben_id, 6, p.plan_id, NULL, b.fecha_alta
FROM dbo.Benef b
JOIN dbo.planes p ON p.plan_nombre = CASE b.ben_gr_id
    WHEN 10001 THEN 'PREMIUM PLATA'
    WHEN 10002 THEN 'PREMIUM ORO'
    WHEN 10003 THEN 'START'
    WHEN 10004 THEN 'SENIOR'
    WHEN 10005 THEN 'BALANCE'
    WHEN 10006 THEN 'FAMILY'
END;
GO

-- Baja definitiva LOPEZ jul/2026
INSERT INTO dbo.BenefCambio (ben_id, tcambio_id, plan_id, bc_datonue, bc_fecha)
SELECT b.ben_id, 3, NULL, NULL, '2026-07-20'
FROM dbo.Benef b WHERE b.ben_gr_id = 10002;
GO

-- Baja temporal FERNANDEZ jul/2026 (NO debe contar como baja)
INSERT INTO dbo.BenefCambio (ben_id, tcambio_id, plan_id, bc_datonue, bc_fecha)
SELECT b.ben_id, 18, NULL, NULL, '2026-07-28'
FROM dbo.Benef b WHERE b.ben_gr_id = 10006;
GO

-- Baja precargada a futuro de PEREZ (2026-08-31): no afecta el estado hoy
INSERT INTO dbo.BenefCambio (ben_id, tcambio_id, plan_id, bc_datonue, bc_fecha)
SELECT b.ben_id, 15, NULL, '2', '2026-08-31' FROM dbo.Benef b WHERE b.ben_gr_id = 10003;
INSERT INTO dbo.BenefCambio (ben_id, tcambio_id, plan_id, bc_datonue, bc_fecha)
SELECT b.ben_id, 3, NULL, NULL, '2026-08-31' FROM dbo.Benef b WHERE b.ben_gr_id = 10003;
GO

-- ============================================================
-- Login de solo lectura del puente (igual que en producción)
-- ============================================================
IF EXISTS (SELECT 1 FROM sys.database_principals WHERE name = 'crm_bridge')
    DROP USER crm_bridge;
GO

IF EXISTS (SELECT 1 FROM sys.server_principals WHERE name = 'crm_bridge')
    DROP LOGIN crm_bridge;
GO

CREATE LOGIN crm_bridge WITH PASSWORD = 'Bridge2026!';
CREATE USER crm_bridge FOR LOGIN crm_bridge;
GRANT SELECT ON dbo.Benef TO crm_bridge;
GRANT SELECT ON dbo.BenefCambio TO crm_bridge;
GRANT SELECT ON dbo.planes TO crm_bridge;
GRANT SELECT ON dbo.parentescos TO crm_bridge;
GRANT SELECT ON dbo.estadosBenef TO crm_bridge;
GRANT SELECT ON dbo.vendedoresafi TO crm_bridge;
-- OJO: NO usar DENY CONTROL ON SCHEMA (bloquea también los SELECT
-- concedidos). Denegar solo escritura:
DENY INSERT, UPDATE, DELETE, ALTER ON SCHEMA::dbo TO crm_bridge;
GO

PRINT 'SIMULACION GECROS LISTA';
SELECT ben_gr_id, COUNT(*) AS integrantes FROM dbo.Benef GROUP BY ben_gr_id ORDER BY ben_gr_id;
GO
