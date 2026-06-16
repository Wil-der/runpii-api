# Implementación del Módulo de Autenticación

## Archivos Creados

### DTOs (Data Transfer Objects)
- `src/Auth/DTO/RegistroDTO.php` - Validación para registro de usuarios
- `src/Auth/DTO/LoginDTO.php` - Validación para login
- `src/Auth/DTO/ResetPasswordRequestDTO.php` - Validación para solicitud de reseteo
- `src/Auth/DTO/ResetPasswordConfirmDTO.php` - Validación para confirmación de reseteo

### Servicios
- `src/Auth/Service/AuthService.php` - Lógica de negocio para registro y búsqueda de usuarios
- `src/Auth/Service/TokenBlacklistService.php` - Gestión de blacklist de tokens JWT en Redis

### Controlador
- `src/Auth/Controller/AuthController.php` - Endpoints API:
  - `POST /api/auth/register` - Registro de nuevos usuarios
  - `POST /api/auth/login` - Login y obtención de JWT
  - `POST /api/auth/logout` - Logout (invalida token)
  - `POST /api/auth/refresh` - Refresh de token (pendiente completar)
  - `POST /api/auth/forgot-password` - Solicitud de reseteo de contraseña
  - `POST /api/auth/reset-password` - Resetear contraseña (pendiente completar)

### Event Listener
- `src/Auth/EventListener/JWTBlacklistListener.php` - Verifica tokens contra la blacklist en cada request

### Configuración
- `config/packages/auth.yaml` - Configuración de servicios y JWT
- `.env` - Variables de entorno para JWT y Redis

## Próximos Pasos (Requieren Docker)

1. **Generar claves JWT:**
   ```bash
   docker compose exec php php bin/console lexik:jwt:generate-keypair
   ```

2. **Configurar Redis en services.yaml:**
   ```yaml
   services:
       redis_connection:
           class: \Redis
           calls:
               - ['connect', ['%env(REDIS_HOST)%', %env(int:REDIS_PORT)%]]
   ```

3. **Generar migraciones:**
   ```bash
   docker compose exec php php bin/console make:migration
   ```

4. **Aplicar migraciones:**
   ```bash
   docker compose exec php php bin/console doctrine:migrations:migrate
   ```

5. **Verificar schema:**
   ```bash
   docker compose exec php php bin/console doctrine:schema:validate
   ```

## Endpoints Disponibles

### Registro
```bash
POST /api/auth/register
Content-Type: application/json

{
    "nombre": "Juan",
    "apellidos": "Pérez",
    "email": "juan@example.com",
    "password": "password123",
    "tipoUsuario": "cliente",
    "telefono": "+5351234567",
    "ci": "12345678901"
}
```

### Login
```bash
POST /api/auth/login
Content-Type: application/json

{
    "email": "juan@example.com",
    "password": "password123"
}
```

### Logout
```bash
POST /api/auth/logout
Authorization: Bearer {token}
```

## Notas Importantes

1. Los mensajeros quedan con estado `pendiente_aprobacion` y requieren aprobación administrativa
2. Los clientes y admins quedan `activo` automáticamente
3. El logout invalida el token actual mediante blacklist en Redis
4. Los tokens JWT tienen un TTL de 1 hora (configurable)
5. Faltan implementar completamente los endpoints de refresh token y reseteo de contraseña

