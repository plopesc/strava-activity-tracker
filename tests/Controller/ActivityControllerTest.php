<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ActivityController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Twig\Environment;

/**
 * @internal
 *
 * @covers \App\Controller\ActivityController
 */
final class ActivityControllerTest extends TestCase
{
    use ControllerTestTrait;

    private ActivityController $controller;

    protected function setUp(): void
    {
        $this->controller = new ActivityController();

        $twig = self::createStub(Environment::class);
        $twig->method('render')->willReturn('');

        $container = new Container();
        $container->set('twig', $twig);
        $container->setParameter('kernel.charset', 'UTF-8');
        $this->controller->setContainer($container);
    }

    // =========================================================================
    // detail() — buildMapData
    // =========================================================================

    public function testDetailMapDataExtractsLatLng(): void
    {
        $latlng = [[40.0, -74.0], [40.1, -74.1]];
        $activity = $this->makeActivity(rawStreams: ['latlng' => ['data' => $latlng]]);

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        self::assertSame($latlng, $capturedParams['mapData']);
    }

    public function testDetailMapDataNullWhenNoStreams(): void
    {
        $activity = $this->makeActivity(rawStreams: null);

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        self::assertNull($capturedParams['mapData']);
    }

    public function testDetailMapDataNullWhenEmptyLatLng(): void
    {
        $activity = $this->makeActivity(rawStreams: ['latlng' => ['data' => []]]);

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        self::assertNull($capturedParams['mapData']);
    }

    public function testDetailMapDataNullWhenLatLngKeyMissing(): void
    {
        $activity = $this->makeActivity(rawStreams: ['velocity_smooth' => ['data' => [3.5]]]);

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        self::assertNull($capturedParams['mapData']);
    }

    // =========================================================================
    // detail() — buildChartData
    // =========================================================================

    public function testDetailChartDataWithVelocityAndDistance(): void
    {
        $activity = $this->makeActivity(rawStreams: [
            'velocity_smooth' => ['data' => [3.0, 4.0, 5.0]],
            'distance' => ['data' => [0, 500, 1000]],
        ]);

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        $chartData = $capturedParams['chartData'];
        self::assertNotNull($chartData);
        self::assertSame([0.0, 0.5, 1.0], $chartData['distance']);
        self::assertCount(3, $chartData['pace']);
    }

    public function testDetailChartDataNullWhenNoStreams(): void
    {
        $activity = $this->makeActivity(rawStreams: null);

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        self::assertNull($capturedParams['chartData']);
    }

    public function testDetailChartDataNullWhenEmptyVelocity(): void
    {
        $activity = $this->makeActivity(rawStreams: ['velocity_smooth' => ['data' => []]]);

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        self::assertNull($capturedParams['chartData']);
    }

    public function testDetailChartDataLinearDistanceFallback(): void
    {
        // 3 velocity data points, total distance 3000m, no distance stream
        $activity = $this->makeActivity(
            distance: 3000.0,
            rawStreams: ['velocity_smooth' => ['data' => [3.0, 3.5, 4.0]]],
        );

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        $chartData = $capturedParams['chartData'];
        self::assertNotNull($chartData);
        // 3 points: 0, 1.5, 3.0 km (linear interpolation)
        self::assertSame(0.0, $chartData['distance'][0]);
        self::assertSame(1.5, $chartData['distance'][1]);
        self::assertSame(3.0, $chartData['distance'][2]);
    }

    public function testDetailChartDataPaceNullWhenStopped(): void
    {
        $activity = $this->makeActivity(rawStreams: [
            'velocity_smooth' => ['data' => [0.3, 4.0]],
            'distance' => ['data' => [0, 1000]],
        ]);

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        $chartData = $capturedParams['chartData'];
        self::assertNull($chartData['pace'][0]); // 0.3 m/s < 0.5 threshold
        self::assertNotNull($chartData['pace'][1]); // 4.0 m/s is fine
    }

    public function testDetailChartDataPaceCalculation(): void
    {
        // 4.0 m/s → 1000/4.0 = 250 sec/km → 250/60 ≈ 4.167 min/km
        $activity = $this->makeActivity(rawStreams: [
            'velocity_smooth' => ['data' => [4.0]],
            'distance' => ['data' => [0]],
        ]);

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        $chartData = $capturedParams['chartData'];
        self::assertEqualsWithDelta(4.167, $chartData['pace'][0], 0.001);
    }

    public function testDetailChartDataIncludesHeartrate(): void
    {
        $activity = $this->makeActivity(rawStreams: [
            'velocity_smooth' => ['data' => [3.5]],
            'distance' => ['data' => [0]],
            'heartrate' => ['data' => [145]],
        ]);

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        self::assertSame([145], $capturedParams['chartData']['heartrate']);
    }

    public function testDetailChartDataHeartrateNullWhenMissing(): void
    {
        $activity = $this->makeActivity(rawStreams: [
            'velocity_smooth' => ['data' => [3.5]],
            'distance' => ['data' => [0]],
        ]);

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        self::assertNull($capturedParams['chartData']['heartrate']);
    }

    public function testDetailPassesActivityToTemplate(): void
    {
        $activity = $this->makeActivity();

        $capturedParams = $this->captureTwigParams('activity/detail.html.twig');
        $this->controller->detail($activity);

        self::assertSame($activity, $capturedParams['activity']);
    }
}
