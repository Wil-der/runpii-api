<?php

declare(strict_types=1);

namespace App\Envio\Repository;

use App\Envio\Entity\Envio;
use App\Envio\Entity\EstadoEnvio;
use App\Envio\Entity\TipoEnvio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Envio>
 */
class EnvioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Envio::class);
    }

    public function save(Envio $envio, bool $flush = false): void
    {
        $this->getEntityManager()->persist($envio);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Envio $envio, bool $flush = false): void
    {
        $this->getEntityManager()->remove($envio);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(string $uuid): ?Envio
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    /**
     * Busca envíos por cliente
     */
    public function findByCliente(int $clienteId, int $page = 1, int $limit = 20): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.cliente = :clienteId')
            ->setParameter('clienteId', $clienteId)
            ->orderBy('e.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca envíos por estado
     */
    public function findByEstado(EstadoEnvio $estado, int $limit = 50): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.estado = :estado')
            ->setParameter('estado', $estado)
            ->innerJoin('e.cliente', 'c')
            ->addSelect('c')
            ->leftJoin('e.mensajero', 'm')
            ->addSelect('m')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca envíos asignados a un mensajero
     */
    public function findByMensajero(int $mensajeroId, ?EstadoEnvio $estado = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.mensajero = :mensajeroId')
            ->setParameter('mensajeroId', $mensajeroId)
            ->innerJoin('e.cliente', 'c')
            ->addSelect('c')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($estado !== null) {
            $qb->andWhere('e.estado = :estado')
                ->setParameter('estado', $estado);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Cuenta envíos por estado
     */
    public function countByEstado(EstadoEnvio $estado): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.estado = :estado')
            ->setParameter('estado', $estado)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Busca envíos pendientes de asignación
     */
    public function findPendientesDeAsignacion(int $limit = 20): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.estado = :estado')
            ->setParameter('estado', EstadoEnvio::PAGADO)
            ->andWhere('e.mensajero IS NULL')
            ->orderBy('e.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca envíos cuya asignación ha expirado
     */
    public function findAsignacionesExpiradas(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.estado = :estado')
            ->setParameter('estado', EstadoEnvio::ASIGNADO)
            ->andWhere('e.expiraAsignacionAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca envíos planificados para una fecha específica
     */
    public function findPlanificadosParaFecha(\DateTimeInterface $fecha): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.tipoEnvio = :tipo')
            ->setParameter('tipo', TipoEnvio::PLANIFICADO)
            ->andWhere('DATE(e.fechaPlanificada) = :fecha')
            ->setParameter('fecha', $fecha->format('Y-m-d'))
            ->orderBy('e.ventanaInicio', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene estadísticas diarias de envíos
     */
    public function getEstadisticasDiarias(\DateTimeInterface $fecha): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.estado, COUNT(e.id) as total, SUM(e.precioBase) as montoTotal')
            ->where('DATE(e.createdAt) = :fecha')
            ->setParameter('fecha', $fecha->format('Y-m-d'))
            ->groupBy('e.estado');

        return $qb->getQuery()->getResult();
    }
}
