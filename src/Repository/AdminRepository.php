<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Admin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Admin>
 */
class AdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Admin::class);
    }

    public function findActiveAdmins(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByEmail(string $email): ?Admin
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.email = :val')
            ->setParameter('val', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findSuperAdmins(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.isSuperAdmin = :superAdmin')
            ->setParameter('superAdmin', true)
            ->orderBy('a.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByDepartment(?string $department): array
    {
        if ($department === null) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.department = :val')
            ->setParameter('val', $department)
            ->orderBy('a.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
