<?php

declare(strict_types=1);

namespace App\Auth\Controller;

use App\Auth\DTO\LoginDTO;
use App\Auth\DTO\RegistroDTO;
use App\Auth\DTO\ResetPasswordRequestDTO;
use App\Auth\DTO\ResetPasswordConfirmDTO;
use App\Auth\Service\AuthService;
use App\Auth\Service\TokenBlacklistService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TokenBlacklistService $tokenBlacklistService,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer
    ) {
    }

    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!is_array($data)) {
            throw new BadRequestHttpException('Datos inválidos');
        }

        $dto = new RegistroDTO();
        $dto->nombre = $data['nombre'] ?? '';
        $dto->apellidos = $data['apellidos'] ?? '';
        $dto->email = $data['email'] ?? '';
        $dto->password = $data['password'] ?? '';
        $dto->tipoUsuario = $data['tipoUsuario'] ?? '';
        $dto->telefono = $data['telefono'] ?? null;
        $dto->ci = $data['ci'] ?? null;

        // Validar el DTO (se puede usar validator service si es necesario)
        // Por simplicidad, validaciones básicas aquí
        if (empty($dto->nombre) || empty($dto->apellidos) || empty($dto->email) || 
            empty($dto->password) || empty($dto->tipoUsuario)) {
            throw new BadRequestHttpException('Todos los campos requeridos son obligatorios');
        }

        if (!in_array($dto->tipoUsuario, ['cliente', 'mensajero', 'admin'], true)) {
            throw new BadRequestHttpException('Tipo de usuario no válido');
        }

        try {
            $usuario = $this->authService->registrar($dto);
            
            return new JsonResponse([
                'message' => 'Usuario registrado exitosamente',
                'usuario' => [
                    'id' => $usuario->getId(),
                    'email' => $usuario->getEmail(),
                    'nombre' => $usuario->getNombre(),
                    'apellidos' => $usuario->getApellidos(),
                    'tipoUsuario' => $usuario->getTipoUsuario(),
                    'estado' => $usuario->getEstado()
                ]
            ], JsonResponse::HTTP_CREATED);
        } catch (BadRequestHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Error al registrar el usuario: ' . $e->getMessage());
        }
    }

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!is_array($data)) {
            throw new BadRequestHttpException('Datos inválidos');
        }

        $dto = new LoginDTO();
        $dto->email = $data['email'] ?? '';
        $dto->password = $data['password'] ?? '';

        if (empty($dto->email) || empty($dto->password)) {
            throw new BadRequestHttpException('Email y contraseña son obligatorios');
        }

        $usuario = $this->authService->buscarPorEmail($dto->email);

        if (!$usuario) {
            throw new UnauthorizedHttpException('Credenciales inválidas');
        }

        // Verificar estado del usuario
        if ($usuario->getEstado() === 'pendiente_aprobacion') {
            throw new UnauthorizedHttpException('Tu cuenta está pendiente de aprobación por un administrador');
        }

        if ($usuario->getEstado() !== 'activo') {
            throw new UnauthorizedHttpException('Tu cuenta está inactiva');
        }

        // Verificar contraseña
        if (!$this->passwordHasher->isPasswordValid($usuario, $dto->password)) {
            throw new UnauthorizedHttpException('Credenciales inválidas');
        }

        // Generar token JWT
        $payload = [
            'user_id' => $usuario->getId(),
            'email' => $usuario->getEmail(),
            'roles' => $usuario->getRoles()
        ];

        $jwt = $this->jwtManager->createFromPayload($usuario, $payload);

        return new JsonResponse([
            'token' => $jwt,
            'usuario' => [
                'id' => $usuario->getId(),
                'email' => $usuario->getEmail(),
                'nombre' => $usuario->getNombre(),
                'apellidos' => $usuario->getApellidos(),
                'tipoUsuario' => $usuario->getTipoUsuario()
            ]
        ]);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        // Obtener el token del header Authorization
        $authorizationHeader = $request->headers->get('Authorization');
        
        if (!$authorizationHeader || !str_starts_with($authorizationHeader, 'Bearer ')) {
            throw new BadRequestHttpException('Token no proporcionado');
        }

        $token = substr($authorizationHeader, 7); // Remover "Bearer "
        
        // Añadir token a la blacklist
        $this->tokenBlacklistService->addToBlacklist($token);

        return new JsonResponse(['message' => 'Sesión cerrada exitosamente']);
    }

    #[Route('/refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!is_array($data) || empty($data['refreshToken'])) {
            throw new BadRequestHttpException('Refresh token requerido');
        }

        // Aquí se implementaría la lógica de refresh token
        // Por simplicidad, este endpoint requiere implementación adicional
        // con RefreshTokenRepository y generación de nuevo JWT
        
        throw new BadRequestHttpException('Funcionalidad de refresh en implementación');
    }

    #[Route('/forgot-password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!is_array($data) || empty($data['email'])) {
            throw new BadRequestHttpException('Email es obligatorio');
        }

        $usuario = $this->authService->buscarPorEmail($data['email']);

        // Siempre retornar éxito para evitar enumeración de usuarios
        if (!$usuario) {
            return new JsonResponse([
                'message' => 'Si el email está registrado, recibirás un enlace para restablecer tu contraseña'
            ]);
        }

        // Generar token de reseteo (esto debería guardarse en PasswordResetToken entity)
        $resetToken = bin2hex(random_bytes(32));
        
        // TODO: Guardar el token en PasswordResetTokenRepository con expiración
        
        // Enviar email con enlace de reseteo
        // $email = (new TemplatedEmail())
        //     ->from('no-reply@tudominio.com')
        //     ->to($usuario->getEmail())
        //     ->subject('Restablecimiento de contraseña')
        //     ->htmlTemplate('emails/reset_password.html.twig')
        //     ->context(['resetToken' => $resetToken, 'usuario' => $usuario]);
        // 
        // $this->mailer->send($email);

        return new JsonResponse([
            'message' => 'Si el email está registrado, recibirás un enlace para restablecer tu contraseña'
        ]);
    }

    #[Route('/reset-password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!is_array($data) || empty($data['token']) || empty($data['password'])) {
            throw new BadRequestHttpException('Token y nueva contraseña son obligatorios');
        }

        if (strlen($data['password']) < 8) {
            throw new BadRequestHttpException('La contraseña debe tener al menos 8 caracteres');
        }

        // TODO: Validar el token con PasswordResetTokenRepository
        // TODO: Buscar usuario asociado al token
        // TODO: Verificar que el token no haya expirado
        
        // Ejemplo de implementación (pendiente de completar con PasswordResetToken):
        // $resetTokenEntity = $this->passwordResetTokenRepository->findByToken($data['token']);
        // if (!$resetTokenEntity || $resetTokenEntity->getExpiresAt() < new \DateTime()) {
        //     throw new BadRequestHttpException('Token inválido o expirado');
        // }
        // 
        // $usuario = $resetTokenEntity->getUsuario();
        // $hashedPassword = $this->passwordHasher->hashPassword($usuario, $data['password']);
        // $usuario->setPassword($hashedPassword);
        // $this->usuarioRepository->guardar($usuario);
        // 
        // // Invalidar el token usado
        // $this->passwordResetTokenRepository->remove($resetTokenEntity);

        throw new BadRequestHttpException('Funcionalidad de reseteo de contraseña en implementación');
    }
}
