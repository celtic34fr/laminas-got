<?php

namespace LaminasGOT\Service\Factory;


use Laminas\View\Renderer\PhpRenderer;
use LaminasGOT\Service\LF3GotServices;
use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceManager;

class LF3GotServicesFactory implements FactoryInterface
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
        /** @var PhpRenderer $render */
        $render  = $container->get('ViewPhpRenderer');
        /** get Configuration array */
        $config = $container->get('Config') ;

        return new LF3GotServices($serviceManager, $render, $config);
    }
}
?>
