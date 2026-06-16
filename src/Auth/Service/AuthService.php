<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\DTO\RegistroDTO;
use App\Auth\Entity\Usuario;
use App\Auth\Repository\UsuarioRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AuthService
{
    public function __construct(
        private readonly UsuarioRepository $usuarioRepository,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Registra un nuevo usuario en el sistema
     * 
     * @throws BadRequestHttpException Si el email ya existe
     */
    public function registrar(RegistroDTO $dto): Usuario
    {
        // Verificar si el email ya existe
        if ($this->usuarioRepository->findOneBy(['email' => $dto->email])) {
            throw new BadRequestHttpException('El email ya está registrado');
        }

        // Crear nuevo usuario
        $usuario = new Usuario();
        $usuario->setNombre($dto->nombre);
        $usuario->setApellidos($dto->apellidos);
        $usuario->setEmail($dto->email);
        $usuario->setPassword(
            $this->passwordHasher->hashPassword($usuario, $dto->password)
        );
        $usuario->setTipoUsuario($dto->tipoUsuario);
        
        if ($dto->telefono) {
            $usuario->setTelefono($dto->telefono);
        }
        
        if ($dto->ci) {
            $usuario->setCi($dto->ci);
        }

        // Establecer estado según el tipo de usuario
        // Los mensajeros requieren aprobación, los demás están activos por defecto
        if ($dto->tipoUsuario === 'mensajero') {
            $usuario->setEstado('pendiente_aprobacion');
        } else {
            $usuario->setEstado('activo');
        }

        $this->usuarioRepository->guardar($usuario);

        return $usuario;
    }

    /**
     * Busca un usuario por email
     */
    public function buscarPorEmail(string $email): ?Usuario
    {
        return $this->usuarioRepository->findOneBy(['email' => $email]);
    }
}
