# Guía de Ejecución de Pruebas

## Requisitos Previos

1. Tener Docker instalado y ejecutándose
2. Tener los servicios levantados: `docker compose up -d`
3. Haber generado las claves JWT: `docker compose exec php php bin/console lexik:jwt:generate-keypair`
4. Haber aplicado las migraciones: `docker compose exec php php bin/console doctrine:migrations:migrate`

## Ejecutar Pruebas Unitarias

```bash
# Todas las pruebas unitarias
docker compose exec php php bin/phpunit tests/Unit

# Solo pruebas del módulo Auth
docker compose exec php php bin/phpunit tests/Unit/Auth

# Prueba específica
docker compose exec php php bin/phpunit tests/Unit/Auth/Service/AuthServiceTest.php
```

## Ejecutar Pruebas Funcionales

```bash
# Todas las pruebas funcionales
docker compose exec php php bin/phpunit tests/Functional

# Solo pruebas del módulo Auth
docker compose exec php php bin/phpunit tests/Functional/Auth
```

## Ejecutar Todas las Pruebas

```bash
docker compose exec php php bin/phpunit
```

## Verificar Cobertura de Código (requiere xdebug)

```bash
docker compose exec php php -dxdebug.mode=coverage bin/phpunit --coverage-html var/coverage
```

Luego abre `var/coverage/index.html` en tu navegador.

## Estructura de Pruebas Creadas

```
tests/
├── bootstrap.php                    # Archivo de inicialización
├── Unit/
│   └── Auth/
│       ├── Service/
│       │   ├── AuthServiceTest.php              # Pruebas para AuthService
│       │   └── TokenBlacklistServiceTest.php    # Pruebas para TokenBlacklistService
│       └── EventListener/
│           └── JwtAuthListenerTest.php          # Pruebas para JwtAuthListener
└── Functional/
    └── Auth/
        └── AuthControllerTest.php               # Pruebas de integración para AuthController
```

## Pruebas Implementadas

### Unitarias
- **AuthServiceTest**: Registro exitoso, usuario existente, login correcto, credenciales inválidas, logout
- **TokenBlacklistServiceTest**: Añadir a blacklist, verificar tokens revocados, limpiar blacklist
- **JwtAuthListenerTest**: Tokens blacklisted rechazados, tokens válidos aceptados

### Funcionales
- **AuthControllerTest**: Endpoints /api/auth/register, /api/auth/login, /api/auth/logout, /api/auth/refresh, /api/auth/me

## Notas Importantes

1. Las pruebas funcionales requieren que la base de datos esté configurada en el entorno de test
2. Para ejecutar pruebas funcionales aisladas, configura `DATABASE_URL` en `.env.test`
3. Los tests unitarios no requieren base de datos ni Redis, usan mocks y ArrayAdapter
