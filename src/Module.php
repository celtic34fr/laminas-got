<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-skeleton for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-skeleton/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-skeleton/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace LaminasGOT;

use Laminas\Mvc\MvcEvent;
use Laminas\View\Model\ViewModel;
use LaminasGOT\Service\LF3GotServices;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        $events = $e->getTarget()->getEventManager();
        $events->attach(MvcEvent::EVENT_RENDER, [$this, 'onRender'], 200);
    }

    public function onRender(MvcEvent $e)
    {
        /** @var LF3GotServices $gotService */
        $gotService = $e->getTarget()->getServiceManager()->get('graphic.object.templating.services');
        /** @var ViewModel $viewModel */
        $viewModel  = $e->getApplication()->getMvcEvent()->getViewModel();

        list($viewModel, $rscs)  = $gotService->render_objects($viewModel);
        $rscs = $gotService->render_header($rscs);
        // affectation au premier enfant de ViewModel du champs got_header
        $viewModel->setVariable('got_header', $rscs);
        $e->getApplication()->getMvcEvent()->setViewModel($viewModel);
        return $e;
    }

    public function getConfig() : array
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
