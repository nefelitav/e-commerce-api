<?php

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('api');
    }

    public function test_api_rate_limiting_allows_requests_within_limit(): void
    {
        // Make 5 requests (well within the 60/minute limit)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->getJson(route('v1.products.index'));
            $response->assertStatus(200);
            $response->assertHeader('X-RateLimit-Limit', '60');
        }
    }

    public function test_api_rate_limiting_blocks_requests_exceeding_limit(): void
    {
        // Simulate exceeding rate limit
        $ip = '192.168.1.1';

        // Make 60 requests
        for ($i = 0; $i < 60; $i++) {
            $this->getJson(route('v1.products.index'), ['REMOTE_ADDR' => $ip]);
        }

        // The 61st request should be rate limited
        $response = $this->getJson(route('v1.products.index'), ['REMOTE_ADDR' => $ip]);
        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    public function test_rate_limit_headers_are_present(): void
    {
        $response = $this->getJson(route('v1.products.index'));

        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_rate_limiting_is_per_ip_address(): void
    {
        $ip1 = '192.168.1.1';
        $ip2 = '192.168.1.2';

        // Make 60 requests from IP1
        for ($i = 0; $i < 60; $i++) {
            $this->getJson(route('v1.products.index'), ['REMOTE_ADDR' => $ip1]);
        }

        // Request from IP1 should be rate limited
        $response1 = $this->getJson(route('v1.products.index'), ['REMOTE_ADDR' => $ip1]);
        $response1->assertStatus(429);

        // Request from IP2 should still work (different IP)
        $response2 = $this->getJson(route('v1.products.index'), ['REMOTE_ADDR' => $ip2]);
        $response2->assertStatus(200);
    }
}



