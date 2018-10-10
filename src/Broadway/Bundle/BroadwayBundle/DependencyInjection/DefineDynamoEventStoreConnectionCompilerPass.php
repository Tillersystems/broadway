<?php

namespace Broadway\Bundle\BroadwayBundle\DependencyInjection;

use Aws\AwsClient;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Compiler pass to define the dynamo event store connection according to the configuration.
 */
class DefineDynamoEventStoreConnectionCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('broadway.event_store.dynamo.connection')) {
            return;
        }

        $config = $container->getParameter('broadway.event_store.dynamo.connection');

        $container
            ->getDefinition('aws_sdk')
            ->replaceArgument(0, $config + ['ua_append' => [
                'Symfony/'.Kernel::VERSION,
            ]]);

        $serviceDefinition = $this->createServiceDefinition('DynamoDb');
        $container->setDefinition('broadway.event_store.dynamo.connection', $serviceDefinition);
    }

    private function createServiceDefinition($name)
    {
        $clientClass = "Aws\\{$name}\\{$name}Client";
        $serviceDefinition = new Definition(
            class_exists($clientClass) ? $clientClass : AwsClient::class
        );

        $serviceDefinition->setFactory([
            new Reference('aws_sdk'),
            'create'.$name,
        ]);

        return $serviceDefinition;
    }
}
