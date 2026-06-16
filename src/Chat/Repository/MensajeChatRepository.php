<?php

declare(strict_types=1);

namespace App\Chat\Repository;

use App\Chat\Entity\MensajeChat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MensajeChat>
 */
class MensajeChatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MensajeChat::class);
    }

    public function save(MensajeChat $mensaje, bool $flush = false): void
    {
        $this->getEntityManager()->persist($mensaje);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MensajeChat $mensaje, bool $flush = false): void
    {
        $this->getEntityManager()->remove($mensaje);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Busca mensajes por envío
     */
    public function findByEnvio(int $envioId, int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.envio = :envioId')
            ->setParameter('envioId', $envioId)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca mensajes expirados para limpieza
     */
    public function findExpirados(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Marca mensajes como leídos por cliente
     */
    public function marcarComoLeidosPorCliente(int $envioId): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->update(MensajeChat::class, 'm')
            ->set('m.leidoCliente', ':leido')
            ->where('m.envio = :envioId')
            ->andWhere('m.leidoCliente = false')
            ->setParameter('leido', true)
            ->setParameter('envioId', $envioId);

        return $qb->getQuery()->execute();
    }

    /**
     * Marca mensajes como leídos por mensajero
     */
    public function marcarComoLeidosPorMensajero(int $envioId): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->update(MensajeChat::class, 'm')
            ->set('m.leidoMensajero', ':leido')
            ->where('m.envio = :envioId')
            ->andWhere('m.leidoMensajero = false')
            ->setParameter('leido', true)
            ->setParameter('envioId', $envioId);

        return $qb->getQuery()->execute();
    }

    /**
     * Marca mensajes como leídos por destinatario
     */
    public function marcarComoLeidosPorDestinatario(int $envioId): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->update(MensajeChat::class, 'm')
            ->set('m.leidoDestinatario', ':leido')
            ->where('m.envio = :envioId')
            ->andWhere('m.leidoDestinatario = false')
            ->setParameter('leido', true)
            ->setParameter('envioId', $envioId);

        return $qb->getQuery()->execute();
    }

    /**
     * Cuenta mensajes no leídos por tipo de lector
     */
    public function countNoLeidos(int $envioId, string $tipoLector): int
    {
        $campo = match ($tipoLector) {
            'cliente' => 'm.leidoCliente',
            'mensajero' => 'm.leidoMensajero',
            'destinatario' => 'm.leidoDestinatario',
            default => 'm.leidoCliente',
        };

        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.envio = :envioId')
            ->andWhere("$campo = false")
            ->setParameter('envioId', $envioId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
