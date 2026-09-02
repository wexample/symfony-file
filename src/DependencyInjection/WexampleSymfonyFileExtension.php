<?php

namespace Wexample\SymfonyFile\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyFileExtension extends AbstractWexampleSymfonyExtension
{
    public const PARAMETER_ROOTS = 'wexample_symfony_file.roots';

    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $this->loadConfig(
            __DIR__,
            $container
        );

        $config = $this->processConfiguration(
            new Configuration(),
            $configs
        );

        $container->setParameter(
            self::PARAMETER_ROOTS,
            $config['roots']
        );
    }
}
