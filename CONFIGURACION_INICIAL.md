# Guía de Configuración Inicial

## Pasos realizados automáticamente:

1. ✅ **Limpieza de entidades duplicadas**: Se eliminó el directorio `src/Entity/` que contenía entidades duplicadas en inglés que entraban en conflicto con la arquitectura modular.

2. ✅ **Configuración de Doctrine**: Se actualizó `config/packages/doctrine.yaml` para mapear correctamente todas las entidades modulares según la guía:
   - Auth (Usuario, RefreshToken, PasswordResetToken)
   - Mensajero (Mensajero)
   - Envio (Envio, FotoEnvio, Calificacion)
   - Tracking (UbicacionMensajero)
   - Chat (MensajeChat)
   - Pago (Pago, Favorito)
   - Admin

3. ✅ **Variables de entorno**: Se agregaron al `.env`:
   - `APP_SECRET`
   - Variables JWT (`JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE`, `JWT_TTL`)
   - `ENCRYPTION_KEY` para cifrado AES-256-GCM

4. ✅ **Directorio JWT**: Se creó `config/jwt/` para almacenar las claves.

## Próximos pasos MANUALES (requieren Docker):

### 1. Generar claves JWT
```bash
docker compose exec php php bin/console lexik:jwt:generate-keypair
```

### 2. Iniciar servicios Docker
```bash
docker compose up -d
```

### 3. Generar migraciones
```bash
docker compose exec php php bin/console make:migration
```

### 4. Aplicar migraciones
```bash
docker compose exec php php bin/console doctrine:migrations:migrate
```

### 5. Verificar configuración
```bash
docker compose exec php php bin/console doctrine:schema:validate
```

## Notas importantes:

- Las claves JWT generadas se guardarán en `config/jwt/private.pem` y `config/jwt/public.pem`
- El `ENCRYPTION_KEY` debe ser un hex string de 64 caracteres (32 bytes) para AES-256-GCM
- En producción, usar valores seguros generados con:
  - `openssl rand -hex 32` para APP_SECRET y ENCRYPTION_KEY
  - `openssl genpkey -algorithm RSA` para JWT keys
