# 📦 Plataforma de Mensajería Bajo Demanda v2.0

Plataforma de mensajería bajo demanda desarrollada con Symfony 7.4 y PHP 8.3.

## 🚀 Requisitos Previos

- Docker y Docker Compose
- PHP 8.3+ (para desarrollo local)
- Composer 2.x

## 🏗️ Arquitectura

### Stack Tecnológico
- **Backend:** Symfony 7.4 LTS + PHP 8.3
- **Base de Datos:** MariaDB 10.11
- **Caché/Colas:** Redis 7.2
- **Tiempo Real:** Mercure Hub
- **Geocoding:** Nominatim (OpenStreetMap)
- **Rutas:** OSRM (Open Source Routing Machine)

### Estructura del Proyecto
```
src/
├── Controller/
│   ├── Api/          # Controladores API REST
│   └── Web/          # Controladores web tradicionales
├── Entity/           # Entidades Doctrine
├── Repository/       # Repositorios customizados
├── Service/          # Servicios de negocio
├── Message/          # Mensajes para Messenger
├── Event/            # Eventos personalizados
├── EventListener/    # Listeners de eventos
├── DTO/              # Data Transfer Objects
└── Validator/        # Validadores customizados
```

## 🛠️ Configuración del Entorno

### 1. Iniciar Servicios Docker

```bash
# Iniciar todos los servicios (excepto la app PHP que corre localmente)
docker-compose up -d mariadb redis mercure nominatim osrm

# Verificar estado
docker-compose ps
```

### 2. Instalar Dependencias PHP

```bash
composer install
```

### 3. Configurar Variables de Entorno

El archivo `.env.local` ya está configurado con valores por defecto:

```bash
# Ver configuración
cat .env.local
```

### 4. Configurar Base de Datos

```bash
# Crear base de datos
php bin/console doctrine:database:create

# Ejecutar migraciones (cuando existan)
php bin/console doctrine:migrations:migrate
```

## 📁 Servicios Docker Disponibles

| Servicio     | Puerto  | URL                        |
|-------------|---------|----------------------------|
| MariaDB     | 3306    | localhost:3306             |
| Redis       | 6379    | localhost:6379             |
| Mercure     | 8080    | http://localhost:8080      |
| Nominatim   | 8081    | http://localhost:8081      |
| OSRM        | 5000    | http://localhost:5000      |

## 🔧 Comandos Útiles

```bash
# Limpiar caché
php bin/console cache:clear

# Verificar configuración
php bin/console debug:config

# Ver rutas
php bin/console debug:router

# Ejecutar tests
php bin/phpunit

# Worker para colas (en segundo plano)
php bin/console messenger:consume async -vv
```

## 📝 Próximos Pasos

1. **Fase 2:** Implementar módulos de autenticación y registro
2. **Fase 3:** Desarrollar API de geolocalización
3. **Fase 4:** Sistema de mensajería en tiempo real
4. **Fase 5:** Módulo de reservas y seguimiento

## 📄 Licencia

Propietaria - Todos los derechos reservados.
