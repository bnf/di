<?php
declare(strict_types = 1);
namespace Bnf\Di\Tests;

use Bnf\Di\Container;
use Interop\Container\ServiceProviderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use stdClass as Service;

/**
 * Testcase for the Container class
 */
class ContainerTest extends TestCase
{
    /**
     * @var ObjectProphecy<ServiceProviderInterface>
     */
    protected $providerProphecy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->providerProphecy = $this->createServiceProviderProphecy();
    }

    /**
     * @return ObjectProphecy<ServiceProviderInterface>
     */
    protected function createServiceProviderProphecy(array $extensions = [], array $factories = []): ObjectProphecy
    {
        $prophecy = $this->prophesize();
        $prophecy->willImplement(ServiceProviderInterface::class);
        $prophecy->getFactories()->willReturn($extensions);
        $prophecy->getExtensions()->willReturn($factories);
        return $prophecy;
    }

    public function testImplementsInterface(): void
    {
        // Cast to object for phpstan to not complain that container is always an instance of ContainerInterface
        /** @var object $container */
        $container = new Container;
        self::assertInstanceOf(ContainerInterface::class, $container);
    }

    public function testWithString(): void
    {
        $this->providerProphecy->getFactories()->willReturn([
            'param' => function () {
                return 'value';
            }
        ]);
        $container = new Container([$this->providerProphecy->reveal()]);

        self::assertTrue($container->has('param'));
        self::assertEquals('value', $container->get('param'));
    }

    /**
     * @dataProvider objectFactories
     */
    public function testGet(callable $factory): void
    {
        $this->providerProphecy->getFactories()->willReturn([
            'service' => $factory,
        ]);
        $container = new Container([$this->providerProphecy->reveal()]);

        self::assertTrue($container->has('service'));
        self::assertInstanceOf(Service::class, $container->get('service'));
    }

    public function testNullContainer(): void
    {
        $container = new Container;
        self::assertFalse($container->has('foo'));
    }

    public function testNullContainerWithDefaultEntries(): void
    {
        $container = new Container([], ['foo' => 'bar']);
        self::assertTrue($container->has('foo'));
    }

    /**
     * @dataProvider objectFactories
     */
    public function testMultipleGetServicesShouldBeEqual(callable $factory): void
    {
        $this->providerProphecy->getFactories()->willReturn([ 'service' => $factory ]);
        // A factory can also be used as extension, as it's based on the same signature
        $this->providerProphecy->getExtensions()->willReturn([ 'extension' => $factory ]);

        $container = new Container([$this->providerProphecy->reveal()]);

        $serviceOne = $container->get('service');
        $serviceTwo = $container->get('service');

        $extensionOne = $container->get('extension');
        $extensionTwo = $container->get('extension');

        self::assertSame($serviceOne, $serviceTwo);
        self::assertSame($extensionOne, $extensionTwo);
    }

    public function testPassesContainerAsParameter(): void
    {
        $this->providerProphecy->getFactories()->willReturn([
            'service' => function () {
                return new Service();
            },
            'container' => function (ContainerInterface $container) {
                return $container;
            }
        ]);
        $container = new Container([$this->providerProphecy->reveal()]);

        self::assertNotSame($container, $container->get('service'));
        self::assertSame($container, $container->get('container'));
    }

    public function testNullValueEntry(): void
    {
        $this->providerProphecy->getFactories()->willReturn([
            'null' => function () {
                return null;
            }
        ]);
        $container = new Container([$this->providerProphecy->reveal()]);

        self::assertTrue($container->has('null'));
        self::assertNull($container->get('null'));
    }

    public function testNullValueEntryCallsFactoryOnlyOnce(): void
    {
        $calledCount = 0;
        $factory = function () use (&$calledCount) {
            $calledCount++;
            return null;
        };
        $this->providerProphecy->getFactories()->willReturn([
            'null' => $factory,
        ]);
        $container = new Container([$this->providerProphecy->reveal()]);

        self::assertTrue($container->has('null'));
        self::assertNull($container->get('null'));
        self::assertTrue($container->has('null'));
        self::assertNull($container->get('null'));
        self::assertEquals($calledCount, 1);
    }

    public function testHas(): void
    {
        $this->providerProphecy->getFactories()->willReturn([
            'service' => function () {
                return new Service();
            },
            'param' => function () {
                return 'value';
            },
            'int' => function () {
                return 2;
            },
            'bool' => function () {
                return false;
            },
            'null' => function () {
                return null;
            },
            '0' => function () {
                return 0;
            }
        ]);
        $container = new Container([$this->providerProphecy->reveal()]);

        self::assertTrue($container->has('param'));
        self::assertTrue($container->has('service'));
        self::assertTrue($container->has('int'));
        self::assertTrue($container->has('bool'));
        self::assertTrue($container->has('null'));
        self::assertFalse($container->has('non_existent'));
    }

    public function testDefaultEntry(): void
    {
        $default = ['param' => 'value'];
        $container = new Container([], $default);

        self::assertSame('value', $container->get('param'));
    }

    public function testGetValidatesKeyIsPresent(): void
    {
        $container = new Container();

        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('Container entry "foo" is not available.');
        $container->get('foo');
    }

    /**
     * @dataProvider objectFactories
     */
    public function testExtension(callable $factory): void
    {
        $providerA = $this->providerProphecy;
        $providerA->getFactories()->willReturn(['service' => $factory]);

        $providerB = $this->createServiceProviderProphecy();
        $providerB->getExtensions()->willReturn([
            'service' => function (ContainerInterface $c, Service $s) {
                $s->value = 'value';
                return $s;
            },
        ]);
        $iterator = (function () use ($providerA, $providerB): iterable {
            yield $providerA->reveal();
            yield $providerB->reveal();
        })();
        $container = new Container($iterator);

        /** @var object{value: string} $service */
        $service = $container->get('service');

        self::assertSame('value', $service->value);
    }

    /**
     * @dataProvider objectFactories
     */
    public function testExtendingLaterProvider(callable $factory): void
    {
        $providerA = $this->providerProphecy;
        $providerA->getFactories()->willReturn(['service' => $factory]);

        $providerB = $this->createServiceProviderProphecy();
        $providerB->getExtensions()->willReturn([
            'service' => function (ContainerInterface $c, Service $s) {
                $s->value = 'value';
                return $s;
            },
        ]);
        $container = new Container([$providerB->reveal(), $providerA->reveal()]);

        /** @var object{value: string} $service */
        $service = $container->get('service');

        self::assertSame('value', $service->value);
    }

    /**
     * @dataProvider objectFactories
     */
    public function testExtendingOwnFactory(callable $factory): void
    {
        $this->providerProphecy->getFactories()->willReturn(['service' => $factory]);
        $this->providerProphecy->getExtensions()->willReturn(
            [
                'service' => function (ContainerInterface $c, Service $s) {
                    $s->value = 'value';
                    return $s;
                },
            ]
        );
        $container = new Container([$this->providerProphecy->reveal()]);

        /** @var object{value: string} $service */
        $service = $container->get('service');

        self::assertSame('value', $service->value);
    }

    public function testExtendingNonExistingFactory(): void
    {
        $this->providerProphecy->getExtensions()->willReturn([
            'service' => function (ContainerInterface $c, Service $s = null) {
                if ($s === null) {
                    $s = new Service();
                }
                $s->value = 'value';
                return $s;
            },
        ]);
        $container = new Container([$this->providerProphecy->reveal()]);

        /** @var object{value: string} $service */
        $service = $container->get('service');

        self::assertSame('value', $service->value);
    }

    /**
     * @dataProvider objectFactories
     */
    public function testMultipleExtensions(callable $factory): void
    {
        $providerA = $this->providerProphecy;
        $providerA->getFactories()->willReturn(['service' => $factory]);

        $providerB = $this->createServiceProviderProphecy();
        $providerB->getExtensions()->willReturn([
            'service' => function (ContainerInterface $c, Service $s) {
                $s->value = '1';
                return $s;
            },
        ]);

        $providerC = $this->createServiceProviderProphecy();
        $providerC->getExtensions()->willReturn([
            'service' => function (ContainerInterface $c, Service $s) {
                $s->value .= '2';
                return $s;
            },
        ]);
        $container = new Container([$providerA->reveal(), $providerB->reveal(), $providerC->reveal()]);

        /** @var object{value: string} $service */
        $service = $container->get('service');

        self::assertSame('12', $service->value);
    }

    /**
     * @dataProvider objectFactories
     */
    public function testEntryOverriding(callable $factory): void
    {
        $providerA = $this->providerProphecy;
        $providerA->getFactories()->willReturn(['service' => $factory]);

        $providerB = $this->createServiceProviderProphecy();
        $providerB->getFactories()->willReturn(['service' => function () {
            return 'value';
        }]);

        $container = new Container([$providerA->reveal(), $providerB->reveal()]);

        self::assertNotInstanceOf(Service::class, $container->get('service'));
        self::assertEquals('value', $container->get('service'));
    }

    public function testDelegateContainer(): void
    {
        $this->providerProphecy = $this->createServiceProviderProphecy();
        $this->providerProphecy->getFactories()->willReturn([
            'service2' => function (ContainerInterface $c) {
                $s = new \stdClass;
                $s->dependency = $c->get('service');
                return $s;
            },
        ]);

        $rootContainer = new Container([], ['service' => new Service()]);
        $container = new Container([$this->providerProphecy->reveal()], [], $rootContainer);

        self::assertFalse($container->has('service'));

        /** @var object{dependency: mixed} $service2 */
        $service2 = $container->get('service2');

        self::assertInstanceOf(Service::class, $service2->dependency);
    }

    public function testDelegateContainerExceptionIfNotInOwnContainer(): void
    {
        $rootContainer = new Container([], ['service' => new Service()]);
        $container = new Container([], [], $rootContainer);
        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('Container entry "service" is not available.');
        $container->get('service');
    }

    public function testCyclicDependency(): void
    {
        $this->providerProphecy->getFactories()->willReturn([
            'A' => function (ContainerInterface $container) {
                return $container->get('B');
            },
            'B' => function (ContainerInterface $container) {
                return $container->get('A');
            },
        ]);

        $container = new Container([$this->providerProphecy->reveal()]);

        $this->expectException(ContainerExceptionInterface::class);
        $this->expectExceptionMessage('Container entry "A" is part of a cyclic dependency chain.');
        $container->get('A');
    }

    public function testCyclicDependencyRetrievedTwice(): void
    {
        $this->providerProphecy->getFactories()->willReturn([
            'A' => function (ContainerInterface $container) {
                return $container->get('B');
            },
            'B' => function (ContainerInterface $container) {
                return $container->get('A');
            },
        ]);

        $container = new Container([$this->providerProphecy->reveal()]);

        $this->expectException(ContainerExceptionInterface::class);
        $this->expectExceptionMessage('Container entry "A" is part of a cyclic dependency chain.');
        try {
            $container->get('A');
        } catch (ContainerExceptionInterface $e) {
        }
        self::assertTrue($container->has('A'));
        $container->get('A');
    }

    public static function factory(): Service
    {
        return new Service();
    }

    /**
     * Provider for ServerProvider callables.
     * Either a closure, a static callable or invokable.
     */
    public function objectFactories(): array
    {
        return [
            [
                // Static callback
                [ self::class, 'factory']
            ],
            [
                // Closure
                function () {
                    return new Service();
                }
            ],
            [
                // Invokable
                new class {
                    public function __invoke(): Service
                    {
                        return new Service();
                    }
                }
            ],
            [
                // Non static factory
                [
                    new class {
                        public function factory(): Service
                        {
                            return new Service();
                        }
                    },
                    'factory'
                ]
            ],
        ];
    }

    // @todo: test leaking factories?
    // @todo: passing non ServiceProvider as parameter
}
