<?php

declare(strict_types=1);

namespace App\Tracking\Repository;

use App\Tracking\Entity\UbicacionMensajero;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UbicacionMensajero>
 */
class UbicacionMensajeroRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UbicacionMensajero::class);
    }

    public function save(UbicacionMensajero $ubicacion, bool $flush = false): void
    {
        $this->getEntityManager()->persist($ubicacion);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UbicacionMensajero $ubicacion, bool $flush = false): void
    {
        $this->getEntityManager()->remove($ubicacion);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Obtiene las últimas ubicaciones de un mensajero
     */
    public function findUltimasPorMensajero(int $mensajeroId, int $limit = 100): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.mensajero = :mensajeroId')
            ->setParameter('mensajeroId', $mensajeroId)
            ->orderBy('u.registradoAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene la última ubicación de un mensajero
     */
    public function findUltimaPorMensajero(int $mensajeroId): ?UbicacionMensajero
    {
        return $this->createQueryBuilder('u')
            ->where('u.mensajero = :mensajeroId')
            ->setParameter('mensajeroId', $mensajeroId)
            ->orderBy('u.registradoAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Busca ubicaciones en un rango de tiempo
     */
    public function findByRangoTiempo(int $mensajeroId, \DateTimeInterface $desde, \DateTimeInterface $hasta): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.mensajero = :mensajeroId')
            ->andWhere('u.registradoAt BETWEEN :desde AND :hasta')
            ->setParameter('mensajeroId', $mensajeroId)
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->orderBy('u.registradoAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Elimina ubicaciones antiguas (para limpieza)
     */
    public function eliminarAntiguas(\DateTimeInterface $fechaLimite): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->delete(UbicacionMensajero::class, 'u')
            ->where('u.registradoAt < :fechaLimite')
            ->setParameter('fechaLimite', $fechaLimite);

        return $qb->getQuery()->execute();
    }

    /**
     * Cuenta ubicaciones por mensajero
     */
    public function countPorMensajero(int $mensajeroId): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.mensajero = :mensajeroId')
            ->setParameter('mensajeroId', $mensajeroId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
