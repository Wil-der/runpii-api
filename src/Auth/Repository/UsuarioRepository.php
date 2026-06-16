<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Usuario>
 */
class UsuarioRepository extends ServiceEntityRepository implements \Symfony\Component\Security\Core\User\UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Usuario::class);
    }

    /**
     * Used to load (or refresh) the user by its username (email).
     */
    public function loadUserByIdentifier(string $identifier): ?Usuario
    {
        $queryBuilder = $this->createQueryBuilder('u');
        $queryBuilder
            ->where('u.email = :identifier')
            ->setParameter('identifier', $identifier)
            ->andWhere('u.activo = true');

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * @deprecated since Symfony 5.3, use loadUserByIdentifier() instead
     */
    public function loadUserByUsername(string $username): ?Usuario
    {
        return $this->loadUserByIdentifier($username);
    }

    public function save(Usuario $usuario, bool $flush = false): void
    {
        $this->getEntityManager()->persist($usuario);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Usuario $usuario, bool $flush = false): void
    {
        $this->getEntityManager()->remove($usuario);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(string $uuid): ?Usuario
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findByEmail(string $email): ?Usuario
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Busca usuarios por tipo (rol)
     */
    public function findByTipo(\App\Auth\Entity\TipoUsuario $tipo, int $limit = 50): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.tipo = :tipo')
            ->andWhere('u.activo = true')
            ->setParameter('tipo', $tipo)
            ->setMaxResults($limit)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta usuarios activos por tipo
     */
    public function countByTipo(\App\Auth\Entity\TipoUsuario $tipo): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.tipo = :tipo')
            ->andWhere('u.activo = true')
            ->setParameter('tipo', $tipo)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Busca usuarios con filtro de texto (nombre o email)
     */
    public function search(string $query, ?\App\Auth\Entity\TipoUsuario $tipo = null, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.nombre LIKE :query OR u.email LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->andWhere('u.activo = true')
            ->setMaxResults($limit)
            ->orderBy('u.nombre', 'ASC');

        if ($tipo !== null) {
            $qb->andWhere('u.tipo = :tipo')
                ->setParameter('tipo', $tipo);
        }

        return $qb->getQuery()->getResult();
    }
}
