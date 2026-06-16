<?php

declare(strict_types=1);

namespace App\Mensajero\Repository;

use App\Mensajero\Entity\Mensajero;
use App\Mensajero\Entity\EstadoAprobacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mensajero>
 */
class MensajeroRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mensajero::class);
    }

    public function save(Mensajero $mensajero, bool $flush = false): void
    {
        $this->getEntityManager()->persist($mensajero);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Mensajero $mensajero, bool $flush = false): void
    {
        $this->getEntityManager()->remove($mensajero);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUsuarioId(int $usuarioId): ?Mensajero
    {
        return $this->findOneBy(['usuario' => $usuarioId]);
    }

    /**
     * Busca mensajeros por estado de aprobación
     */
    public function findByEstadoAprobacion(EstadoAprobacion $estado, int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.estadoAprobacion = :estado')
            ->setParameter('estado', $estado)
            ->innerJoin('m.usuario', 'u')
            ->addSelect('u')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca mensajeros disponibles y aprobados
     */
    public function findDisponiblesYaprobados(int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.disponible = true')
            ->andWhere('m.estadoAprobacion = :estado')
            ->setParameter('estado', EstadoAprobacion::APROBADO)
            ->andWhere('m.bloqueadoHasta IS NULL OR m.bloqueadoHasta < :now')
            ->setParameter('now', new \DateTime())
            ->innerJoin('m.usuario', 'u')
            ->addSelect('u')
            ->orderBy('m.calificacionPromedio', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta mensajeros por estado
     */
    public function countByEstadoAprobacion(EstadoAprobacion $estado): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.estadoAprobacion = :estado')
            ->setParameter('estado', $estado)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Busca mensajeros cercanos usando fórmula Haversine en SQL nativo
     * Calcula la distancia real en kilómetros basada en lat/lng
     * 
     * LIMITACIÓN CONOCIDA: Esta consulta lee desde MySQL (ubicaciones_mensajero),
     * que recibe datos en batch cada 60s. Los resultados pueden tener hasta 60s
     * de desfase en la búsqueda inicial. Mejora futura: leer desde Redis en tiempo real.
     */
    public function findCercanos(float $lat, float $lng, float $radioKm, int $limit = 20): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        // Fórmula Haversine en SQL nativo para cálculo preciso de distancia
        $sql = "
            SELECT 
                m.id,
                m.usuario_id,
                m.disponible,
                m.estado_aprobacion,
                m.bloqueado_hasta,
                m.calificacion_promedio,
                m.tipo_vehiculo,
                (6371 * ACOS(
                    COS(RADIANS(:lat)) * COS(RADIANS(um.lat)) * COS(RADIANS(um.lng) - RADIANS(:lng)) +
                    SIN(RADIANS(:lat)) * SIN(RADIANS(um.lat))
                )) AS distance
            FROM mensajeros m
            INNER JOIN usuarios u ON m.usuario_id = u.id
            LEFT JOIN (
                SELECT mensajero_id, lat, lng, registrado_at
                FROM ubicaciones_mensajero
                WHERE (mensajero_id, registrado_at) IN (
                    SELECT mensajero_id, MAX(registrado_at)
                    FROM ubicaciones_mensajero
                    GROUP BY mensajero_id
                )
            ) um ON m.id = um.mensajero_id
            WHERE m.disponible = 1
            AND m.estado_aprobacion = 'aprobado'
            AND (m.bloqueado_hasta IS NULL OR m.bloqueado_hasta < NOW())
            AND um.lat IS NOT NULL
            AND um.lng IS NOT NULL
            HAVING distance < :radio
            ORDER BY distance ASC
            LIMIT :limit
        ";

        $stmt = $conn->executeQuery($sql, [
            'lat' => $lat,
            'lng' => $lng,
            'radio' => $radioKm,
            'limit' => $limit,
        ]);

        $results = $stmt->fetchAllAssociative();
        
        // Convertir resultados a entidades Mensajero
        $mensajeros = [];
        $mensajeroIds = array_column($results, 'id');
        
        if (!empty($mensajeroIds)) {
            $mensajeros = $this->findBy(['id' => $mensajeroIds]);
            
            // Añadir distancia calculada como propiedad temporal (si fuera necesario)
            foreach ($mensajeros as $mensajero) {
                $distance = null;
                foreach ($results as $result) {
                    if ($result['id'] === $mensajero->getId()) {
                        $distance = (float) $result['distance'];
                        break;
                    }
                }
                // Podrías añadir un método setDistanceTemporal() si lo necesitas
            }
        }
        
        return $mensajeros;
    }

    /**
     * Incrementa el contador de rechazos consecutivos
     */
    public function incrementarRechazos(Mensajero $mensajero): void
    {
        $mensajero->setRechazosConsecutivos($mensajero->getRechazosConsecutivos() + 1);
        $this->save($mensajero, true);
    }

    /**
     * Resetea los rechazos consecutivos tras una aceptación
     */
    public function resetearRechazos(Mensajero $mensajero): void
    {
        $mensajero->setRechazosConsecutivos(0);
        $this->save($mensajero, true);
    }

    /**
     * Actualiza la calificación promedio
     */
    public function actualizarCalificacionPromedio(Mensajero $mensajero): void
    {
        // Este método debería ser llamado desde un servicio que calcule el promedio
        // La lógica real está en el servicio de calificaciones
        $this->save($mensajero, true);
    }

    /**
     * Incrementa el total de envíos completados
     */
    public function incrementarTotalEnvios(Mensajero $mensajero): void
    {
        $mensajero->setTotalEnvios($mensajero->getTotalEnvios() + 1);
        $this->save($mensajero, true);
    }
}
