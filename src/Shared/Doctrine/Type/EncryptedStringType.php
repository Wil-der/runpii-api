<?php

declare(strict_types=1);

namespace App\Shared\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/**
 * Tipo de campo personalizado para almacenar strings encriptados.
 */
class EncryptedStringType extends StringType
{
    public function getName(): string
    {
        return 'encrypted_string';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
