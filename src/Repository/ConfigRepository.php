<?php

namespace App\Repository;

use App\Entity\Config;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Config>
 */
class ConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Config::class);
    }

    public function findByKey(string $key): ?Config
    {
        return $this->createQueryBuilder('c')
            ->where('c.configKey = :key')
            ->setParameter('key', $key)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getValue(string $key, ?string $default = null): ?string
    {
        $config = $this->findByKey($key);
        return $config?->getConfigValue() ?? $default;
    }
}

