<?php

declare(strict_types=1);

namespace Tueen\Database\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;
use Tueen\Database\Security\PasswordHasherService;

final class ContainerFactory
{
    public static function createContainer(DatabaseConfig $config): ContainerBuilder
    {
        $container = new ContainerBuilder();

        // Register Config
        $container->register(DatabaseConfig::class)
            ->setSynthetic(true);

        // Register EventDispatcher
        $container->register('event_dispatcher', EventDispatcher::class)
            ->setPublic(true);
        $container->setAlias(EventDispatcherInterface::class, 'event_dispatcher');

        // Register Password Hasher
        $container->register(PasswordHasherService::class)
            ->setPublic(true);

        // Register DatabaseManager
        $container->register(DatabaseManager::class)
            ->setArguments([
                new Reference(DatabaseConfig::class),
                new Reference('event_dispatcher'),
            ])
            ->setPublic(true);

        $container->set(DatabaseConfig::class, $config);
        $container->compile();

        return $container;
    }
}
