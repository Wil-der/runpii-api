<?php

declare(strict_types=1);

namespace App\Envio\Repository;

use App\Envio\Entity\FotoEnvio;
use App\Envio\Entity\TipoFotoEnvio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FotoEnvio>
 */
class FotoEnvioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FotoEnvio::class);
    }

    public function save(FotoEnvio $fotoEnvio, bool $flush = false): void
    {
        $this->getEntityManager()->persist($fotoEnvio);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FotoEnvio $fotoEnvio, bool $flush = false): void
    {
        $this->getEntityManager()->remove($fotoEnvio);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Busca fotos por envío
     */
    public function findByEnvio(int $envioId): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.envio = :envioId')
            ->setParameter('envioId', $envioId)
            ->orderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca fotos por tipo
     */
    public function findByTipo(TipoFotoEnvio $tipo, int $limit = 50): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.tipo = :tipo')
            ->setParameter('tipo', $tipo)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca fotos expiradas para limpieza
     */
    public function findExpiradas(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta fotos por envío y tipo
     */
    public function countByEnvioYTipo(int $envioId, TipoFotoEnvio $tipo): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.envio = :envioId')
            ->andWhere('f.tipo = :tipo')
            ->setParameter('envioId', $envioId)
            ->setParameter('tipo', $tipo)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
