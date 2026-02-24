<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\DashboardController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * @internal
 *
 * @covers \App\Controller\DashboardController
 */
final class DashboardControllerTest extends TestCase
{
    private DashboardController $controller;

    protected function setUp(): void
    {
        $this->controller = new DashboardController();

        $twig = self::createStub(Environment::class);
        $twig->method('render')->willReturn('');

        $router = self::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/activities');

        $container = new Container();
        $container->set('twig', $twig);
        $container->set('router', $router);
        $container->setParameter('kernel.charset', 'UTF-8');
        $this->controller->setContainer($container);
    }

    public function testHomeRedirectsToCalendar(): void
    {
        $response = $this->controller->home();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/activities', $response->headers->get('Location'));
    }
}
