<?php

declare(strict_types=1);

namespace App\Pago\Repository;

use App\Pago\Entity\Favorito;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorito>
 */
class FavoritoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorito::class);
    }

    public function save(Favorito $favorito, bool $flush = false): void
    {
        $this->getEntityManager()->persist($favorito);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Favorito $favorito, bool $flush = false): void
    {
        $this->getEntityManager()->remove($favorito);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Busca favoritos por cliente
     */
    public function findByCliente(int $clienteId): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.cliente = :clienteId')
            ->setParameter('clienteId', $clienteId)
            ->innerJoin('f.mensajero', 'm')
            ->addSelect('m')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca favoritos por mensajero
     */
    public function findByMensajero(int $mensajeroId): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.mensajero = :mensajeroId')
            ->setParameter('mensajeroId', $mensajeroId)
            ->innerJoin('f.cliente', 'c')
            ->addSelect('c')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Verifica si un cliente tiene como favorito a un mensajero
     */
    public function existeFavorito(int $clienteId, int $mensajeroId): bool
    {
        return (bool) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.cliente = :clienteId')
            ->andWhere('f.mensajero = :mensajeroId')
            ->setParameter('clienteId', $clienteId)
            ->setParameter('mensajeroId', $mensajeroId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Encuentra un favorito específico
     */
    public function findOneByClienteYMensajero(int $clienteId, int $mensajeroId): ?Favorito
    {
        return $this->findOneBy([
            'cliente' => $clienteId,
            'mensajero' => $mensajeroId,
        ]);
    }

    /**
     * Cuenta favoritos de un cliente
     */
    public function countPorCliente(int $clienteId): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.cliente = :clienteId')
            ->setParameter('clienteId', $clienteId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
