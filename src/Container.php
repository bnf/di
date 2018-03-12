<?php
declare(strict_types = 1);
namespace Bnf\Di;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class Container implements ContainerInterface
{
    /**
     * @var array
     */
    private $entries = [];

    /**
     * @var array
     */
    private $factories = [];

    /**
     * Instantiate the container.
     *
     * Objects and parameters can be passed as argument to the constructor.
     *
     * @param array $providers The service providers to register.
     * @param array $entires The default parameters or objects.
     */
    public function __construct(array $providers = [], array $entries = [])
    {
        $this->entries = $entries;

        foreach ($providers as $provider) {
            // @todo sanity check if $provider implements ServiceProviderInterface?
            // @todo sanity check if factory is callable
            $factories = $provider->getFactories();
            foreach ($factories as $id => $factory) {
                $this->factories[$id] = $factory;
            }
        }

        foreach ($providers as $provider) {
            $extensions = $provider->getExtensions();
            foreach ($extensions as $id => $extension) {
                if (isset($this->factories[$id])) {
                    $previousFactory = $this->factories[$id];
                    $this->factories[$id] = function (ContainerInterface $container) use ($extension, $previousFactory) {
                        $previous = ($previousFactory)($container);
                        return ($extension)($container, $previous);
                    };
                } else {
                    // calling extension as a regular factory means the second parameter is null
                    // If the extension declares a non-nullable type  for the second parameter the
                    // call will fail – by intent.
                    $this->factories[$id] = $extension;
                }
            }
        }
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has($id)
    {
        return array_key_exists($id, $this->entries) || array_key_exists($id, $this->factories);
    }

    /**
     * @param string $id
     * @return mixed
     * @throws NotFoundException
     */
    public function get($id)
    {
        if (!$this->has($id)) {
            throw new class('Container entry "' . $id . '" is not available.', 1519978105) extends Exception implements NotFoundExceptionInterface {
            };
        }

        if (!array_key_exists($id, $this->entries)) {
            $factory = $this->factories[$id];
            if ($factory === false) {
                throw new class('Container entry "' . $id . '" is part of a cyclic dependency chain.', 1520175002) extends Exception implements ContainerExceptionInterface {
                };
            }

            // Remove factory as it is no longer required.
            // Set factory to false to be able to detect
            // cyclic dependency loops.
            $this->factories[$id] = false;

            $this->entries[$id] = ($factory)($this);
        }

        return $this->entries[$id];
    }
}
