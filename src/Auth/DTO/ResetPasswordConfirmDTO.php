<?php

declare(strict_types=1);

namespace App\Auth\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordConfirmDTO
{
    #[Assert\NotBlank(message: 'El token es obligatorio')]
    public string $token;

    #[Assert\NotBlank(message: 'La nueva contraseña es obligatoria')]
    #[Assert\Length(min: 8, minMessage: 'La contraseña debe tener al menos 8 caracteres')]
    public string $password;
}
