<?php
declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\ServiceContainer;

use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class IbexaExtension implements Extension
{
    public function getConfigKey()
    {
        return 'ef_ibexa';
    }

    public function initialize(ExtensionManager $extensionManager)
    {

    }

    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('resetId')->defaultValue(1000)->end()
                ->end()
            ->end()
        ;
    }

    public function load(ContainerBuilder $container, array $config)
    {
        $container->setParameter('ef_ibexa.reset_id',$config['resetId']);
    }

    public function process(ContainerBuilder $container)
    {
        // TODO: Implement process() method.
    }
}