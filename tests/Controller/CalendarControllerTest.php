<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\CalendarController;
use App\Entity\Activity;
use App\Repository\ActivityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * @internal
 * @coversNothing
 */
final class CalendarControllerTest extends TestCase
{
    private CalendarController $controller;

    protected function setUp(): void
    {
        $this->controller = new CalendarController();

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

    // =========================================================================
    // home()
    // =========================================================================

    public function testHomeRedirectsToCalendar(): void
    {
        $response = $this->controller->home();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/activities', $response->headers->get('Location'));
    }

    // =========================================================================
    // calendar()
    // =========================================================================

    public function testCalendarDefaultsToCurrentMonth(): void
    {
        $repo = self::createStub(ActivityRepository::class);
        $repo->method('findByMonth')->willReturn([]);
        $repo->method('findDistinctPatternSignatures')->willReturn([]);
        $repo->method('findDistinctGearNames')->willReturn([]);

        $capturedParams = $this->captureTwigParams('calendar/index.html.twig');

        $request = new Request();
        $this->controller->calendar($request, $repo);

        self::assertSame((int) date('Y'), $capturedParams['year']);
        self::assertSame((int) date('n'), $capturedParams['month']);
        self::assertTrue($capturedParams['isCurrentMonth']);
    }

    public function testCalendarMonthUnderflowWrapsToDecemberPreviousYear(): void
    {
        $repo = self::createStub(ActivityRepository::class);
        $repo->method('findByMonth')->willReturn([]);
        $repo->method('findDistinctPatternSignatures')->willReturn([]);
        $repo->method('findDistinctGearNames')->willReturn([]);

        $capturedParams = $this->captureTwigParams('calendar/index.html.twig');

        $request = new Request(['year' => '2025', 'month' => '0']);
        $this->controller->calendar($request, $repo);

        self::assertSame(2024, $capturedParams['year']);
        self::assertSame(12, $capturedParams['month']);
    }

    public function testCalendarMonthOverflowWrapsToJanuaryNextYear(): void
    {
        $repo = self::createStub(ActivityRepository::class);
        $repo->method('findByMonth')->willReturn([]);
        $repo->method('findDistinctPatternSignatures')->willReturn([]);
        $repo->method('findDistinctGearNames')->willReturn([]);

        $capturedParams = $this->captureTwigParams('calendar/index.html.twig');

        // Use a year far in the past so the future-capping won't interfere
        $request = new Request(['year' => '2020', 'month' => '13']);
        $this->controller->calendar($request, $repo);

        self::assertSame(2021, $capturedParams['year']);
        self::assertSame(1, $capturedParams['month']);
    }

    public function testCalendarFutureMonthClampedToCurrentMonth(): void
    {
        $repo = self::createStub(ActivityRepository::class);
        $repo->method('findByMonth')->willReturn([]);
        $repo->method('findDistinctPatternSignatures')->willReturn([]);
        $repo->method('findDistinctGearNames')->willReturn([]);

        $capturedParams = $this->captureTwigParams('calendar/index.html.twig');

        $request = new Request(['year' => '2099', 'month' => '6']);
        $this->controller->calendar($request, $repo);

        self::assertSame((int) date('Y'), $capturedParams['year']);
        self::assertSame((int) date('n'), $capturedParams['month']);
        self::assertTrue($capturedParams['isCurrentMonth']);
    }

    public function testCalendarGroupsActivitiesByDay(): void
    {
        $activity1 = $this->makeActivity(date: new \DateTimeImmutable('2024-06-15'));
        $activity2 = $this->makeActivity(date: new \DateTimeImmutable('2024-06-15'));
        $activity3 = $this->makeActivity(date: new \DateTimeImmutable('2024-06-20'));

        $repo = self::createStub(ActivityRepository::class);
        $repo->method('findByMonth')->willReturn([$activity1, $activity2, $activity3]);
        $repo->method('findDistinctPatternSignatures')->willReturn([]);
        $repo->method('findDistinctGearNames')->willReturn([]);

        $capturedParams = $this->captureTwigParams('calendar/index.html.twig');

        $request = new Request(['year' => '2024', 'month' => '6']);
        $this->controller->calendar($request, $repo);

        self::assertCount(2, $capturedParams['activitiesByDay'][15]);
        self::assertCount(1, $capturedParams['activitiesByDay'][20]);
    }

    public function testCalendarPassesFiltersToRepository(): void
    {
        $repo = $this->createMock(ActivityRepository::class);
        $repo->expects(self::once())
            ->method('findByMonth')
            ->with(2024, 3, 'easy 5km', 'Nike Pegasus')
            ->willReturn([]);
        $repo->method('findDistinctPatternSignatures')->willReturn(['easy 5km']);
        $repo->method('findDistinctGearNames')->willReturn(['Nike Pegasus']);

        $capturedParams = $this->captureTwigParams('calendar/index.html.twig');

        $request = new Request(['year' => '2024', 'month' => '3', 'pattern' => 'easy 5km', 'gear' => 'Nike Pegasus']);
        $this->controller->calendar($request, $repo);

        self::assertSame('easy 5km', $capturedParams['selectedPattern']);
        self::assertSame('Nike Pegasus', $capturedParams['selectedGear']);
    }

    public function testCalendarComputesDaysInMonth(): void
    {
        $repo = self::createStub(ActivityRepository::class);
        $repo->method('findByMonth')->willReturn([]);
        $repo->method('findDistinctPatternSignatures')->willReturn([]);
        $repo->method('findDistinctGearNames')->willReturn([]);

        $capturedParams = $this->captureTwigParams('calendar/index.html.twig');

        // February 2024 is a leap year
        $request = new Request(['year' => '2024', 'month' => '2']);
        $this->controller->calendar($request, $repo);

        self::assertSame(29, $capturedParams['daysInMonth']);
    }

    public function testCalendarStartWeekday(): void
    {
        $repo = self::createStub(ActivityRepository::class);
        $repo->method('findByMonth')->willReturn([]);
        $repo->method('findDistinctPatternSignatures')->willReturn([]);
        $repo->method('findDistinctGearNames')->willReturn([]);

        $capturedParams = $this->captureTwigParams('calendar/index.html.twig');

        // June 2024 starts on Saturday (day 6)
        $request = new Request(['year' => '2024', 'month' => '6']);
        $this->controller->calendar($request, $repo);

        self::assertSame(6, $capturedParams['startWeekday']);
    }

    public function testCalendarEmptyFiltersTreatedAsNull(): void
    {
        $repo = $this->createMock(ActivityRepository::class);
        $repo->expects(self::once())
            ->method('findByMonth')
            ->with(2024, 3, null, null)
            ->willReturn([]);
        $repo->method('findDistinctPatternSignatures')->willReturn([]);
        $repo->method('findDistinctGearNames')->willReturn([]);

        $request = new Request(['year' => '2024', 'month' => '3', 'pattern' => '', 'gear' => '']);
        $this->controller->calendar($request, $repo);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Configures a Twig stub to capture template parameters.
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

    private function makeActivity(?\DateTimeImmutable $date = null): Activity
    {
        $activity = new Activity();
        $activity->setStravaId((string) random_int(1, 999999));
        $activity->setName('Test Activity');
        $activity->setDistance(5000.0);
        $activity->setElapsedTime(1800);
        $activity->setAverageSpeed(3.5);
        $activity->setActivityDate($date ?? new \DateTimeImmutable('2024-06-15'));
        $activity->setSyncedAt(new \DateTimeImmutable());

        return $activity;
    }
}
