<?php

namespace Elbformat\IbexaBehatBundle\DependencyInjection;

use Elbformat\IbexaBehatBundle\Context\TagContentContext;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class ElbformatIbexaBehatExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        // Load only, when netgen bundle is installed
        if (class_exists('Netgen\\TagsBundle\\NetgenTagsBundle')) {
            $context = new Definition(TagContentContext::class);
            $context->setAutoconfigured(true);
            $context->setAutowired(true);
            $container->setDefinition(TagContentContext::class, $context);
        }
    }
}
