<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\PasswordResetToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function save(PasswordResetToken $token, bool $flush = false): void
    {
        $this->getEntityManager()->persist($token);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PasswordResetToken $token, bool $flush = false): void
    {
        $this->getEntityManager()->remove($token);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByToken(string $token): ?PasswordResetToken
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Busca tokens expirados o usados para limpieza
     */
    public function findExpiradosOUdados(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.expiresAt < :now OR t.usado = true')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Marca un token como usado
     */
    public function marcarComoUsado(string $token): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->update(PasswordResetToken::class, 't')
            ->set('t.usado', ':usado')
            ->where('t.token = :token')
            ->setParameter('usado', true)
            ->setParameter('token', $token);

        return $qb->getQuery()->execute();
    }

    /**
     * Elimina tokens expirados o usados
     */
    public function eliminarExpiradosOUdados(): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->delete(PasswordResetToken::class, 't')
            ->where('t.expiresAt < :now OR t.usado = true')
            ->setParameter('now', new \DateTime());

        return $qb->getQuery()->execute();
    }

    /**
     * Invalida todos los tokens pendientes de un usuario
     */
    public function invalidarPorUsuario(int $usuarioId): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->update(PasswordResetToken::class, 't')
            ->set('t.usado', ':usado')
            ->where('t.usuario = :usuarioId')
            ->andWhere('t.usado = false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('usado', true)
            ->setParameter('usuarioId', $usuarioId)
            ->setParameter('now', new \DateTime());

        return $qb->getQuery()->execute();
    }
}
