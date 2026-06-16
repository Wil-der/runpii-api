<?php

declare(strict_types=1);

namespace App\Admin\Repository;

use App\Admin\Entity\Configuracion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Configuracion>
 */
class ConfiguracionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Configuracion::class);
    }

    public function save(Configuracion $configuracion, bool $flush = false): void
    {
        $this->getEntityManager()->persist($configuracion);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Configuracion $configuracion, bool $flush = false): void
    {
        $this->getEntityManager()->remove($configuracion);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Obtiene una configuración por su clave
     */
    public function findOneByClave(string $clave): ?Configuracion
    {
        return $this->findOneBy(['clave' => $clave]);
    }

    /**
     * Obtiene el valor de una configuración por su clave
     */
    public function getValorByClave(string $clave): ?string
    {
        $config = $this->findOneByClave($clave);
        return $config?->getValor();
    }

    /**
     * Actualiza o crea una configuración
     */
    public function setValor(string $clave, string $valor, ?string $descripcion = null): Configuracion
    {
        $config = $this->findOneByClave($clave);
        
        if ($config === null) {
            $config = new Configuracion();
            $config->setClave($clave);
        }
        
        $config->setValor($valor);
        
        if ($descripcion !== null) {
            $config->setDescripcion($descripcion);
        }
        
        $this->save($config, true);
        
        return $config;
    }

    /**
     * Obtiene todas las configuraciones
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.clave', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene configuraciones como array clave-valor
     */
    public function findAllAsKeyValue(): array
    {
        $configs = $this->findAllOrdered();
        $result = [];
        
        foreach ($configs as $config) {
            $result[$config->getClave()] = $config->getValor();
        }
        
        return $result;
    }

    /**
     * Busca configuraciones por descripción (búsqueda parcial)
     */
    public function searchByDescripcion(string $query): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.descripcion LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.clave', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
