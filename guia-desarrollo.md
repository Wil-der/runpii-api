# 📦 DOCUMENTACIÓN TÉCNICA — Plataforma de Mensajería Bajo Demanda
> **Backend:** PHP 8.3 + Symfony 7.1 LTS + Doctrine ORM 3.x + MySQL 8.x  
> **Real-time:** Mercure Hub (SSE)  
> **Mapas / Geocodificación:** OpenStreetMap + Nominatim + OSRM (open-source, sin coste de API)  
> **Caché:** Redis 7.x  
> **Despliegue:** VPS único · Docker Compose  
> **Versión 2.0 — Arquitectura Modular orientada a futura migración a Microservicios**

---

## 📑 Tabla de Contenidos

1. [Decisiones de Arquitectura y Cambios respecto a v1.0](#1-decisiones-de-arquitectura-y-cambios-respecto-a-v10)
2. [Arquitectura por Módulos](#2-arquitectura-por-módulos)
3. [Stack Tecnológico Completo](#3-stack-tecnológico-completo)
4. [Modelo de Base de Datos (DDL SQL)](#4-modelo-de-base-de-datos-ddl-sql)
5. [Real-time con Mercure](#5-real-time-con-mercure)
6. [Mapas y Geocodificación Open-Source](#6-mapas-y-geocodificación-open-source)
7. [Gestión de Caché y Reducción de Peticiones Externas](#7-gestión-de-caché-y-reducción-de-peticiones-externas)
8. [Diagramas de Flujo (Mermaid)](#8-diagramas-de-flujo-mermaid)
9. [Endpoints API REST Completos](#9-endpoints-api-rest-completos)
10. [Cron Jobs y Symfony Messenger](#10-cron-jobs-y-symfony-messenger)
11. [Consideraciones de Seguridad](#11-consideraciones-de-seguridad)
12. [Deudas Técnicas Resueltas](#12-deudas-técnicas-resueltas)
13. [Historias de Usuario con Estimación](#13-historias-de-usuario-con-estimación)
14. [Plan de Pruebas](#14-plan-de-pruebas)
15. [Infraestructura y Despliegue](#15-infraestructura-y-despliegue)

---

## 1. Decisiones de Arquitectura y Cambios respecto a v1.0

### 1.1 Por qué Symfony 7.1 en lugar de 6.4

Symfony 7.1 (LTS hasta noviembre 2027) incorpora mejoras nativas de rendimiento en el contenedor de dependencias, soporte completo para PHP 8.3 (readonly classes, typed constants) y una integración más limpia con Mercure 0.16+. El coste de migración desde 6.4 es mínimo: los cambios breaking afectan principalmente a APIs internas del framework, no a las capas de aplicación que nosotros escribimos.

### 1.2 Tabla de decisiones clave

| # | Aspecto | Decisión v1.0 | Decisión v2.0 | Justificación |
|---|---------|--------------|--------------|---------------|
| 1 | Real-time | Polling HTTP cada 3–10s | **Mercure SSE** (push del servidor) | Elimina miles de peticiones/min; un VPS con 100–500 usuarios saturaba con polling |
| 2 | Geocodificación | Sin servicio definido | **Nominatim** (OSM) self-hosted + caché Redis | Cero coste, sin límite de peticiones, datos cubanos actualizados en OSM |
| 3 | Cálculo de rutas | Haversine en PHP | **OSRM** self-hosted (motor de rutas real) | Devuelve tiempo y distancia real por calles, no línea recta |
| 4 | Mapas frontend | No definido | **Leaflet.js** + tiles OSM/self-hosted | Open-source, sin API key, funciona offline si se cachean tiles |
| 5 | Caché | Redis opcional | **Redis obligatorio** + estrategia multicapa | Absorbe lecturas de ubicación, configuraciones y geocoding |
| 6 | Mensajería async | Cron cada 3 min | **Symfony Messenger** + worker persistente | Reasignación en segundos, no en minutos; jobs desacoplados |
| 7 | Arquitectura | Monolito plano | **Módulos Symfony** con boundaries claros | Permite extraer microservicios sin reescribir lógica |
| 8 | JWT logout | Sin blacklist | **Redis blacklist** con TTL = TTL del token | Deuda técnica v1.0 resuelta |
| 9 | Datos sensibles | Texto plano en DB | **AES-256-GCM** en capa de aplicación (Doctrine listener) | Tarjeta bancaria y CI cifrados en reposo |
| 10 | Tracking ubicación | INSERT cada 10s en DB | **Redis ZSET** para última posición + DB batch 60s | 6× menos escrituras en MySQL |

### 1.3 Suposiciones de negocio mantenidas

Todas las suposiciones de la v1.0 se mantienen (moneda CUP, pago manual Transfermóvil/Enzona, verificación manual de mensajeros, un envío activo por mensajero, bloqueo por 3 rechazos, retención 7 días de fotos y chat). La v2.0 no cambia el modelo de negocio, solo la arquitectura técnica.

---

## 2. Arquitectura por Módulos

La aplicación se organiza en módulos Symfony dentro de un único repositorio. Cada módulo es autónomo: tiene sus propias entidades, repositorios, servicios, controladores y tests. La comunicación entre módulos se hace **exclusivamente a través de interfaces de servicio o eventos del bus de mensajes**, nunca importando entidades de otro módulo directamente.

Este boundary es el que permite en el futuro separar cada módulo en un microservicio independiente con su propia base de datos, sin reescribir la lógica de negocio.

```
src/
├── Auth/                      # Módulo: Autenticación y usuarios
│   ├── Controller/
│   ├── Entity/                # Usuario, RefreshToken, PasswordResetToken
│   ├── Repository/
│   ├── Service/               # AuthService, PasswordResetService
│   ├── Security/              # JwtAuthenticator, Voter base
│   ├── Event/                 # UserRegisteredEvent, UserLoggedOutEvent
│   └── Tests/
│
├── Mensajero/                 # Módulo: Perfil y disponibilidad del mensajero
│   ├── Controller/
│   ├── Entity/                # Mensajero
│   ├── Repository/
│   ├── Service/               # MensajeroService, DisponibilidadService
│   ├── Event/                 # MensajeroAprobadoEvent, MensajeroBloquedoEvent
│   └── Tests/
│
├── Envio/                     # Módulo: Ciclo de vida del envío (core del negocio)
│   ├── Controller/
│   ├── Entity/                # Envio, FotoEnvio, Calificacion
│   ├── Repository/
│   ├── Service/               # EnvioService, AsignacionService, CalificacionService
│   ├── StateMachine/          # Symfony Workflow para estados del envío
│   ├── Message/               # ReasignarEnvioMessage, NotificarEnvioMessage
│   ├── MessageHandler/
│   ├── Event/                 # EnvioAsignadoEvent, EnvioEntregadoEvent
│   └── Tests/
│
├── Tracking/                  # Módulo: Ubicación en tiempo real
│   ├── Controller/
│   ├── Entity/                # UbicacionMensajero (solo persistencia batch)
│   ├── Repository/
│   ├── Service/               # TrackingService, MercurePublisher
│   ├── Cache/                 # UbicacionCacheRepository (Redis ZSET)
│   └── Tests/
│
├── Chat/                      # Módulo: Mensajería entre participantes
│   ├── Controller/
│   ├── Entity/                # MensajeChat
│   ├── Repository/
│   ├── Service/               # ChatService, MercureChatPublisher
│   ├── Security/              # ChatVoter
│   └── Tests/
│
├── Pago/                      # Módulo: Gestión de comprobantes de pago
│   ├── Controller/
│   ├── Entity/                # Pago
│   ├── Repository/
│   ├── Service/               # PagoService
│   ├── Event/                 # PagoVerificadoEvent, PagoRechazadoEvent
│   └── Tests/
│
├── Geocoding/                 # Módulo: Geocodificación y rutas (open-source)
│   ├── Service/               # NominatimService, OsrmService, GeocodingCache
│   ├── Dto/                   # CoordenadasDto, RutaDto
│   └── Tests/
│
├── Admin/                     # Módulo: Panel de administración
│   ├── Controller/
│   ├── Service/               # ReporteService, ConfiguracionService
│   └── Tests/
│
└── Shared/                    # Kernel compartido (sin lógica de negocio)
    ├── Cache/                 # RedisCache wrapper, CacheKeyBuilder
    ├── Dto/                   # Respuestas genéricas, paginación
    ├── Event/                 # EventBus interface
    ├── Exception/             # DomainException base, ValidationException
    └── Validator/             # Validadores CI cubano, teléfono, Luhn tarjeta
```

### 2.1 Reglas de boundaries entre módulos

```
PERMITIDO:
  Envio → Mensajero    via MensajeroServiceInterface (inyectado)
  Envio → Tracking     via TrackingServiceInterface
  Envio → Pago         via PagoServiceInterface
  Chat  → Envio        via EnvioRepositoryInterface (solo lectura)
  cualquier módulo → Shared  (acceso libre)
  cualquier módulo → EventBus (publicar eventos)

PROHIBIDO:
  Módulo A importa Entity de Módulo B directamente
  Módulo A llama a Repository de Módulo B
  Comunicación circular entre módulos (A→B→A)
```

### 2.2 Camino hacia microservicios

Cuando el volumen lo requiera, la extracción sigue este orden de prioridad (el que más carga genera primero):

1. **Tracking** → servicio independiente con Redis propio (mayor frecuencia de escritura)
2. **Chat** → servicio con WebSocket/Mercure dedicado
3. **Geocoding** → servicio con caché compartida
4. **Envio** → núcleo, se extrae al final

---

## 3. Stack Tecnológico Completo

### 3.1 Software base

| Componente | Versión | Función |
|---|---|---|
| PHP | 8.3+ | Runtime principal |
| Symfony | 7.1 LTS | Framework |
| Doctrine ORM | 3.x | ORM + migraciones |
| MySQL | 8.0+ | Base de datos principal |
| Redis | 7.2+ | Caché, rate limiting, Mercure token store, JWT blacklist |
| Mercure Hub | 0.16+ | SSE push en tiempo real (tracking + chat) |
| OSRM | 5.27+ | Motor de rutas open-source (Docker) |
| Nominatim | 4.x | Geocodificación open-source (Docker) |
| Nginx | 1.26+ | Proxy inverso + servir archivos estáticos |
| PHP-FPM | 8.3+ | FastCGI para Symfony |
| Supervisor | 4.x | Gestión del worker de Symfony Messenger |
| Docker Compose | 2.x | Orquestación de todos los servicios en VPS |

### 3.2 Paquetes Composer

```bash
# Core Symfony
composer require symfony/framework-bundle
composer require symfony/security-bundle
composer require symfony/validator
composer require symfony/serializer
composer require symfony/rate-limiter
composer require symfony/workflow          # State machine para estados de envío
composer require symfony/messenger         # Bus de mensajes async

# API
composer require api-platform/core         # API Platform 3.x (opcional, para OpenAPI auto)
composer require lexik/jwt-authentication-bundle
composer require nelmio/api-doc-bundle

# Base de datos y caché
composer require doctrine/orm
composer require doctrine/doctrine-bundle
composer require doctrine/doctrine-migrations-bundle
composer require symfony/cache             # Cache component (adapters Redis, APCu)
composer require predis/predis             # Cliente Redis

# Mercure (real-time)
composer require symfony/mercure-bundle

# Geocodificación (cliente HTTP para Nominatim/OSRM self-hosted)
composer require symfony/http-client

# Archivos
composer require vich/uploader-bundle

# Paginación
composer require knplabs/knp-paginator-bundle

# Dev / Testing
composer require --dev symfony/test-pack
composer require --dev dama/doctrine-test-bundle
composer require --dev fakerphp/faker
composer require --dev zenstruck/foundry   # Factories de entidades para tests
composer require --dev phpstan/phpstan
composer require --dev symplify/easy-coding-standard
```

### 3.3 Variables de entorno (`.env.prod`)

```ini
APP_ENV=prod
APP_SECRET=<random_64_chars_hex>

# Base de datos
DATABASE_URL="mysql://mensajeria_user:StrongPassword@127.0.0.1:3306/mensajeria?serverVersion=8.0&charset=utf8mb4"

# JWT (RS256)
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<passphrase>
JWT_TTL=3600

# Redis
REDIS_URL=redis://127.0.0.1:6379
REDIS_PASSWORD=<redis_password>

# Mercure
MERCURE_URL=http://127.0.0.1:3000/.well-known/mercure
MERCURE_PUBLIC_URL=https://tu-dominio.cu/.well-known/mercure
MERCURE_JWT_SECRET=<mercure_jwt_secret_64chars>

# Geocodificación (self-hosted en el mismo VPS)
NOMINATIM_URL=http://127.0.0.1:8080
OSRM_URL=http://127.0.0.1:5000

# Uploads
UPLOAD_DIR=/var/www/mensajeria/var/uploads
FOTO_RETENCION_DIAS=7

# Cifrado de datos sensibles (AES-256-GCM)
ENCRYPTION_KEY=<hex_64_chars_generado_con_openssl_rand_hex_32>

# Messenger (worker)
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

---

## 4. Modelo de Base de Datos (DDL SQL)

> Motor: MySQL 8.x · Charset: `utf8mb4` · Collation: `utf8mb4_unicode_ci` · InnoDB en todas las tablas.

### 4.1 Tabla: `usuarios`

```sql
CREATE TABLE usuarios (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid        CHAR(36) NOT NULL UNIQUE,          -- UUID v4 para URLs públicas
    email       VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    tipo        ENUM('cliente','mensajero','destinatario','admin') NOT NULL,
    nombre      VARCHAR(120) NOT NULL,
    telefono    VARCHAR(30),
    activo      TINYINT(1) NOT NULL DEFAULT 1,
    device_token VARCHAR(255),                      -- FCM futuro
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo  (tipo),
    INDEX idx_uuid  (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Cambio v2.0:** Se añade columna `uuid` (CHAR 36) para exponer en URLs públicas en lugar del `id` entero autoincremental, evitando enumeración de recursos.

### 4.2 Tabla: `mensajeros`

```sql
CREATE TABLE mensajeros (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id            BIGINT UNSIGNED NOT NULL UNIQUE,
    ci_cifrado            VARBINARY(512) NOT NULL,          -- AES-256-GCM
    tarjeta_bancaria_cifrada VARBINARY(512) NOT NULL,       -- AES-256-GCM
    movil_pagos_cifrado   VARBINARY(512) NOT NULL,          -- AES-256-GCM
    foto_documento_path   VARCHAR(255),
    tipo_vehiculo         VARCHAR(60) NOT NULL DEFAULT 'moto',
    estado_aprobacion     ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
    disponible            TINYINT(1) NOT NULL DEFAULT 0,
    bloqueado_hasta       DATETIME,
    rechazos_consecutivos TINYINT NOT NULL DEFAULT 0,
    calificacion_promedio DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    total_envios          INT UNSIGNED NOT NULL DEFAULT 0,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mensajero_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_disponible_estado (disponible, estado_aprobacion),
    INDEX idx_estado_aprobacion (estado_aprobacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Cambio v2.0:** CI, tarjeta y móvil pasan de `VARCHAR` a `VARBINARY(512)` para almacenar el texto cifrado con AES-256-GCM. El cifrado/descifrado ocurre en el `DoctrineEncryptionListener` de la capa de aplicación.

### 4.3 Tabla: `envios`

```sql
CREATE TABLE envios (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid             CHAR(36) NOT NULL UNIQUE,
    cliente_id       BIGINT UNSIGNED NOT NULL,
    mensajero_id     BIGINT UNSIGNED,
    destinatario_usuario_id BIGINT UNSIGNED,

    -- Origen
    origen_direccion VARCHAR(255) NOT NULL,
    origen_lat       DECIMAL(10,7) NOT NULL,
    origen_lng       DECIMAL(10,7) NOT NULL,

    -- Destino
    destino_direccion VARCHAR(255) NOT NULL,
    destino_lat      DECIMAL(10,7) NOT NULL,
    destino_lng      DECIMAL(10,7) NOT NULL,

    -- Ruta calculada (OSRM, cacheada en Redis, guardada aquí al calcular)
    ruta_distancia_m INT UNSIGNED,                  -- metros
    ruta_duracion_s  SMALLINT UNSIGNED,             -- segundos estimados
    ruta_polyline    MEDIUMTEXT,                    -- polyline codificada (Leaflet)

    -- Destinatario libre
    dest_nombre      VARCHAR(120) NOT NULL,
    dest_telefono    VARCHAR(30) NOT NULL,

    -- Paquete
    descripcion      VARCHAR(500),
    peso_aprox_kg    DECIMAL(6,2),

    -- Tarifa
    precio_base           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    comision_plataforma   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ganancia_mensajero    DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    -- Estado (gestionado por Symfony Workflow)
    estado           ENUM(
        'pendiente','pagado','asignado','recogido',
        'en_camino','entregado','cancelado'
    ) NOT NULL DEFAULT 'pendiente',
    motivo_cancelacion VARCHAR(255),
    cancelado_por    ENUM('cliente','admin','sistema'),

    -- Tipo de envío
    tipo_envio       ENUM('inmediato','planificado') NOT NULL DEFAULT 'inmediato',
    fecha_planificada DATETIME,
    ventana_inicio   TIME,
    ventana_fin      TIME,

    -- Código de verificación
    codigo_verificacion CHAR(6) NOT NULL,
    codigo_usado     TINYINT(1) NOT NULL DEFAULT 0,

    -- Timestamps de transición de estado
    asignado_at      DATETIME,
    recogido_at      DATETIME,
    entregado_at     DATETIME,
    cancelado_at     DATETIME,
    expira_asignacion_at DATETIME,

    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_envio_cliente      FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
    CONSTRAINT fk_envio_mensajero    FOREIGN KEY (mensajero_id) REFERENCES mensajeros(id),
    CONSTRAINT fk_envio_destinatario FOREIGN KEY (destinatario_usuario_id) REFERENCES usuarios(id),

    INDEX idx_estado          (estado),
    INDEX idx_cliente         (cliente_id),
    INDEX idx_mensajero       (mensajero_id),
    INDEX idx_tipo_planificado (tipo_envio, fecha_planificada),
    INDEX idx_expira          (expira_asignacion_at),
    INDEX idx_uuid            (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Cambio v2.0:** Campos `ruta_distancia_m`, `ruta_duracion_s` y `ruta_polyline` almacenan la ruta calculada por OSRM al crear el envío. Se persiste para no recalcular en cada petición de tracking.

### 4.4 Tabla: `ubicaciones_mensajero`

```sql
-- Esta tabla recibe escrituras BATCH cada 60s desde el worker.
-- La posición "en vivo" se consulta desde Redis (ver §7).
CREATE TABLE ubicaciones_mensajero (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mensajero_id   BIGINT UNSIGNED NOT NULL,
    lat            DECIMAL(10,7) NOT NULL,
    lng            DECIMAL(10,7) NOT NULL,
    precision_m    SMALLINT,
    registrado_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ubic_mensajero FOREIGN KEY (mensajero_id)
        REFERENCES mensajeros(id) ON DELETE CASCADE,
    INDEX idx_mensajero_tiempo (mensajero_id, registrado_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ROW_FORMAT=COMPRESSED;           -- Ahorra espacio en tabla de alta inserción
```

### 4.5 Tabla: `fotos_envio`

```sql
CREATE TABLE fotos_envio (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    envio_id     BIGINT UNSIGNED NOT NULL,
    tipo         ENUM('recogida','entrega','comprobante_pago') NOT NULL,
    path         VARCHAR(255) NOT NULL,
    hash_sha256  CHAR(64) NOT NULL,             -- integridad del archivo
    subido_por   BIGINT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_at    DATETIME NOT NULL,
    CONSTRAINT fk_foto_envio FOREIGN KEY (envio_id) REFERENCES envios(id),
    INDEX idx_envio_tipo (envio_id, tipo),
    INDEX idx_expira     (expira_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Cambio v2.0:** Campo `hash_sha256` para verificar integridad del archivo en disco en los jobs de limpieza.

### 4.6 Tabla: `pagos`

```sql
CREATE TABLE pagos (
    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    envio_id             BIGINT UNSIGNED NOT NULL UNIQUE,
    monto                DECIMAL(10,2) NOT NULL,
    metodo               ENUM('transfermovil','enzona') NOT NULL,
    referencia_cliente   VARCHAR(100),
    foto_comprobante_id  BIGINT UNSIGNED,
    estado               ENUM('pendiente','verificado','rechazado') NOT NULL DEFAULT 'pendiente',
    verificado_por       BIGINT UNSIGNED,
    verificado_at        DATETIME,
    motivo_rechazo       VARCHAR(255),
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pago_envio  FOREIGN KEY (envio_id) REFERENCES envios(id),
    CONSTRAINT fk_pago_admin  FOREIGN KEY (verificado_por) REFERENCES usuarios(id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.7 Tabla: `calificaciones`

```sql
CREATE TABLE calificaciones (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    envio_id     BIGINT UNSIGNED NOT NULL UNIQUE,
    cliente_id   BIGINT UNSIGNED NOT NULL,
    mensajero_id BIGINT UNSIGNED NOT NULL,
    estrellas    TINYINT NOT NULL CHECK (estrellas BETWEEN 1 AND 5),
    comentario   VARCHAR(500),
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_calif_envio     FOREIGN KEY (envio_id) REFERENCES envios(id),
    CONSTRAINT fk_calif_cliente   FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
    CONSTRAINT fk_calif_mensajero FOREIGN KEY (mensajero_id) REFERENCES mensajeros(id),
    INDEX idx_mensajero (mensajero_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.8 Tabla: `favoritos`

```sql
CREATE TABLE favoritos (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id   BIGINT UNSIGNED NOT NULL,
    mensajero_id BIGINT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fav_cliente   FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
    CONSTRAINT fk_fav_mensajero FOREIGN KEY (mensajero_id) REFERENCES mensajeros(id),
    UNIQUE KEY uq_fav (cliente_id, mensajero_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.9 Tabla: `mensajes_chat`

```sql
CREATE TABLE mensajes_chat (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    envio_id     BIGINT UNSIGNED NOT NULL,
    remitente_id BIGINT UNSIGNED,               -- NULL = mensaje de sistema
    tipo         ENUM('texto','foto','sistema') NOT NULL DEFAULT 'texto',
    contenido    TEXT,
    leido_cliente      TINYINT(1) NOT NULL DEFAULT 0,
    leido_mensajero    TINYINT(1) NOT NULL DEFAULT 0,
    leido_destinatario TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_at    DATETIME NOT NULL,
    CONSTRAINT fk_chat_envio     FOREIGN KEY (envio_id) REFERENCES envios(id),
    CONSTRAINT fk_chat_remitente FOREIGN KEY (remitente_id) REFERENCES usuarios(id),
    INDEX idx_envio_time (envio_id, created_at),
    INDEX idx_expira     (expira_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Cambio v2.0:** Se separa `leido` en tres columnas (una por tipo de participante) en lugar de un campo booleano único, permitiendo saber exactamente quién ha leído sin pollar la tabla completa.

### 4.10 Tabla: `refresh_tokens`

```sql
CREATE TABLE refresh_tokens (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token       VARCHAR(128) NOT NULL UNIQUE,
    usuario_id  BIGINT UNSIGNED NOT NULL,
    expira_at   DATETIME NOT NULL,
    revocado    TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rt_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token    (token),
    INDEX idx_usuario  (usuario_id),
    INDEX idx_expira   (expira_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.11 Tabla: `password_reset_tokens`

```sql
CREATE TABLE password_reset_tokens (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token       VARCHAR(128) NOT NULL UNIQUE,
    usuario_id  BIGINT UNSIGNED NOT NULL,
    expira_at   DATETIME NOT NULL,
    usado       TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prt_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token  (token),
    INDEX idx_expira (expira_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.12 Tabla: `messenger_messages` (Symfony Messenger)

```sql
-- Generada automáticamente por Symfony Messenger con doctrine transport.
-- No modificar manualmente.
CREATE TABLE messenger_messages (
    id           BIGINT AUTO_INCREMENT NOT NULL,
    body         LONGTEXT NOT NULL,
    headers      LONGTEXT NOT NULL,
    queue_name   VARCHAR(190) NOT NULL,
    created_at   DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id),
    INDEX IDX_queue      (queue_name),
    INDEX IDX_available  (available_at),
    INDEX IDX_delivered  (delivered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.13 Tabla: `configuraciones`

```sql
CREATE TABLE configuraciones (
    clave       VARCHAR(80) NOT NULL PRIMARY KEY,
    valor       VARCHAR(255) NOT NULL,
    descripcion VARCHAR(300)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO configuraciones VALUES
    ('comision_porcentaje',   '15',          'Porcentaje de comisión de la plataforma'),
    ('precio_base_cup',       '100',         'Tarifa base por envío en CUP'),
    ('retencion_fotos_dias',  '7',           'Días de retención de fotos y chat'),
    ('radio_busqueda_km',     '10',          'Radio para buscar mensajeros cercanos'),
    ('timeout_asignacion_min','3',           'Minutos para que mensajero acepte'),
    ('max_rechazos',          '3',           'Rechazos consecutivos antes de bloqueo'),
    ('bloqueo_horas',         '24',          'Horas de bloqueo por exceso de rechazos'),
    ('moneda',                'CUP',         'Moneda operativa'),
    ('ciudad_activa',         'La Habana',   'Ciudad de operación'),
    ('geocoding_cache_ttl',   '86400',       'TTL en segundos para caché de geocodificación'),
    ('ruta_cache_ttl',        '3600',        'TTL en segundos para caché de rutas OSRM'),
    ('tracking_push_interval','10',          'Segundos entre actualizaciones de ubicación del mensajero');
```

---

## 5. Real-time con Mercure

### 5.1 Por qué Mercure en lugar de WebSockets

Mercure es un protocolo sobre SSE (Server-Sent Events) que funciona sobre HTTP/2. En un VPS único con Nginx, es significativamente más simple de operar que WebSockets: no requiere sticky sessions, funciona con proxies HTTP estándar y el hub de Mercure es un binario Go de ~15 MB que consume muy poca memoria. Para 100–500 usuarios concurrentes es la solución más adecuada.

El cliente (navegador o app móvil) mantiene **una única conexión SSE** al hub de Mercure, que recibe todos los eventos a los que está suscrito (tracking + chat). No hay polling. El servidor publica eventos en el hub mediante HTTP POST.

### 5.2 Tópicos Mercure

```
# Tracking de ubicación (mensajero → cliente y admin)
/tracking/envio/{envioUuid}

# Chat (todos los participantes del envío)
/chat/envio/{envioUuid}

# Notificaciones del mensajero (solicitudes de envío, confirmaciones)
/mensajero/{mensajeroUuid}/notificaciones

# Notificaciones del cliente
/cliente/{clienteUuid}/notificaciones

# Panel del admin (nuevos comprobantes, envíos sin mensajero)
/admin/notificaciones
```

### 5.3 Publicar desde Symfony

```php
// src/Tracking/Service/MercurePublisher.php

final class MercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    public function publicarUbicacion(string $envioUuid, float $lat, float $lng, \DateTimeImmutable $timestamp): void
    {
        $this->hub->publish(new Update(
            topics: ["/tracking/envio/{$envioUuid}"],
            data: json_encode([
                'lat'       => $lat,
                'lng'       => $lng,
                'timestamp' => $timestamp->format(\DateTimeInterface::ATOM),
            ]),
            private: true,   // solo clientes con JWT Mercure válido pueden suscribirse
        ));
    }

    public function publicarMensajeChat(string $envioUuid, array $mensaje): void
    {
        $this->hub->publish(new Update(
            topics: ["/chat/envio/{$envioUuid}"],
            data: json_encode($mensaje),
            private: true,
        ));
    }

    public function notificarMensajero(string $mensajeroUuid, string $tipo, array $data): void
    {
        $this->hub->publish(new Update(
            topics: ["/mensajero/{$mensajeroUuid}/notificaciones"],
            data: json_encode(['tipo' => $tipo, ...$data]),
            private: true,
        ));
    }
}
```

### 5.4 Token JWT de Mercure para el cliente

El cliente (frontend) necesita un JWT firmado con el secreto de Mercure para suscribirse a tópicos privados. Este token se genera en el backend y se entrega junto con la respuesta del login o de la petición del envío.

```php
// src/Auth/Service/MercureTokenService.php

final class MercureTokenService
{
    public function __construct(
        #[Autowire('%env(MERCURE_JWT_SECRET)%')]
        private readonly string $mercureSecret,
    ) {}

    /**
     * Genera un JWT Mercure con los tópicos a los que el usuario puede suscribirse.
     */
    public function generarToken(Usuario $usuario, ?Envio $envio = null): string
    {
        $topicos = $this->construirTopicos($usuario, $envio);

        $payload = [
            'mercure' => ['subscribe' => $topicos],
            'exp'     => time() + 3600,
        ];

        return JWT::encode($payload, $this->mercureSecret, 'HS256');
    }

    private function construirTopicos(Usuario $usuario, ?Envio $envio): array
    {
        $topicos = [];

        if ($usuario->getTipo() === 'mensajero') {
            $topicos[] = "/mensajero/{$usuario->getUuid()}/notificaciones";
        }

        if ($usuario->getTipo() === 'cliente') {
            $topicos[] = "/cliente/{$usuario->getUuid()}/notificaciones";
        }

        if ($usuario->getTipo() === 'admin') {
            $topicos[] = '/admin/notificaciones';
        }

        if ($envio !== null) {
            $topicos[] = "/tracking/envio/{$envio->getUuid()}";
            $topicos[] = "/chat/envio/{$envio->getUuid()}";
        }

        return $topicos;
    }
}
```

### 5.5 Flujo de tracking con Mercure

```
[App Mensajero]                    [API Symfony]           [Redis]           [Mercure Hub]        [App Cliente]
      |                                  |                     |                    |                    |
      |-- POST /mensajero/ubicacion ---> |                     |                    |                    |
      |   { lat, lng, precision_m }      |                     |                    |                    |
      |                                  |-- SET ubicacion:{mensajeroId} -> |       |                    |
      |                                  |   (Redis ZSET, TTL 30s)         |       |                    |
      |                                  |                                  |       |                    |
      |                                  |-- hub->publish(tracking/envio/X) -----> |                    |
      |                                  |                                  |       |-- SSE event -----> |
      |<-- 204 No Content -------------- |                                  |       |   {lat,lng,ts}     |
      |                                  |                                  |       |                    |
      |                        [cada 60s: worker flush batch a MySQL]       |       |                    |
```

### 5.6 Configuración Nginx para Mercure

```nginx
# /etc/nginx/conf.d/mercure.conf
server {
    listen 443 ssl http2;
    server_name tu-dominio.cu;

    # ...certificados SSL...

    location /.well-known/mercure {
        proxy_pass http://127.0.0.1:3000;
        proxy_read_timeout 24h;         # Mantener conexiones SSE abiertas
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_buffering off;            # CRÍTICO: no bufferizar SSE
        proxy_cache off;
        chunked_transfer_encoding on;
    }

    location /api {
        fastcgi_pass 127.0.0.1:9000;
        # ...configuración PHP-FPM estándar...
    }
}
```

---

## 6. Mapas y Geocodificación Open-Source

### 6.1 Stack geoespacial elegido

| Función | Servicio | Por qué |
|---|---|---|
| Geocodificación (dirección → coords) | **Nominatim** (self-hosted) | OSM, sin límites, datos de Cuba disponibles |
| Geocodificación inversa (coords → dirección) | **Nominatim** (self-hosted) | Igual que arriba |
| Cálculo de rutas y tiempos | **OSRM** (self-hosted) | Motor C++, muy rápido, usa datos OSM |
| Mapa en el frontend | **Leaflet.js** + tiles OSM | Sin API key, open-source |
| Tiles (imágenes de mapa) | `tile.openstreetmap.org` o self-hosted **TileServer GL** | Self-hosted si la conectividad es un problema |

> **Nota sobre Cuba y OSM:** OpenStreetMap tiene cobertura de calles de La Habana y otras ciudades cubanas. La calidad varía por barrio, pero es suficiente para geocodificación de direcciones principales. Se recomienda contribuir y corregir datos en OSM para mejorar la cobertura local con el tiempo.

### 6.2 Nominatim: servicio de geocodificación

```php
// src/Geocoding/Service/NominatimService.php

final class NominatimService
{
    private const CACHE_PREFIX = 'geocoding:nominatim:';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        #[Autowire('%env(NOMINATIM_URL)%')]
        private readonly string $nominatimUrl,
        #[Autowire('%env(int:GEOCODING_CACHE_TTL)%')]
        private readonly int $cacheTtl,
    ) {}

    /**
     * Geocodifica una dirección de texto a coordenadas.
     * Resultados cacheados en Redis por TTL configurable (por defecto 24h).
     */
    public function geocodificar(string $direccion): ?CoordenadasDto
    {
        $cacheKey = self::CACHE_PREFIX . 'fwd:' . md5(strtolower(trim($direccion)));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($direccion): ?CoordenadasDto {
            $item->expiresAfter($this->cacheTtl);

            $response = $this->httpClient->request('GET', $this->nominatimUrl . '/search', [
                'query' => [
                    'q'              => $direccion,
                    'format'         => 'jsonv2',
                    'limit'          => 1,
                    'countrycodes'   => 'cu',            // Limitar a Cuba
                    'addressdetails' => 1,
                ],
                'headers' => [
                    'User-Agent' => 'MensajeriaApp/2.0 (contacto@tu-dominio.cu)',
                ],
            ]);

            $data = $response->toArray();

            if (empty($data)) {
                return null;
            }

            return new CoordenadasDto(
                lat: (float) $data[0]['lat'],
                lng: (float) $data[0]['lon'],
                displayName: $data[0]['display_name'],
            );
        });
    }

    /**
     * Geocodificación inversa: coordenadas a dirección legible.
     * Muy útil para mostrar dirección del mensajero en el tracking.
     */
    public function geocodificarInverso(float $lat, float $lng): ?string
    {
        // Redondear a 4 decimales para agrupar puntos muy cercanos en la misma clave de caché
        $latR = round($lat, 4);
        $lngR = round($lng, 4);
        $cacheKey = self::CACHE_PREFIX . "rev:{$latR}:{$lngR}";

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($lat, $lng): ?string {
            $item->expiresAfter($this->cacheTtl);

            $response = $this->httpClient->request('GET', $this->nominatimUrl . '/reverse', [
                'query' => [
                    'lat'    => $lat,
                    'lon'    => $lng,
                    'format' => 'jsonv2',
                ],
                'headers' => ['User-Agent' => 'MensajeriaApp/2.0'],
            ]);

            $data = $response->toArray();

            return $data['display_name'] ?? null;
        });
    }
}
```

### 6.3 OSRM: cálculo de rutas

```php
// src/Geocoding/Service/OsrmService.php

final class OsrmService
{
    private const CACHE_PREFIX = 'geocoding:osrm:';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        #[Autowire('%env(OSRM_URL)%')]
        private readonly string $osrmUrl,
        #[Autowire('%env(int:RUTA_CACHE_TTL)%')]
        private readonly int $cacheTtl,
    ) {}

    /**
     * Calcula la ruta entre dos puntos.
     * Devuelve distancia en metros, duración estimada en segundos y polyline.
     */
    public function calcularRuta(
        float $origenLat, float $origenLng,
        float $destinoLat, float $destinoLng
    ): ?RutaDto {
        // La clave de caché redondea a 4 decimales (~11m de precisión)
        $key = self::CACHE_PREFIX . implode(':', array_map(
            fn(float $v) => round($v, 4),
            [$origenLat, $origenLng, $destinoLat, $destinoLng]
        ));

        return $this->cache->get($key, function (ItemInterface $item) use (
            $origenLat, $origenLng, $destinoLat, $destinoLng
        ): ?RutaDto {
            $item->expiresAfter($this->cacheTtl);

            $coords = "{$origenLng},{$origenLat};{$destinoLng},{$destinoLat}";

            $response = $this->httpClient->request(
                'GET',
                "{$this->osrmUrl}/route/v1/driving/{$coords}",
                ['query' => ['overview' => 'full', 'geometries' => 'polyline6']]
            );

            $data = $response->toArray();

            if ($data['code'] !== 'Ok' || empty($data['routes'])) {
                return null;
            }

            $route = $data['routes'][0];

            return new RutaDto(
                distanciaMetros: (int) $route['distance'],
                duracionSegundos: (int) $route['duration'],
                polyline: $route['geometry'],          // polyline6 para Leaflet
            );
        });
    }
}
```

### 6.4 Integración en el flujo de creación de envío

Al crear un envío, el `EnvioService` llama a `OsrmService::calcularRuta()` y persiste el resultado en las columnas `ruta_distancia_m`, `ruta_duracion_s` y `ruta_polyline` de la tabla `envios`. Esto ocurre **una sola vez** por envío. El cliente de seguimiento recibe la polyline directamente del endpoint de detalle del envío, sin recalcular.

```php
// src/Envio/Service/EnvioService.php (fragmento)

public function crearEnvio(CrearEnvioDto $dto, Usuario $cliente): Envio
{
    // 1. Calcular ruta (OSRM, con caché Redis)
    $ruta = $this->osrmService->calcularRuta(
        $dto->origenLat, $dto->origenLng,
        $dto->destinoLat, $dto->destinoLng,
    );

    // 2. Calcular precio (precio_base de configuraciones + posible factor de distancia futuro)
    $precioBase       = (float) $this->configuracionService->get('precio_base_cup');
    $comisionPct      = (float) $this->configuracionService->get('comision_porcentaje');
    $comision         = $precioBase * ($comisionPct / 100);
    $ganancia         = $precioBase - $comision;

    // 3. Crear entidad
    $envio = new Envio();
    $envio->setUuid(Uuid::v4()->toRfc4122());
    $envio->setCliente($cliente);
    $envio->setOrigenDireccion($dto->origenDireccion);
    $envio->setOrigenLat($dto->origenLat);
    $envio->setOrigenLng($dto->origenLng);
    $envio->setDestinoDireccion($dto->destinoDireccion);
    $envio->setDestinoLat($dto->destinoLat);
    $envio->setDestinoLng($dto->destinoLng);
    $envio->setRutaDistanciaM($ruta?->distanciaMetros);
    $envio->setRutaDuracionS($ruta?->duracionSegundos);
    $envio->setRutaPolyline($ruta?->polyline);
    $envio->setCodigoVerificacion($this->generarCodigo6Digitos());
    $envio->setPrecioBase($precioBase);
    $envio->setComisionPlataforma($comision);
    $envio->setGananciaMensajero($ganancia);
    // ...resto de campos...

    $this->em->persist($envio);
    $this->em->flush();

    // 4. Publicar evento (Symfony EventDispatcher)
    $this->eventDispatcher->dispatch(new EnvioCreadoEvent($envio));

    return $envio;
}
```

### 6.5 Leaflet.js en el frontend

```javascript
// Ejemplo de integración en frontend (vanilla JS o framework)
// La polyline se recibe del endpoint GET /cliente/envios/{id}

const map = L.map('map').setView([23.1136, -82.3666], 13); // La Habana

// Tiles OSM (sin API key)
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19,
}).addTo(map);

// Dibujar ruta (polyline6 de OSRM → decodificar con polyline.js)
const rutaCoords = polyline.decode(envio.ruta_polyline, 6); // precision 6
const rutaLine = L.polyline(rutaCoords, { color: '#2563EB', weight: 4 }).addTo(map);
map.fitBounds(rutaLine.getBounds());

// Marcador origen
L.marker([envio.origen_lat, envio.origen_lng])
    .bindPopup('📦 Origen: ' + envio.origen_direccion)
    .addTo(map);

// Marcador destino
L.marker([envio.destino_lat, envio.destino_lng])
    .bindPopup('🏠 Destino: ' + envio.destino_direccion)
    .addTo(map);

// Marcador mensajero — se actualiza via Mercure SSE
const mensajeroMarker = L.marker([0, 0], {
    icon: L.icon({ iconUrl: '/img/moto-icon.png', iconSize: [32, 32] })
}).addTo(map);

// Suscripción SSE con Mercure
const eventSource = new EventSource(
    `${MERCURE_PUBLIC_URL}?topic=${encodeURIComponent('/tracking/envio/' + envio.uuid)}`,
    { headers: { Authorization: 'Bearer ' + mercureToken } }
);

eventSource.onmessage = (event) => {
    const { lat, lng } = JSON.parse(event.data);
    mensajeroMarker.setLatLng([lat, lng]);
};
```

---

## 7. Gestión de Caché y Reducción de Peticiones Externas

### 7.1 Estrategia multicapa

```
┌─────────────────────────────────────────────────────────────────┐
│                         CAPAS DE CACHÉ                          │
│                                                                  │
│  L1: APCu (in-process, ~5ms)                                    │
│      → Configuraciones del sistema (precio, comisión, radio)    │
│      → TTL: 5 minutos, invalidar al actualizar configuración    │
│                                                                  │
│  L2: Redis (red local, ~1ms)                                     │
│      → Última ubicación mensajero (ZSET, TTL 30s)              │
│      → Geocodificación Nominatim (TTL 24h)                      │
│      → Rutas OSRM (TTL 1h)                                      │
│      → Mensajeros disponibles por zona (TTL 10s)               │
│      → JWT blacklist (TTL = TTL del token)                      │
│      → Rate limiting (sliding window)                           │
│                                                                  │
│  L3: MySQL (origen de verdad)                                    │
│      → Todo el modelo de datos                                   │
│      → Ubicaciones batch (escritura cada 60s)                   │
└─────────────────────────────────────────────────────────────────┘
```

### 7.2 Caché de última ubicación del mensajero (Redis ZSET)

La posición GPS del mensajero se actualiza cada 10 segundos. Con 100 mensajeros activos eso son 600 escrituras/minuto. En la v1.0 todas iban directo a MySQL. En la v2.0:

- Cada actualización de ubicación escribe en **Redis HASH** (key: `ubicacion:mensajero:{id}`, TTL: 30s).
- Un worker de Symfony Messenger hace **flush batch a MySQL cada 60s** para el historial.
- Las lecturas de tracking (cliente viendo dónde está el mensajero) leen **únicamente desde Redis**.
- Con Mercure, las lecturas de tracking desde Redis son casi cero: el cliente recibe push, solo consulta la API como fallback inicial.

```php
// src/Tracking/Cache/UbicacionCacheRepository.php

final class UbicacionCacheRepository
{
    private const KEY_PREFIX = 'ubicacion:mensajero:';
    private const TTL = 30; // segundos; si no hay update en 30s, el mensajero está offline

    public function __construct(private readonly \Redis $redis) {}

    public function guardar(int $mensajeroId, float $lat, float $lng, ?int $precisionM, \DateTimeImmutable $ts): void
    {
        $key  = self::KEY_PREFIX . $mensajeroId;
        $data = json_encode(['lat' => $lat, 'lng' => $lng, 'precision_m' => $precisionM, 'ts' => $ts->format(\DateTimeInterface::ATOM)]);

        $this->redis->setex($key, self::TTL, $data);
    }

    public function obtener(int $mensajeroId): ?array
    {
        $data = $this->redis->get(self::KEY_PREFIX . $mensajeroId);

        return $data ? json_decode($data, true) : null;
    }

    public function estaActivo(int $mensajeroId): bool
    {
        return (bool) $this->redis->exists(self::KEY_PREFIX . $mensajeroId);
    }
}
```

### 7.3 Caché de configuraciones del sistema

```php
// src/Admin/Service/ConfiguracionService.php

final class ConfiguracionService
{
    private const CACHE_KEY = 'config:sistema:all';
    private const TTL = 300; // 5 minutos

    public function __construct(
        private readonly ConfiguracionRepository $repo,
        private readonly CacheInterface $apcu,   // TagAwareAdapter con APCu
    ) {}

    public function get(string $clave): string
    {
        $todas = $this->apcu->get(self::CACHE_KEY, function (): array {
            return $this->repo->findAllAsMap();  // ['clave' => 'valor', ...]
        });

        return $todas[$clave] ?? throw new \InvalidArgumentException("Configuración '{$clave}' no existe.");
    }

    public function actualizar(string $clave, string $valor): void
    {
        $config = $this->repo->find($clave) ?? throw new \NotFoundException();
        $config->setValor($valor);
        $this->repo->save($config);

        // Invalidar caché inmediatamente
        $this->apcu->delete(self::CACHE_KEY);
    }
}
```

### 7.4 Caché de mensajeros disponibles cercanos

```php
// src/Mensajero/Service/DisponibilidadService.php (fragmento)

/**
 * Devuelve mensajeros disponibles en un radio dado.
 * Cache en Redis 10s: evita N queries Haversine por cada cliente que carga la pantalla.
 */
public function obtenerCercanos(float $lat, float $lng, float $radioKm): array
{
    $latR  = round($lat, 2);   // ~1.1km de precisión para clave de caché
    $lngR  = round($lng, 2);
    $radio = (int) $radioKm;
    $cacheKey = "mensajeros:cercanos:{$latR}:{$lngR}:{$radio}km";

    return $this->cache->get($cacheKey, function (ItemInterface $item) use ($lat, $lng, $radioKm): array {
        $item->expiresAfter(10);  // 10 segundos
        return $this->mensajeroRepo->findCercanosDisponibles($lat, $lng, $radioKm);
    });
}
```

### 7.5 Symfony Workflow para estados del envío

En lugar de gestionar transiciones de estado con `if/switch` dispersos, usamos el componente **Symfony Workflow** (state machine). Esto elimina transiciones inválidas y centraliza la lógica.

```yaml
# config/packages/workflow.yaml
framework:
    workflows:
        envio:
            type: 'state_machine'
            marking_store:
                type: 'method'
                property: 'estado'
            supports:
                - App\Envio\Entity\Envio
            initial_marking: pendiente
            places:
                - pendiente
                - pagado
                - asignado
                - recogido
                - en_camino
                - entregado
                - cancelado
            transitions:
                pagar:
                    from: pendiente
                    to:   pagado
                asignar:
                    from: pagado
                    to:   asignado
                recoger:
                    from: asignado
                    to:   recogido
                salir:
                    from: recogido
                    to:   en_camino
                entregar:
                    from: en_camino
                    to:   entregado
                cancelar_pendiente:
                    from: pendiente
                    to:   cancelado
                cancelar_pagado:
                    from: pagado
                    to:   cancelado
                cancelar_asignado:
                    from: asignado
                    to:   cancelado
                cancelar_admin:
                    from: [pendiente, pagado, asignado, recogido, en_camino]
                    to:   cancelado
```

---

## 8. Diagramas de Flujo (Mermaid)

### 8.1 Flujo completo del envío

```mermaid
flowchart TD
    A([Cliente crea envío]) --> B[OSRM calcula ruta\ncached en Redis]
    B --> C[Estado: PENDIENTE]
    C --> D[Cliente sube comprobante Transfermóvil/Enzona]
    D --> E{Admin valida\ncomprobante}
    E -->|Rechazado| F[Notifica via Mercure\ncliente puede reintentar]
    F --> D
    E -->|Aprobado| G[Estado: PAGADO\nMercure notifica cliente]
    G --> H{Modo asignación}
    H -->|Manual| I[Cliente elige mensajero\nde lista cercanos]
    H -->|Auto| J[Sistema busca mensajero\nmás cercano — Haversine]
    I --> K[Estado: ASIGNADO\nMercure notifica mensajero]
    J --> K
    K --> L{Mensajero responde\nen 3 min}
    L -->|Rechaza| M{¿3er rechazo?}
    M -->|Sí| N[Bloqueo 24h\nMessenger reasigna]
    M -->|No| J
    L -->|Timeout — Messenger| J
    L -->|Acepta| O[Estado: RECOGIDO\nMercure notifica cliente]
    O --> P[Estado: EN_CAMINO\nTracking Mercure activo]
    P --> Q[Mensajero introduce\ncódigo 6 dígitos]
    Q --> R{¿Código correcto?}
    R -->|No| Q
    R -->|Sí| S[Foto obligatoria\nuploaded]
    S --> T[Estado: ENTREGADO\nMercure notifica todos]
    T --> U[Cliente califica 1-5 ⭐]
    U --> V[Promedio mensajero\nactualizado]

    style E fill:#FFE082,color:#000
    style G fill:#C8E6C9,color:#000
    style T fill:#C8E6C9,color:#000
    style N fill:#FFCDD2,color:#000
```

### 8.2 Flujo de tracking con Mercure

```mermaid
sequenceDiagram
    participant AppMensajero
    participant API
    participant Redis
    participant MercureHub
    participant AppCliente

    Note over AppCliente,MercureHub: Al abrir pantalla de tracking, AppCliente conecta SSE a Mercure

    loop Cada 10 segundos
        AppMensajero->>API: POST /mensajero/ubicacion {lat, lng}
        API->>Redis: SETEX ubicacion:mensajero:X {lat,lng,ts} TTL=30s
        API->>MercureHub: POST /hub topic=/tracking/envio/UUID
        MercureHub-->>AppCliente: SSE event {lat, lng, timestamp}
        API-->>AppMensajero: 204 No Content
    end

    Note over API,Redis: Cada 60s: worker flush batch a MySQL (historial)
```

### 8.3 Flujo de chat con Mercure

```mermaid
sequenceDiagram
    participant Cliente
    participant API
    participant DB
    participant MercureHub
    participant Mensajero
    participant Destinatario

    Cliente->>API: POST /chat/{envioId}/mensajes {contenido}
    API->>DB: INSERT mensajes_chat
    API->>MercureHub: POST /hub topic=/chat/envio/UUID
    MercureHub-->>Mensajero: SSE event {mensaje}
    MercureHub-->>Destinatario: SSE event {mensaje}
    API-->>Cliente: 201 Created {mensaje}
```

### 8.4 Flujo de geocodificación con caché

```mermaid
flowchart LR
    A([Cliente ingresa dirección]) --> B{¿En caché\nRedis?}
    B -->|Sí — TTL 24h| C[Devuelve coords\ncacheadas]
    B -->|No| D[Consulta Nominatim\nself-hosted]
    D --> E[Guarda en Redis\nTTL 24h]
    E --> C
    C --> F([Muestra en Leaflet])
```

---

## 9. Endpoints API REST Completos

> **Base URL:** `/api/v1` · **Auth:** `Authorization: Bearer {JWT}` · **Respuestas:** JSON · **Formato fechas:** ISO 8601.

### 9.1 Autenticación

| Método | Ruta | Auth | Descripción |
|:-:|---|:-:|---|
| POST | `/auth/registro/cliente` | — | Registro cliente |
| POST | `/auth/registro/mensajero` | — | Registro mensajero (foto CI en multipart) |
| POST | `/auth/registro/destinatario` | — | Registro destinatario opcional |
| POST | `/auth/login` | — | Login → `{token, refresh_token, tipo, nombre, mercure_token}` |
| POST | `/auth/refresh` | RT | Renueva JWT con refresh token |
| POST | `/auth/logout` | JWT | Invalida JWT en Redis blacklist + revoca refresh token |
| POST | `/auth/cambiar-password` | JWT | Cambia contraseña |
| POST | `/auth/restablecer-password` | — | Solicita reset (genera token, envío por SMS/email) |
| POST | `/auth/restablecer-password/{token}` | — | Ejecuta reset |
| GET | `/auth/perfil` | JWT | Datos del usuario autenticado (grupos de serialización por rol) |
| PUT | `/auth/perfil` | JWT | Actualiza nombre, teléfono, device_token |

**Cambio v2.0:** El login devuelve `mercure_token` para que el cliente pueda suscribirse inmediatamente a SSE sin petición adicional.

### 9.2 Cliente — Perfil y Favoritos

| Método | Ruta | Auth | Descripción |
|:-:|---|:-:|---|
| GET | `/cliente/perfil` | `JWT:cliente` | Perfil del cliente |
| PUT | `/cliente/perfil` | `JWT:cliente` | Actualiza nombre, teléfono |
| GET | `/cliente/favoritos` | `JWT:cliente` | Lista de favoritos con calificación promedio |
| POST | `/cliente/favoritos/{mensajeroUuid}` | `JWT:cliente` | Añade favorito |
| DELETE | `/cliente/favoritos/{mensajeroUuid}` | `JWT:cliente` | Elimina favorito |

### 9.3 Cliente — Envíos

| Método | Ruta | Auth | Descripción |
|:-:|---|:-:|---|
| POST | `/cliente/envios` | `JWT:cliente` | Crea envío. Devuelve `{envio, mercure_token, ruta_polyline, duracion_estimada_s}` |
| GET | `/cliente/envios` | `JWT:cliente` | Lista paginada (filtros: estado, fecha) |
| GET | `/cliente/envios/{uuid}` | `JWT:cliente` | Detalle completo incluye `ruta_polyline` |
| GET | `/cliente/envios/{uuid}/tracking` | `JWT:cliente` | Posición actual del mensajero (Redis). Fallback si Mercure no disponible. |
| POST | `/cliente/envios/{uuid}/cancelar` | `JWT:cliente` | Cancela envío (workflow valida estado permitido) |
| GET | `/cliente/mensajeros/cercanos` | `JWT:cliente` | Lista mensajeros disponibles (caché Redis 10s). Query: `lat`, `lng`, `radio_km` |
| POST | `/cliente/envios/{uuid}/asignar/{mensajeroUuid}` | `JWT:cliente` | Asignación manual |
| POST | `/cliente/envios/{uuid}/calificar` | `JWT:cliente` | Calificación 1–5 |

### 9.4 Cliente — Pagos

| Método | Ruta | Auth | Descripción |
|:-:|---|:-:|---|
| GET | `/cliente/envios/{uuid}/pago` | `JWT:cliente` | Instrucciones de pago (Transfermóvil/Enzona, monto, cuenta) |
| POST | `/cliente/envios/{uuid}/pago/comprobante` | `JWT:cliente` | Sube foto del comprobante (multipart/form-data) |
| GET | `/cliente/envios/{uuid}/pago/estado` | `JWT:cliente` | Estado del pago `{pendiente\|verificado\|rechazado, motivo?}` |

### 9.5 Cliente — Chat

| Método | Ruta | Auth | Descripción |
|:-:|---|:-:|---|
| GET | `/chat/{envioUuid}/mensajes` | `JWT:participante` | Mensajes paginados por cursor (carga inicial; luego Mercure) |
| POST | `/chat/{envioUuid}/mensajes` | `JWT:participante` | Envía mensaje texto. Publica en Mercure. |
| POST | `/chat/{envioUuid}/mensajes/foto` | `JWT:participante` | Envía foto. Publica en Mercure. |
| GET | `/chat/{envioUuid}/mercure-token` | `JWT:participante` | Genera token Mercure para el chat de este envío |

**Cambio v2.0:** Se elimina el endpoint de polling `/chat/{envioId}/mensajes/nuevos`. El `GET /mensajes` solo se usa para carga inicial; las actualizaciones llegan via Mercure SSE.

### 9.6 Mensajero — Perfil y Disponibilidad

| Método | Ruta | Auth | Descripción |
|:-:|---|:-:|---|
| GET | `/mensajero/perfil` | `JWT:mensajero` | Perfil (datos descifrados en capa de aplicación) |
| PUT | `/mensajero/perfil` | `JWT:mensajero` | Actualiza tipo_vehiculo, device_token |
| POST | `/mensajero/disponibilidad` | `JWT:mensajero` | Activa/desactiva `{disponible: bool}`. Solo si aprobado y no bloqueado. |
| POST | `/mensajero/ubicacion` | `JWT:mensajero` | Envía `{lat, lng, precision_m?}`. Escribe Redis + publica Mercure. |

### 9.7 Mensajero — Envíos

| Método | Ruta | Auth | Descripción |
|:-:|---|:-:|---|
| GET | `/mensajero/solicitudes/pendientes` | `JWT:mensajero` | Solicitudes asignadas (fallback polling si Mercure no disponible) |
| POST | `/mensajero/envios/{uuid}/aceptar` | `JWT:mensajero` | Acepta envío asignado |
| POST | `/mensajero/envios/{uuid}/rechazar` | `JWT:mensajero` | Rechaza `{motivo?}` |
| POST | `/mensajero/envios/{uuid}/recoger` | `JWT:mensajero` | Marca recogido. Foto opcional (multipart). |
| POST | `/mensajero/envios/{uuid}/en-camino` | `JWT:mensajero` | Marca en_camino |
| POST | `/mensajero/envios/{uuid}/entregar` | `JWT:mensajero` | `{codigo_verificacion}` + foto obligatoria. Valida código, cambia estado. |
| GET | `/mensajero/envios` | `JWT:mensajero` | Historial paginado |
| GET | `/mensajero/envios/{uuid}` | `JWT:mensajero` | Detalle de un envío |
| GET | `/mensajero/envios/planificados` | `JWT:mensajero` | Lista de envíos planificados aceptables (sin solapamiento) |
| GET | `/mensajero/envios/{uuid}/ruta` | `JWT:mensajero` | Polyline + duración estimada para mostrar en mapa del mensajero |

### 9.8 Administrador — Mensajeros

| Método | Ruta | Auth | Descripción |
|:-:|---|:-:|---|
| GET | `/admin/mensajeros` | `JWT:admin` | Lista con filtros (estado_aprobacion, disponible), paginada |
| GET | `/admin/mensajeros/{uuid}` | `JWT:admin` | Detalle (campos sensibles descifrados) |
| POST | `/admin/mensajeros/{uuid}/aprobar` | `JWT:admin` | Aprueba. Mercure notifica mensajero. |
| POST | `/admin/mensajeros/{uuid}/rechazar` | `JWT:admin` | Rechaza `{motivo}`. Mercure notifica. |
| POST | `/admin/mensajeros/{uuid}/desactivar` | `JWT:admin` | Desactiva cuenta |
| GET | `/admin/mensajeros/{uuid}/foto-documento` | `JWT:admin` | Descarga foto CI (stream protegido) |

### 9.9 Administrador — Pagos

| Método | Ruta | Auth | Descripción |
|:-:|---|:-:|---|
| GET | `/admin/pagos/pendientes` | `JWT:admin` | Comprobantes pendientes de verificación |
| GET | `/admin/pagos/{id}` | `JWT:admin` | Detalle + URL de foto comprobante |
| POST | `/admin/pagos/{id}/verificar` | `JWT:admin` | Verifica → estado envío: pagado. Mercure notifica cliente. |
| POST | `/admin/pagos/{id}/rechazar` | `JWT:admin` | Rechaza `{motivo_rechazo}`. Mercure notifica cliente. |
| GET | `/admin/pagos/{id}/foto` | `JWT:admin` | Stream de foto comprobante |

### 9.10 Administrador — Envíos

| Método | Ruta | Auth | Descripción |
|:-:|---|:-:|---|
| GET | `/admin/envios` | `JWT:admin` | Lista con filtros (estado, cliente_uuid, mensajero_uuid, fecha) |
| GET | `/admin/envios/{uuid}` | `JWT:admin` | Detalle completo |
| POST | `/admin/envios/{uuid}/cancelar` | `JWT:admin` | Fuerza cancelación en cualquier estado (workflow: cancelar_admin) |
| GET | `/admin/envios/{uuid}/fotos` | `JWT:admin` | Lista de fotos del envío |
| GET | `/admin/envios/{uuid}/foto/{fotoId}` | `JWT:admin` | Stream de foto |

### 9.11 Administrador — Reportes y Configuración

| Método | Ruta | Auth | Descripción |
|:-:|---|:-:|---|
| GET | `/admin/reportes/diario` | `JWT:admin` | Reporte JSON pagos mensajeros. Query: `fecha` (YYYY-MM-DD) |
| GET | `/admin/reportes/resumen` | `JWT:admin` | Resumen: total envíos, ingresos, mensajeros activos. Query: `desde`, `hasta` |
| GET | `/admin/configuraciones` | `JWT:admin` | Lista todas las configuraciones |
| PUT | `/admin/configuraciones/{clave}` | `JWT:admin` | Actualiza valor. Invalida caché APCu. |

### 9.12 Geocodificación (uso interno del frontend)

| Método | Ruta | Auth | Descripción |
|:-:|---|:-:|---|
| GET | `/geocoding/buscar` | `JWT` | Geocodifica dirección. Query: `q`. Responde con `{lat,lng,display_name}`. Caché 24h. |
| GET | `/geocoding/inverso` | `JWT` | Coords → dirección. Query: `lat`, `lng`. Caché 24h. |

---

## 10. Cron Jobs y Symfony Messenger

### 10.1 Por qué Symfony Messenger en lugar de solo crons

Los crons de la v1.0 corrían cada 3 minutos para detectar timeouts. Con Messenger, el mensaje `ReasignarEnvioMessage` se agenda con delay de exactamente 3 minutos al asignar el envío. El worker lo procesa en segundos, sin esperar el siguiente cron.

### 10.2 Messages y Handlers

```php
// src/Envio/Message/ReasignarEnvioMessage.php
final readonly class ReasignarEnvioMessage
{
    public function __construct(public int $envioId) {}
}

// src/Envio/MessageHandler/ReasignarEnvioHandler.php
#[AsMessageHandler]
final class ReasignarEnvioHandler
{
    public function __invoke(ReasignarEnvioMessage $message): void
    {
        $envio = $this->envioRepo->find($message->envioId);

        if ($envio === null || $envio->getEstado() !== 'asignado') {
            return; // Ya fue aceptado o cancelado
        }

        if ($envio->getExpiraAsignacionAt() > new \DateTimeImmutable()) {
            return; // Todavía hay tiempo
        }

        $this->asignacionService->reasignarAutomatico($envio);
    }
}

// src/Tracking/Message/FlushUbicacionesBatchMessage.php
final readonly class FlushUbicacionesBatchMessage
{
    public function __construct(public int $mensajeroId, public array $ubicaciones) {}
}
```

### 10.3 Dispatcher de mensajes con delay

```php
// Al asignar un envío a un mensajero:
$this->bus->dispatch(
    new ReasignarEnvioMessage($envio->getId()),
    [new DelayStamp(180_000)] // 3 minutos en ms
);
```

### 10.4 Cron Jobs restantes (limpieza y reportes)

```cron
# /etc/cron.d/mensajeria

# Limpieza de fotos expiradas (retención 7 días)
0 3 * * * www-data php /var/www/mensajeria/bin/console app:cleanup:fotos >> /var/log/mensajeria/cleanup.log 2>&1

# Limpieza de mensajes de chat expirados
0 3 * * * www-data php /var/www/mensajeria/bin/console app:cleanup:chat >> /var/log/mensajeria/cleanup.log 2>&1

# Limpieza de ubicaciones > 48h (historial ya no útil)
5 3 * * * www-data php /var/www/mensajeria/bin/console app:cleanup:ubicaciones >> /var/log/mensajeria/cleanup.log 2>&1

# Limpieza de refresh_tokens y password_reset_tokens expirados
10 3 * * * www-data php /var/www/mensajeria/bin/console app:cleanup:tokens >> /var/log/mensajeria/cleanup.log 2>&1

# Reporte diario de pagos a mensajeros
0 2 * * * www-data php /var/www/mensajeria/bin/console app:reporte:diario >> /var/log/mensajeria/reportes.log 2>&1

# Desbloqueo de mensajeros (bloqueado_hasta <= NOW)
*/5 * * * * www-data php /var/www/mensajeria/bin/console app:mensajeros:check-bloqueo >> /dev/null 2>&1
```

### 10.5 Worker de Symfony Messenger (Supervisor)

```ini
; /etc/supervisor/conf.d/mensajeria-worker.conf
[program:mensajeria-worker]
command=php /var/www/mensajeria/bin/console messenger:consume async --time-limit=3600
directory=/var/www/mensajeria
user=www-data
numprocs=2                          ; 2 workers para VPS con 100-500 usuarios
autostart=true
autorestart=true
startsecs=0
redirect_stderr=true
stdout_logfile=/var/log/mensajeria/messenger-worker.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
environment=APP_ENV="prod"
```

---

## 11. Consideraciones de Seguridad

### 11.1 Autenticación JWT (RS256)

- Tokens firmados con **RS256** (par RSA 4096 bits). Nunca HS256 en producción.
- TTL access token: 1 hora. Refresh token: 30 días.
- **Logout real:** al llamar `/auth/logout`, el JWT se almacena en Redis con TTL igual al tiempo restante hasta su expiración. El `JWTBlacklistSubscriber` verifica cada petición.
- IDs expuestos en URLs: **UUID** (no ID entero). Impide enumeración de recursos.

```php
// src/Auth/Security/JWTBlacklistSubscriber.php
final class JWTBlacklistSubscriber implements EventSubscriberInterface
{
    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $jti     = $payload['jti'] ?? null;   // JWT ID claim — añadirlo al generar el token

        if ($jti && $this->redis->exists("jwt:blacklist:{$jti}")) {
            $event->markAsInvalid();
        }
    }
}
```

### 11.2 Cifrado de datos sensibles (AES-256-GCM)

```php
// src/Shared/Encryption/FieldEncryptor.php

final class FieldEncryptor
{
    public function __construct(
        #[Autowire('%env(base64:ENCRYPTION_KEY)%')]
        private readonly string $key,   // 32 bytes (256 bits)
    ) {}

    public function encrypt(string $plaintext): string
    {
        $iv         = random_bytes(12);  // GCM requiere 12 bytes
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        // Almacenar: IV (12) + TAG (16) + ciphertext
        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        $data       = base64_decode($encoded);
        $iv         = substr($data, 0, 12);
        $tag        = substr($data, 12, 16);
        $ciphertext = substr($data, 28);

        return openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
    }
}
```

Se integra como **Doctrine Lifecycle Listener**: `prePersist`/`preUpdate` cifran, `postLoad` descifra. Los campos `ci_cifrado`, `tarjeta_bancaria_cifrada` y `movil_pagos_cifrado` son `VARBINARY(512)`.

### 11.3 Validadores personalizados cubanos

```php
// src/Shared/Validator/CedulaCubana.php
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CedulaCubana extends Constraint {}

// src/Shared/Validator/CedulaCubanaValidator.php
final class CedulaCubanaValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!preg_match('/^\d{11}$/', (string) $value)) {
            $this->context->buildViolation('CI cubano debe tener 11 dígitos numéricos.')->addViolation();
            return;
        }

        // Validar fecha implícita en los primeros 6 dígitos (AAMMDD)
        $aa = substr($value, 0, 2);
        $mm = substr($value, 2, 2);
        $dd = substr($value, 4, 2);

        if (!checkdate((int) $mm, (int) $dd, (int) $aa + 1900)
            && !checkdate((int) $mm, (int) $dd, (int) $aa + 2000)) {
            $this->context->buildViolation('La fecha en el CI cubano no es válida.')->addViolation();
        }
    }
}
```

### 11.4 Rate Limiting

| Endpoint | Límite | Ventana |
|---|:-:|:-:|
| `POST /auth/login` | 10 intentos | 15 min / IP |
| `POST /auth/registro/*` | 5 registros | 1 hora / IP |
| `POST /mensajero/ubicacion` | 20 requests | 1 min / usuario |
| `GET /*/tracking` | 60 requests | 1 min / usuario |
| `POST /cliente/envios/{uuid}/pago/comprobante` | 5 subidas | 1 hora / usuario |
| `POST /auth/restablecer-password` | 3 solicitudes | 1 hora / IP |
| `GET /geocoding/*` | 60 requests | 1 min / usuario |

### 11.5 Seguridad de archivos subidos

- Validación de MIME real con `finfo_file()` — nunca confiar en `Content-Type` del cliente.
- Fotos de envío y chat: máximo **5 MB**. Comprobantes: máximo **10 MB**.
- Nombres de archivo: `sha256(uuid + timestamp + random_bytes(8)) + extensión_sanitizada`. Nunca el nombre original del cliente.
- Almacenamiento fuera del document root (`/var/www/mensajeria/var/uploads/`).
- Nginx deniega acceso directo a `/uploads/`. Solo se sirven a través de endpoints autenticados.
- Hash SHA-256 del archivo guardado en `fotos_envio.hash_sha256` para verificación de integridad.

### 11.6 ChatVoter (privacidad de tres participantes)

```php
// src/Chat/Security/ChatVoter.php
final class ChatVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['CHAT_READ', 'CHAT_WRITE'])
            && $subject instanceof Envio;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user  = $token->getUser();
        $envio = $subject;

        // Admin NUNCA accede al contenido del chat
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return false;
        }

        return $envio->getCliente()->getId() === $user->getId()
            || $envio->getMensajero()?->getUsuario()->getId() === $user->getId()
            || $envio->getDestinatarioUsuario()?->getId() === $user->getId();
    }
}
```

### 11.7 Headers de seguridad HTTP (Nginx)

```nginx
add_header X-Frame-Options "DENY" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; connect-src 'self' wss:; img-src 'self' data: tile.openstreetmap.org;" always;
add_header Permissions-Policy "geolocation=(self)" always;
```

---

## 12. Deudas Técnicas Resueltas

Esta sección documenta explícitamente cada deuda técnica identificada en la v1.0 y la solución implementada en v2.0.

| ID | Deuda v1.0 | Solución v2.0 | Módulo |
|---|---|---|---|
| DT-01 | Logout sin blacklist de JWT | Redis blacklist con TTL = tiempo restante del token | Auth |
| DT-02 | Restablecimiento de contraseña sin implementar | Entidad `PasswordResetToken`, servicio SMS/email, flujo completo | Auth |
| DT-03 | Validación débil de CI, tarjeta, teléfono cubano | Validadores Symfony personalizados (`CedulaCubana`, `TelefonoCubano`, `Luhn`) | Shared |
| DT-04 | Datos sensibles en texto plano | AES-256-GCM en capa de aplicación via Doctrine Listener | Shared, Mensajero |
| DT-05 | Rate limiting no configurado | `symfony/rate-limiter` en todos los endpoints críticos | Shared |
| DT-06 | Login solo devuelve `token` | Login devuelve `{token, refresh_token, tipo, nombre, mercure_token}` | Auth |
| DT-07 | Sin grupos de serialización por rol | Symfony Serializer con contextos y grupos (`cliente`, `mensajero`, `admin`) | Auth, Shared |
| DT-08 | Polling para tracking (miles de peticiones/min) | Mercure SSE — push del servidor | Tracking |
| DT-09 | Polling para chat (carga innecesaria) | Mercure SSE para mensajes de chat en tiempo real | Chat |
| DT-10 | Sin UUID en URLs (enumeración de recursos) | UUID v4 en todas las entidades expuestas públicamente | Global |
| DT-11 | IDs enteros en URLs de API | UUIDs en todos los endpoints | Global |
| DT-12 | `ubicaciones_mensajero` recibía INSERT cada 10s directo a MySQL | Redis HASH para posición viva + flush batch cada 60s a MySQL | Tracking |
| DT-13 | Sin Symfony Workflow (transiciones de estado en if/switch) | `symfony/workflow` como state machine del envío | Envio |
| DT-14 | Reasignación de envíos con cron cada 3 min | Symfony Messenger con `DelayStamp` de exactamente 3 min | Envio |
| DT-15 | Sin arquitectura modular (futuro escalado difícil) | Arquitectura por módulos con boundaries de interfaz | Global |
| DT-16 | Sin caché de configuraciones (query en cada request) | APCu L1 + Redis L2 con invalidación activa | Admin |
| DT-17 | Campo `leido` único en chat (no sabe quién leyó) | Tres columnas separadas: `leido_cliente`, `leido_mensajero`, `leido_destinatario` | Chat |
| DT-18 | Sin servicio de rutas (solo Haversine en línea recta) | OSRM self-hosted con caché Redis, polyline en tabla `envios` | Geocoding, Envio |
| DT-19 | Sin geocodificación de direcciones | Nominatim self-hosted con caché Redis 24h | Geocoding |
| DT-20 | Sin hash de integridad en archivos subidos | Campo `hash_sha256` en `fotos_envio`, verificado en cleanup | Envio, Pago |

---

## 13. Historias de Usuario con Estimación

> **Escala Fibonacci:** 1=trivial · 2=simple · 3=normal · 5=complejo · 8=muy complejo · 13=épico  
> **Velocidad estimada:** 25 puntos/semana (2 devs, con arquitectura ya definida)  
> **Objetivo:** Release completo funcional (no MVP)

### Iteración 1 — Auth, Core y DB *(38 puntos, ~1.5 semanas)*

| ID | Como… | Quiero… | Para… | Pts |
|---|---|---|---|:-:|
| US-01 | Dev | Estructura de módulos Symfony, Docker Compose, CI/CD básico | Base sólida antes de escribir features | 5 |
| US-02 | Dev | Migraciones Doctrine de todas las tablas v2.0 | Tener la DB lista | 3 |
| US-03 | Cliente | Registrarme con email y contraseña | Acceder a la plataforma | 2 |
| US-04 | Mensajero | Registrarme con datos de pago (cifrados) y foto de CI | Ser verificado | 3 |
| US-05 | Cualquiera | Login que devuelve JWT + refresh token + mercure_token | Autenticación completa desde el inicio | 3 |
| US-06 | Cualquiera | Logout que invalida JWT en Redis | Seguridad real al cerrar sesión | 2 |
| US-07 | Cualquiera | Restablecer contraseña via token | Recuperar acceso | 3 |
| US-08 | Admin | Ver mensajeros pendientes y aprobar/rechazar | Controlar quién opera | 3 |
| US-09 | Dev | Symfony Workflow configurado para estados del envío | Transiciones seguras y auditables | 3 |
| US-10 | Dev | Validadores personalizados (CI cubano, teléfono, Luhn) | Datos íntegros desde el registro | 3 |
| US-11 | Dev | Cifrado AES-256-GCM como Doctrine Listener | Datos sensibles protegidos en reposo | 5 |
| US-12 | Dev | `ConfiguracionService` con caché APCu/Redis | Sin queries repetidas a la tabla config | 3 |

### Iteración 2 — Envíos, Pagos y Geocodificación *(45 puntos, ~2 semanas)*

| ID | Como… | Quiero… | Para… | Pts |
|---|---|---|---|:-:|
| US-13 | Dev | Nominatim y OSRM self-hosted en Docker, con `GeocodingCache` Redis | Geocodificación sin costes ni límites | 8 |
| US-14 | Cliente | Crear envío con geocodificación de dirección y cálculo de ruta OSRM | Ver tiempo estimado y ruta antes de confirmar | 5 |
| US-15 | Sistema | Generar código de verificación de 6 dígitos al crear el envío | Confirmar la entrega | 2 |
| US-16 | Cliente | Ver datos de pago y subir foto del comprobante | Activar el envío | 3 |
| US-17 | Admin | Verificar o rechazar comprobantes. Mercure notifica cliente. | Gestionar pagos manuales | 3 |
| US-18 | Sistema | Buscar mensajero disponible más cercano (Haversine, caché Redis) | Asignar rápido | 5 |
| US-19 | Mensajero | Recibir solicitud via Mercure SSE (notificación push) | Sin polling de mi parte | 5 |
| US-20 | Mensajero | Aceptar/rechazar envío. Sistema reasigna via Messenger si timeout. | Gestionar mi trabajo | 5 |
| US-21 | Mensajero | Marcar recogido, en_camino, entregado (código + foto) | Completar el servicio | 5 |
| US-22 | Cliente | Calificar al mensajero al finalizar | Dar feedback | 2 |
| US-23 | Cliente | Ver y elegir mensajeros favoritos | Preferir mensajero conocido | 2 |

### Iteración 3 — Tracking y Chat Real-time *(35 puntos, ~1.5 semanas)*

| ID | Como… | Quiero… | Para… | Pts |
|---|---|---|---|:-:|
| US-24 | Dev | Mercure Hub configurado en Docker, token service, publisher | Infraestructura real-time lista | 5 |
| US-25 | Mensajero | Enviar ubicación GPS cada 10s → Redis → Mercure push | Ser visible en tiempo real | 5 |
| US-26 | Cliente | Ver ubicación del mensajero en mapa Leaflet via Mercure SSE | Tracking real sin polling | 5 |
| US-27 | Dev | Worker de Symfony Messenger (flush batch ubicaciones a MySQL cada 60s) | Reducir escrituras DB | 3 |
| US-28 | Cliente/Mensajero | Chat en tiempo real via Mercure SSE | Sin polling de mensajes | 5 |
| US-29 | Destinatario | Registrarme y participar en el chat del envío | Coordinar la entrega | 3 |
| US-30 | Sistema | Enviar código de verificación al chat automáticamente al crear envío | Que destinatario lo vea | 2 |
| US-31 | Dev | `UbicacionCacheRepository` (Redis ZSET) + endpoint tracking fallback | Robustez si Mercure falla | 3 |
| US-32 | Dev | Leaflet.js integrado con tiles OSM y polyline OSRM | Mapa funcional open-source | 4 |

### Iteración 4 — Envíos Planificados, Reportes y Operaciones *(28 puntos, ~1 semana)*

| ID | Como… | Quiero… | Para… | Pts |
|---|---|---|---|:-:|
| US-33 | Cliente | Crear envío planificado con fecha y ventana horaria | Programar recogidas | 5 |
| US-34 | Mensajero | Ver y aceptar planificados sin solapamiento | Organizar agenda | 5 |
| US-35 | Admin | Reporte diario JSON de pagos a mensajeros | Pagar al final del día | 3 |
| US-36 | Admin | Panel resumen: envíos, ingresos, mensajeros activos (con filtro de fechas) | Monitorear operación | 3 |
| US-37 | Admin | Configurar todos los parámetros desde el panel | Ajustar operación sin deploy | 3 |
| US-38 | Sistema | Crons de limpieza: fotos, chat, tokens, ubicaciones | Política de retención automática | 3 |
| US-39 | Cliente | Cancelar envío antes de recogido | Flexibilidad ante cambios | 3 |
| US-40 | Dev | Supervisor configurado para 2 workers Messenger en producción | Procesamiento async robusto | 3 |

---

## 14. Plan de Pruebas

### 14.1 Pruebas de flujos core

| ID | Módulo | Caso de Prueba | Resultado Esperado |
|---|---|---|---|
| PT-01 | Auth | Login correcto | JWT + refresh_token + mercure_token en respuesta |
| PT-02 | Auth | Logout e intento de uso del JWT | HTTP `401` (token en blacklist Redis) |
| PT-03 | Auth | Reset password: solicitar + usar token | Password cambiada, token marcado como usado |
| PT-04 | Auth | Reset password: token expirado | HTTP `422` |
| PT-05 | Mensajero | Registro con CI inválido | HTTP `422` con error `ci_invalido` |
| PT-06 | Mensajero | CI y tarjeta: verificar que en DB están cifrados | `SELECT ci_cifrado FROM mensajeros` devuelve binario, no texto |
| PT-07 | Mensajero | Mensajero no aprobado activa disponibilidad | HTTP `403` `pendiente_aprobacion` |
| PT-08 | Envío | Crear envío → OSRM calcula ruta | `ruta_distancia_m` y `ruta_polyline` no nulos en DB |
| PT-09 | Envío | Workflow: transición inválida (pendiente → entregado) | HTTP `422` `transicion_no_permitida` |
| PT-10 | Envío | Mensajero rechaza 3 veces → bloqueo 24h | `bloqueado_hasta = NOW() + 24h` |
| PT-11 | Envío | Timeout: Messenger reasigna en ~3 min | Envío reasignado a otro mensajero en logs |
| PT-12 | Envío | Código verificación incorrecto | HTTP `422` estado no cambia |
| PT-13 | Envío | Código correcto + foto → entregado | Estado = `entregado`, `entregado_at` poblado |
| PT-14 | Pago | Admin verifica comprobante | Estado envío → `pagado`, Mercure publica evento |
| PT-15 | Tracking | Mensajero envía ubicación | Redis actualizado, evento Mercure publicado |
| PT-16 | Tracking | Redis TTL 30s: sin update en 30s | `estaActivo()` devuelve false |
| PT-17 | Tracking | Flush batch ubicaciones a MySQL | Registros en DB cada 60s |
| PT-18 | Chat | Cliente envía mensaje | DB insertado, Mercure publicado a participantes |
| PT-19 | Chat | Admin intenta leer chat | HTTP `403` (ChatVoter) |
| PT-20 | Chat | Destinatario no registrado intenta acceder | HTTP `403` |
| PT-21 | Geocoding | Geocodificar misma dirección dos veces | Segunda vez: 0 llamadas a Nominatim (Redis hit) |
| PT-22 | Geocoding | OSRM calcular ruta mismos puntos dos veces | Segunda vez: Redis hit |
| PT-23 | Config | Actualizar configuración | APCu invalidado, siguiente `get()` consulta DB |
| PT-24 | Rate limit | POST /auth/login 11 veces en 15 min | 11ª petición → HTTP `429` |
| PT-25 | Security | URL con ID entero en lugar de UUID | HTTP `404` (el sistema no acepta IDs enteros en rutas públicas) |
| PT-26 | Planificado | Mensajero acepta envío solapado | HTTP `422` `solapamiento_horario` |
| PT-27 | Limpieza | `app:cleanup:fotos` con fotos vencidas | Archivos eliminados de disco, registros borrados de DB |

### 14.2 Pruebas de carga (para el VPS)

Con 100–500 usuarios concurrentes en VPS único, verificar:

| Escenario | Herramienta | Umbral de éxito |
|---|---|---|
| 200 mensajeros enviando ubicación simultáneamente cada 10s | k6 / Locust | p99 < 100ms, 0 errores |
| 500 clientes conectados a Mercure SSE | k6 (SSE) | Conexiones estables 5 min |
| 100 creaciones de envío simultáneas (con OSRM) | k6 | p99 < 500ms (OSRM cacheado) |
| 50 subidas de foto simultáneas | k6 | p99 < 2s |

### 14.3 Herramientas de testing

| Herramienta | Uso |
|---|---|
| **PHPUnit** + Symfony `WebTestCase` | Tests de integración de endpoints |
| **Zenstruck Foundry** | Factories de entidades tipadas para fixtures |
| **DAMA Doctrine Test Bundle** | Rollback automático de DB en cada test |
| **FakerPHP** | Datos cubanos realistas (nombres, CI válidos, teléfonos) |
| **PHPStan** (nivel 8) | Análisis estático — detecta bugs antes de tests |
| **k6** | Tests de carga y SSE Mercure |
| **Bruno** *(open-source)* | Colección de pruebas manuales E2E (alternativa a Postman) |
| **Xdebug** + Coverage | Cobertura mínima **80%** en servicios críticos |

---

## 15. Infraestructura y Despliegue

### 15.1 Docker Compose (VPS único)

```yaml
# docker-compose.prod.yml
services:

  app:
    image: mensajeria-app:latest
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    volumes:
      - ./var/uploads:/var/www/mensajeria/var/uploads
      - ./var/log:/var/www/mensajeria/var/log
    environment:
      APP_ENV: prod
    depends_on:
      - mysql
      - redis
      - mercure
    restart: unless-stopped

  nginx:
    image: nginx:1.26-alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/conf.d:/etc/nginx/conf.d
      - ./public:/var/www/mensajeria/public:ro
      - ./certbot/conf:/etc/letsencrypt:ro
    depends_on:
      - app
    restart: unless-stopped

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: mensajeria
      MYSQL_USER: mensajeria_user
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/conf.d:/etc/mysql/conf.d
    restart: unless-stopped

  redis:
    image: redis:7.2-alpine
    command: redis-server --requirepass ${REDIS_PASSWORD} --maxmemory 256mb --maxmemory-policy allkeys-lru
    volumes:
      - redis_data:/data
    restart: unless-stopped

  mercure:
    image: dunglas/mercure:v0.16
    environment:
      SERVER_NAME: ':3000'
      MERCURE_PUBLISHER_JWT_KEY: ${MERCURE_JWT_SECRET}
      MERCURE_SUBSCRIBER_JWT_KEY: ${MERCURE_JWT_SECRET}
      MERCURE_EXTRA_DIRECTIVES: |
        cors_origins https://tu-dominio.cu
        subscriptions
    ports:
      - "127.0.0.1:3000:3000"    # Solo accesible localmente; Nginx lo proxea
    restart: unless-stopped

  nominatim:
    image: mediagis/nominatim:4.4
    environment:
      PBF_URL: https://download.geofabrik.de/central-america/cuba-latest.osm.pbf
      REPLICATION_URL: https://download.geofabrik.de/central-america/cuba-updates/
    volumes:
      - nominatim_data:/var/lib/postgresql/14/main
    ports:
      - "127.0.0.1:8080:8080"
    restart: unless-stopped

  osrm:
    image: osrm/osrm-backend:v5.27.1
    volumes:
      - ./docker/osrm:/data
    command: osrm-routed --algorithm mld /data/cuba-latest.osrm
    ports:
      - "127.0.0.1:5000:5000"
    restart: unless-stopped

  worker:
    image: mensajeria-app:latest
    command: php bin/console messenger:consume async --time-limit=3600
    deploy:
      replicas: 2
    depends_on:
      - mysql
      - redis
    restart: unless-stopped

volumes:
  mysql_data:
  redis_data:
  nominatim_data:
```

### 15.2 Preparación del mapa OSRM para Cuba

```bash
# Ejecutar una sola vez para preparar los datos de Cuba
docker run -t -v ./docker/osrm:/data osrm/osrm-backend:v5.27.1 \
    osrm-extract -p /opt/car.lua /data/cuba-latest.osm.pbf

docker run -t -v ./docker/osrm:/data osrm/osrm-backend:v5.27.1 \
    osrm-partition /data/cuba-latest.osrm

docker run -t -v ./docker/osrm:/data osrm/osrm-backend:v5.27.1 \
    osrm-customize /data/cuba-latest.osrm
```

El archivo `.osm.pbf` de Cuba pesa ~50 MB. El procesamiento tarda ~5 minutos y genera ~200 MB de datos listos para servir.

### 15.3 Recursos estimados del VPS

| Servicio | RAM estimada | CPU (idle/carga) |
|---|:-:|:-:|
| PHP-FPM (8 workers) | ~200 MB | 5% / 60% |
| MySQL 8.0 | ~256 MB | 2% / 40% |
| Redis 7.2 | ~50 MB | <1% / 5% |
| Mercure Hub | ~30 MB | <1% / 10% |
| Nominatim | ~512 MB | <1% / 30% |
| OSRM | ~128 MB | <1% / 20% |
| Nginx | ~20 MB | <1% / 5% |
| Workers Messenger (×2) | ~60 MB | <1% / 10% |
| **Total estimado** | **~1.25 GB** | — |

**VPS recomendado:** 4 GB RAM, 4 vCPU, 80 GB SSD NVMe. Nominatim es el servicio que más RAM consume; con 4 GB hay margen suficiente.

### 15.4 MySQL — ajuste de rendimiento para VPS

```ini
# /docker/mysql/conf.d/performance.cnf
[mysqld]
innodb_buffer_pool_size = 512M          # 512 MB para el buffer pool de InnoDB
innodb_log_file_size = 128M
innodb_flush_log_at_trx_commit = 2      # Mejor rendimiento (pérdida máx. 1s de datos en crash)
query_cache_type = 0                    # Desactivado en MySQL 8 (deprecated)
max_connections = 150
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 0.5
```

---

> *— Documentación Técnica v2.0 — Plataforma de Mensajería Bajo Demanda —*  
> *Generada como plan de desarrollo funcional completo. Revisión recomendada antes de cada iteración.*