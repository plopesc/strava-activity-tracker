<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Activity;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * Shared helpers for controller unit tests.
 *
 * The using class must declare a typed property:
 *   private SomeController $controller;
 */
trait ControllerTestTrait
{
    /**
     * Configures a Twig stub to capture template parameters.
     *
     * Replaces the container on $this->controller with a fresh one
     * containing a Twig stub that records all render() parameters.
     *
     * @return \ArrayObject<string, mixed> Captured parameters (populated after render)
     */
    private function captureTwigParams(string $expectedTemplate): \ArrayObject
    {
        /** @var \ArrayObject<string, mixed> $captured */
        $captured = new \ArrayObject();

        $twig = self::createStub(Environment::class);
        $twig->method('render')
            ->willReturnCallback(static function (string $template, array $params) use ($captured, $expectedTemplate): string {
                self::assertSame($expectedTemplate, $template);
                foreach ($params as $key => $value) {
                    $captured[$key] = $value;
                }

                return '';
            });

        $router = self::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/activities');

        $container = new Container();
        $container->set('twig', $twig);
        $container->set('router', $router);
        $container->setParameter('kernel.charset', 'UTF-8');

        $this->controller->setContainer($container);

        return $captured;
    }

    /**
     * Creates an Activity entity with sensible defaults for testing.
     *
     * @param null|array<string, mixed> $rawStreams
     */
    private function makeActivity(
        ?float $distance = 5000.0,
        ?float $averageSpeed = 3.5,
        ?\DateTimeImmutable $date = null,
        ?array $rawStreams = null,
    ): Activity {
        $activity = new Activity();
        $activity->setStravaId((string) random_int(1, 999999));
        $activity->setName('Test Activity');
        $activity->setDistance($distance ?? 5000.0);
        $activity->setElapsedTime(1800);
        $activity->setAverageSpeed($averageSpeed ?? 3.5);
        $activity->setActivityDate($date ?? new \DateTimeImmutable('2024-06-15'));
        $activity->setSyncedAt(new \DateTimeImmutable());
        $activity->setRawStreams($rawStreams);

        return $activity;
    }
}
