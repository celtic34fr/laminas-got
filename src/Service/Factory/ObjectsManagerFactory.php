<?php


namespace LaminasGOT\Service\Factory;


use LaminasGOT\Service\ObjectsManager;
use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Session\Container;

/**
 * Class ObjectsManagerFactory
 * @package GraphicObjectTemplating\Service\Factory
 *
 * mzthode
 * -------
 * __invoke(ContainerInterface $container, $requestedName, array $options = null)
 */
class ObjectsManagerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string $requestedName
     * @param  null|array $options
     * @return object
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     *     creating a service.
     * @throws ContainerException if any other error occurs
     */

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var ServiceManager $serviceManager */
        $serviceManager =  $container->get('ServiceManager');
        /** get Configuration array */
        $config = $container->get('Config') ;
        /** @var Container $session */
        $session = new Container('GOT');

        return new ObjectsManager($serviceManager, $config, $session);
    }
}