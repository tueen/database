<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;
use Tueen\Database\Security\PasswordHasherService;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(PasswordHasherService::class)
        ->public();

    $services->set(DatabaseManager::class)
        ->args([
            service(DatabaseConfig::class),
            service(EventDispatcherInterface::class)->nullOnInvalid(),
        ])
        ->public();
};
