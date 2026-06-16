<?php

declare(strict_types=1);

namespace App\Auth\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class RegistroDTO
{
    #[Assert\NotBlank(message: 'El nombre es obligatorio')]
    #[Assert\Length(min: 2, max: 100)]
    public string $nombre;

    #[Assert\NotBlank(message: 'Los apellidos son obligatorios')]
    #[Assert\Length(min: 2, max: 100)]
    public string $apellidos;

    #[Assert\NotBlank(message: 'El email es obligatorio')]
    #[Assert\Email(message: 'El email no es válido')]
    public string $email;

    #[Assert\NotBlank(message: 'La contraseña es obligatoria')]
    #[Assert\Length(min: 8, minMessage: 'La contraseña debe tener al menos 8 caracteres')]
    public string $password;

    #[Assert\NotBlank(message: 'El tipo de usuario es obligatorio')]
    public string $tipoUsuario; // 'cliente', 'mensajero', 'admin'

    #[Assert\Optional]
    #[Assert\Type(type: 'string')]
    public ?string $telefono = null;

    #[Assert\Optional]
    #[Assert\Type(type: 'string')]
    public ?string $ci = null;
}
