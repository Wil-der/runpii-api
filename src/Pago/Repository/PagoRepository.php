<?php

declare(strict_types=1);

namespace App\Pago\Repository;

use App\Pago\Entity\Pago;
use App\Pago\Entity\EstadoPago;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pago>
 */
class PagoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pago::class);
    }

    public function save(Pago $pago, bool $flush = false): void
    {
        $this->getEntityManager()->persist($pago);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Pago $pago, bool $flush = false): void
    {
        $this->getEntityManager()->remove($pago);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByEnvio(int $envioId): ?Pago
    {
        return $this->findOneBy(['envio' => $envioId]);
    }

    /**
     * Busca pagos por estado
     */
    public function findByEstado(EstadoPago $estado, int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.estado = :estado')
            ->setParameter('estado', $estado)
            ->innerJoin('p.envio', 'e')
            ->addSelect('e')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca pagos pendientes de verificación
     */
    public function findPendientesDeVerificacion(int $limit = 50): array
    {
        return $this->findByEstado(EstadoPago::PENDIENTE, $limit);
    }

    /**
     * Cuenta pagos por estado
     */
    public function countByEstado(EstadoPago $estado): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.estado = :estado')
            ->setParameter('estado', $estado)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtiene el total recaudado en un período
     */
    public function getTotalRecaudado(\DateTimeInterface $desde, \DateTimeInterface $hasta): string
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.monto) as total')
            ->where('p.estado = :estado')
            ->andWhere('p.verificadoAt BETWEEN :desde AND :hasta')
            ->setParameter('estado', EstadoPago::VERIFICADO)
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['total'] ?? '0.00';
    }

    /**
     * Busca pagos por método de pago
     */
    public function findByMetodo(\App\Pago\Entity\MetodoPago $metodo, int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.metodo = :metodo')
            ->setParameter('metodo', $metodo)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
