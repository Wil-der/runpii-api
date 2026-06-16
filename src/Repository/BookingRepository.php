<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function findByClient(Client $client): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.client = :client')
            ->setParameter('client', $client)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByCourier(Courier $courier): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.courier = :courier')
            ->setParameter('courier', $courier)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingBookings(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveBookings(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.status IN (:statuses)')
            ->setParameter('statuses', ['accepted', 'in_progress'])
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findNearbyPendingBookings(float $lat, float $lng, float $radiusKm = 5.0): array
    {
        // Nota: Esta es una implementación básica. Para producción, usar extensiones espaciales de MySQL/MariaDB
        return $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->setParameter('status', 'pending')
            ->andWhere('b.pickupLatitude IS NOT NULL')
            ->andWhere('b.pickupLongitude IS NOT NULL')
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
        // El filtrado por distancia real se debería hacer en la BD con funciones espaciales
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
