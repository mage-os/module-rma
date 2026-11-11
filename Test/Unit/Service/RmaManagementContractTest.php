<?php

declare(strict_types=1);

namespace MageOS\RMA\Test\Unit\Service;

use MageOS\RMA\Api\RmaManagementInterface;
use MageOS\RMA\Service\RmaSubmitService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * RmaManagementInterface is the public entry point for creating an RMA; RmaSubmitService implements it.
 */
class RmaManagementContractTest extends TestCase
{
    public function testSubmitServiceImplementsTheContract(): void
    {
        $this->assertTrue(is_subclass_of(RmaSubmitService::class, RmaManagementInterface::class));
    }

    public function testContractIsBoundToTheSubmitServiceInDi(): void
    {
        $config = simplexml_load_file(dirname(__DIR__, 3) . '/etc/di.xml');
        $this->assertNotFalse($config);

        $types = [];
        foreach ($config->preference as $preference) {
            $types[(string)$preference['for']] = (string)$preference['type'];
        }

        $this->assertSame(RmaSubmitService::class, $types[RmaManagementInterface::class] ?? null);
    }

    public function testContractAndServiceDeclareTheSameSignature(): void
    {
        $contract = new ReflectionMethod(RmaManagementInterface::class, 'createRma');
        $service = new ReflectionMethod(RmaSubmitService::class, 'createRma');

        $describe = static fn (ReflectionMethod $method): array => array_map(
            static fn ($parameter): string => $parameter->getType() . ' $' . $parameter->getName()
                . ($parameter->isOptional() ? ' = ' . var_export($parameter->getDefaultValue(), true) : ''),
            $method->getParameters()
        );

        $this->assertSame($describe($contract), $describe($service));
        $this->assertSame((string)$contract->getReturnType(), (string)$service->getReturnType());
    }
}
