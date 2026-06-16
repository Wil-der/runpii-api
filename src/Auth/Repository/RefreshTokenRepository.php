<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function save(RefreshToken $refreshToken, bool $flush = false): void
    {
        $this->getEntityManager()->persist($refreshToken);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RefreshToken $refreshToken, bool $flush = false): void
    {
        $this->getEntityManager()->remove($refreshToken);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByToken(string $token): ?RefreshToken
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Busca tokens expirados para limpieza
     */
    public function findExpirados(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.expiresAt < :now OR r.revocado = true')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Invalida todos los tokens de un usuario
     */
    public function revocarPorUsuario(int $usuarioId): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->update(RefreshToken::class, 'r')
            ->set('r.revocado', ':revocado')
            ->where('r.usuario = :usuarioId')
            ->setParameter('revocado', true)
            ->setParameter('usuarioId', $usuarioId);

        return $qb->getQuery()->execute();
    }

    /**
     * Elimina tokens expirados o revocados
     */
    public function eliminarExpiradosORevocados(): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->delete(RefreshToken::class, 'r')
            ->where('r.expiresAt < :now OR r.revocado = true')
            ->setParameter('now', new \DateTime());

        return $qb->getQuery()->execute();
    }

    /**
     * Cuenta tokens activos por usuario
     */
    public function countActivosPorUsuario(int $usuarioId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.usuario = :usuarioId')
            ->andWhere('r.expiresAt > :now')
            ->andWhere('r.revocado = false')
            ->setParameter('usuarioId', $usuarioId)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
