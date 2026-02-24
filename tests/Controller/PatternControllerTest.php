<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\PatternController;
use App\Repository\ActivityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * @internal
 *
 * @covers \App\Controller\PatternController
 */
final class PatternControllerTest extends TestCase
{
    use ControllerTestTrait;

    private PatternController $controller;

    protected function setUp(): void
    {
        $this->controller = new PatternController();

        $twig = self::createStub(Environment::class);
        $twig->method('render')->willReturn('');

        $container = new Container();
        $container->set('twig', $twig);
        $container->setParameter('kernel.charset', 'UTF-8');
        $this->controller->setContainer($container);
    }

    // =========================================================================
    // patternList()
    // =========================================================================

    public function testPatternListPassesGroupsToTemplate(): void
    {
        $groups = [
            ['signature' => 'easy 5km', 'count' => 10, 'activities' => []],
            ['signature' => '3×1km', 'count' => 5, 'activities' => []],
        ];

        $repo = $this->createMock(ActivityRepository::class);
        $repo->expects(self::once())
            ->method('findPatternGroupsWithRecentActivities')
            ->willReturn($groups);

        $capturedParams = $this->captureTwigParams('pattern/list.html.twig');
        $this->controller->patternList($repo);

        self::assertSame($groups, $capturedParams['groups']);
    }

    // =========================================================================
    // patternDetail()
    // =========================================================================

    public function testPatternDetailDefaultPagination(): void
    {
        $repo = $this->createMock(ActivityRepository::class);
        $repo->expects(self::once())
            ->method('findByPatternSignaturePaginated')
            ->with('easy 5km', 1, 25, 'date', 'DESC')
            ->willReturn(['activities' => [], 'total' => 0]);
        $repo->method('findByPatternSignature')->willReturn([]);

        $capturedParams = $this->captureTwigParams('pattern/detail.html.twig');

        $request = new Request();
        $this->controller->patternDetail('easy 5km', $request, $repo);

        self::assertSame(1, $capturedParams['page']);
        self::assertSame('date', $capturedParams['sort']);
        self::assertSame('DESC', $capturedParams['direction']);
        self::assertSame('easy 5km', $capturedParams['signature']);
    }

    public function testPatternDetailCustomPagination(): void
    {
        $repo = $this->createMock(ActivityRepository::class);
        $repo->expects(self::once())
            ->method('findByPatternSignaturePaginated')
            ->with('easy 5km', 3, 25, 'pace', 'ASC')
            ->willReturn(['activities' => [], 'total' => 100]);
        $repo->method('findByPatternSignature')->willReturn([]);

        $capturedParams = $this->captureTwigParams('pattern/detail.html.twig');

        $request = new Request(['page' => '3', 'sort' => 'pace', 'direction' => 'ASC']);
        $this->controller->patternDetail('easy 5km', $request, $repo);

        self::assertSame(3, $capturedParams['page']);
        self::assertSame('pace', $capturedParams['sort']);
        self::assertSame('ASC', $capturedParams['direction']);
        self::assertSame(4, $capturedParams['totalPages']); // ceil(100/25)
    }

    public function testPatternDetailPageMinimumIsOne(): void
    {
        $repo = $this->createMock(ActivityRepository::class);
        $repo->expects(self::once())
            ->method('findByPatternSignaturePaginated')
            ->with('easy 5km', 1, 25, 'date', 'DESC')
            ->willReturn(['activities' => [], 'total' => 0]);
        $repo->method('findByPatternSignature')->willReturn([]);

        $capturedParams = $this->captureTwigParams('pattern/detail.html.twig');

        $request = new Request(['page' => '-5']);
        $this->controller->patternDetail('easy 5km', $request, $repo);

        self::assertSame(1, $capturedParams['page']);
    }

    public function testPatternDetailTrendWithTwoActivities(): void
    {
        // First activity: 3.5 m/s → pace = 1000/3.5 ≈ 285.7 sec/km
        // Last activity: 4.0 m/s → pace = 1000/4.0 = 250 sec/km
        // Delta = 285.7 - 250 = 35.7 sec → positive = improved
        $first = $this->makeActivity(averageSpeed: 3.5);
        $last = $this->makeActivity(averageSpeed: 4.0);

        $repo = self::createStub(ActivityRepository::class);
        $repo->method('findByPatternSignaturePaginated')
            ->willReturn(['activities' => [$first, $last], 'total' => 2]);
        $repo->method('findByPatternSignature')->willReturn([$first, $last]);

        $capturedParams = $this->captureTwigParams('pattern/detail.html.twig');

        $request = new Request();
        $this->controller->patternDetail('easy 5km', $request, $repo);

        self::assertNotNull($capturedParams['trendText']);
        self::assertStringContainsString('improved', $capturedParams['trendText']);
        self::assertStringContainsString('2 sessions', $capturedParams['trendText']);
    }

    public function testPatternDetailTrendSlower(): void
    {
        // First activity: 4.0 m/s → pace = 250 sec/km
        // Last activity: 3.5 m/s → pace ≈ 285.7 sec/km
        // Delta = 250 - 285.7 = -35.7 → negative = slower
        $first = $this->makeActivity(averageSpeed: 4.0);
        $last = $this->makeActivity(averageSpeed: 3.5);

        $repo = self::createStub(ActivityRepository::class);
        $repo->method('findByPatternSignaturePaginated')
            ->willReturn(['activities' => [$first, $last], 'total' => 2]);
        $repo->method('findByPatternSignature')->willReturn([$first, $last]);

        $capturedParams = $this->captureTwigParams('pattern/detail.html.twig');

        $request = new Request();
        $this->controller->patternDetail('easy 5km', $request, $repo);

        self::assertNotNull($capturedParams['trendText']);
        self::assertStringContainsString('slower', $capturedParams['trendText']);
    }

    public function testPatternDetailNoTrendWithSingleActivity(): void
    {
        $activity = $this->makeActivity();

        $repo = self::createStub(ActivityRepository::class);
        $repo->method('findByPatternSignaturePaginated')
            ->willReturn(['activities' => [$activity], 'total' => 1]);
        $repo->method('findByPatternSignature')->willReturn([$activity]);

        $capturedParams = $this->captureTwigParams('pattern/detail.html.twig');

        $request = new Request();
        $this->controller->patternDetail('easy 5km', $request, $repo);

        self::assertNull($capturedParams['trendText']);
    }

    public function testPatternDetailTotalPagesCalculation(): void
    {
        $repo = self::createStub(ActivityRepository::class);
        $repo->method('findByPatternSignaturePaginated')
            ->willReturn(['activities' => [], 'total' => 51]);
        $repo->method('findByPatternSignature')->willReturn([]);

        $capturedParams = $this->captureTwigParams('pattern/detail.html.twig');

        $request = new Request();
        $this->controller->patternDetail('easy 5km', $request, $repo);

        self::assertSame(3, $capturedParams['totalPages']); // ceil(51/25)
    }
}
