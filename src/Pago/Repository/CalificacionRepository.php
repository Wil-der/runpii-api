<?php

declare(strict_types=1);

namespace App\Pago\Repository;

use App\Pago\Entity\Calificacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Calificacion>
 */
class CalificacionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Calificacion::class);
    }

    public function save(Calificacion $calificacion, bool $flush = false): void
    {
        $this->getEntityManager()->persist($calificacion);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Calificacion $calificacion, bool $flush = false): void
    {
        $this->getEntityManager()->remove($calificacion);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Busca calificaciones por mensajero
     */
    public function findByMensajero(int $mensajeroId, int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.mensajero = :mensajeroId')
            ->setParameter('mensajeroId', $mensajeroId)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcula la calificación promedio de un mensajero
     */
    public function calcularPromedioPorMensajero(int $mensajeroId): float
    {
        $result = $this->createQueryBuilder('c')
            ->select('AVG(c.estrellas) as promedio')
            ->where('c.mensajero = :mensajeroId')
            ->setParameter('mensajeroId', $mensajeroId)
            ->getQuery()
            ->getOneOrNullResult();

        return (float) ($result['promedio'] ?? 0);
    }

    /**
     * Cuenta calificaciones por mensajero
     */
    public function countPorMensajero(int $mensajeroId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.mensajero = :mensajeroId')
            ->setParameter('mensajeroId', $mensajeroId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Busca calificaciones por rango de estrellas
     */
    public function findByRangoEstrellas(int $minEstrellas, int $maxEstrellas, int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.estrellas BETWEEN :min AND :max')
            ->setParameter('min', $minEstrellas)
            ->setParameter('max', $maxEstrellas)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene estadísticas de calificaciones (distribución por estrellas)
     */
    public function getDistribucionPorEstrellas(int $mensajeroId): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.estrellas, COUNT(c.id) as cantidad')
            ->where('c.mensajero = :mensajeroId')
            ->setParameter('mensajeroId', $mensajeroId)
            ->groupBy('c.estrellas')
            ->orderBy('c.estrellas', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
