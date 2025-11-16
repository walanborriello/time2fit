<?php

namespace App\Service;

use App\Entity\Config;
use App\Repository\ConfigRepository;
use Doctrine\ORM\EntityManagerInterface;

class SettingsService
{
    public function __construct(
        private ConfigRepository $configRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->configRepository->getValue($key, $default);
    }

    public function set(string $key, ?string $value, ?string $description = null): void
    {
        $config = $this->configRepository->findByKey($key);

        if (!$config) {
            $config = new Config();
            $config->setConfigKey($key);
            $this->entityManager->persist($config);
        }

        $config->setConfigValue($value);
        if ($description !== null) {
            $config->setDescription($description);
        }

        $this->entityManager->flush();
    }

    public function getAll(): array
    {
        $configs = $this->configRepository->findAll();
        $result = [];

        foreach ($configs as $config) {
            $result[$config->getConfigKey()] = [
                'value' => $config->getConfigValue(),
                'description' => $config->getDescription(),
            ];
        }

        return $result;
    }
}

