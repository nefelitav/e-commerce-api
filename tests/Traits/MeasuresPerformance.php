<?php

namespace Tests\Traits;

use Closure;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait for measuring and asserting response times in performance tests.
 * Eliminates the repetitive microtime() + assertLessThan boilerplate.
 *
 * @mixin \Tests\TestCase
 */
trait MeasuresPerformance
{
    /**
     * Assert that a callable completes within the given time threshold (ms).
     *
     * @param  float  $maxMs  Maximum allowed milliseconds
     * @param  Closure  $callback  The operation to measure
     * @param  string  $label  Human-readable label for the assertion message
     */
    protected function assertCompletesWithinMs(float $maxMs, Closure $callback, string $label = 'Operation'): mixed
    {
        $start = microtime(true);

        $result = $callback();

        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertLessThan(
            $maxMs,
            $elapsed,
            "{$label} took {$elapsed}ms, exceeding threshold of {$maxMs}ms",
        );

        return $result;
    }

    /**
     * Measure a GET request and assert it completes within the threshold.
     *
     * @return TestResponse<Response>
     */
    protected function assertGetRespondsWithinMs(string $url, float $maxMs, string $label = 'GET request'): TestResponse
    {
        return $this->assertCompletesWithinMs($maxMs, fn () => $this->getJson($url), $label);
    }

    /**
     * Measure a POST request and assert it completes within the threshold.
     *
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    protected function assertPostRespondsWithinMs(string $url, array $data, float $maxMs, string $label = 'POST request'): TestResponse
    {
        return $this->assertCompletesWithinMs($maxMs, fn () => $this->postJson($url, $data), $label);
    }

    /**
     * Run a callable N times and assert the average time is within threshold.
     *
     * @param  positive-int  $iterations
     * @return non-empty-list<float> Array of individual timings in ms
     */
    protected function assertAverageTimeWithinMs(int $iterations, float $maxAvgMs, Closure $callback, string $label = 'Operation'): array
    {
        $timings = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $callback($i);
            $timings[] = (microtime(true) - $start) * 1000;
        }

        $avg = array_sum($timings) / count($timings);

        $this->assertLessThan(
            $maxAvgMs,
            $avg,
            "{$label}: average was {$avg}ms across {$iterations} iterations, exceeding threshold of {$maxAvgMs}ms",
        );

        return $timings;
    }

    /**
     * Run a callable N times and assert the maximum time is within threshold.
     *
     * @param  positive-int  $iterations
     * @return non-empty-list<float> Array of individual timings in ms
     */
    protected function assertMaxTimeWithinMs(int $iterations, float $maxMs, Closure $callback, string $label = 'Operation'): array
    {
        $timings = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $callback($i);
            $timings[] = (microtime(true) - $start) * 1000;
        }

        /** @var non-empty-list<float> $timings */
        $max = max($timings);

        $this->assertLessThan(
            $maxMs,
            $max,
            "{$label}: slowest was {$max}ms across {$iterations} iterations, exceeding threshold of {$maxMs}ms",
        );

        return $timings;
    }
}
