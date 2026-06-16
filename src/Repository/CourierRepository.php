<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Courier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Courier>
 */
class CourierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Courier::class);
    }

    public function findAvailableCouriers(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.roles LIKE :role')
            ->andWhere('c.isAvailable = :available')
            ->setParameter('role', '%ROLE_COURIER%')
            ->setParameter('available', true)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByEmail(string $email): ?Courier
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.email = :val')
            ->setParameter('val', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByVehicleType(?string $vehicleType): array
    {
        if ($vehicleType === null) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->andWhere('c.vehicleType = :val')
            ->setParameter('val', $vehicleType)
            ->orderBy('c.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function updateLocation(Courier $courier, array $location): void
    {
        $courier->setCurrentLocation($location);
        $this->getEntityManager()->persist($courier);
        $this->getEntityManager()->flush();
    }
}
