<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Mensaje para notificaciones en tiempo real vía Mercure.
 */
final class NotificationMessage
{
    public function __construct(
        private readonly string $type,
        private readonly array $data,
        private readonly ?string $topic = null,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }
}
