<?php

namespace LaminasGOT;

use LaminasGOT\Service\Factory\LF3GotServicesFactory;
use LaminasGOT\Service\Factory\ObjectsManagerFactory;
use LaminasGOT\Service\LF3GotServices;
use LaminasGOT\Service\ObjectsManager;
use LaminasGOT\Controller\Factory\MainControllerFactory;
use LaminasGOT\Controller\MainController;
//use Laminas\Router\Http\Literal;

return [
    'router' => [
        'routes' => [
            'gotDispatch' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/goDispatch',
                    'defaults' => [
                        'controller' => MainController::class,
                        'action'        => 'gotDispatch',
                    ],
                ],
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            MainController::class => MainControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            LF3GotServices::class => LF3GotServicesFactory::class,
            ObjectsManager::class => ObjectsManagerFactory::class
        ],
        'aliases' => [
            'graphic.object.templating.services' => LF3GotServices::class,
            'objects.manager' => ObjectsManager::class
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
];