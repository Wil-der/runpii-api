<?php

declare(strict_types=1);

namespace App\Auth\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class RefreshTokenDTO
{
    #[Assert\NotBlank(message: 'El refresh token es obligatorio')]
    public string $refreshToken;
}
