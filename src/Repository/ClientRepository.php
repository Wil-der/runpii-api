<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function findActiveClients(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.roles LIKE :role')
            ->setParameter('role', '%ROLE_CLIENT%')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByEmail(string $email): ?Client
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.email = :val')
            ->setParameter('val', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCompany(?string $companyName): array
    {
        if ($companyName === null) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->andWhere('c.companyName LIKE :val')
            ->setParameter('val', '%' . $companyName . '%')
            ->orderBy('c.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
