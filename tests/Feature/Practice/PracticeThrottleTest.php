<?php

declare(strict_types=1);

namespace Tests\Feature\Practice;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PracticeThrottleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function practice_show_route_has_throttle_120_middleware(): void
    {
        // Find the route and verify it has the correct middleware
        $routes = Route::getRoutes();
        $route = $routes->getByName('practice.show');

        $this->assertNotNull($route, 'practice.show route not found');

        // Check that the route has the throttle:120,1 middleware
        $middleware = $route->getAction()['middleware'] ?? [];

        $this->assertIsArray($middleware, 'middleware should be an array');

        $hasThrottle = false;
        foreach ($middleware as $m) {
            if (is_string($m) && strpos($m, 'throttle:120') !== false) {
                $hasThrottle = true;
                break;
            }
        }

        $this->assertTrue($hasThrottle, 'practice.show route should have throttle:120,1 middleware');
    }

    #[Test]
    public function the_429_error_page_contains_throttled_message(): void
    {
        // Verify the 429 view exists and has the right content
        $view = file_get_contents(resource_path('views/errors/429.blade.php'));

        $this->assertStringContainsString('errors.throttled', $view);
        $this->assertStringContainsString('layouts.guest', $view);
    }

    #[Test]
    public function practice_page_allows_normal_requests(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->create();

        $response = $this->actingAs($user)->get(route('practice.show', $source))->assertOk();

        // Verify the page loads without throttle errors
        $this->assertStringContainsString('practice', strtolower($response->getContent()));
    }
}
