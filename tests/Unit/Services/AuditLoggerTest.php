<?php

namespace Tests\Unit\Services;

use App\Models\UserModel;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    private AuditLogger $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditLogger = new AuditLogger();
    }

    public function test_logs_to_audit_channel(): void
    {
        Log::shouldReceive('channel')
            ->with('audit')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $action, array $context) {
                return $action === 'product.created'
                    && $context['entity'] === 'product'
                    && $context['entity_id'] === 42
                    && $context['action'] === 'product.created'
                    && $context['properties']['name'] === 'Test Product';
            });

        $this->auditLogger->log('product.created', 'product', 42, [
            'name' => 'Test Product',
        ]);
    }

    public function test_includes_authenticated_user_id(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        Log::shouldReceive('channel')
            ->with('audit')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $action, array $context) use ($user) {
                return $context['user_id'] === $user->id;
            });

        $this->auditLogger->log('order.created', 'order', 1);
    }

    public function test_user_id_is_null_for_unauthenticated(): void
    {
        Log::shouldReceive('channel')
            ->with('audit')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $action, array $context) {
                return $context['user_id'] === null;
            });

        $this->auditLogger->log('category.deleted', 'category', 5);
    }

    public function test_logs_with_empty_properties(): void
    {
        Log::shouldReceive('channel')
            ->with('audit')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $action, array $context) {
                return $context['properties'] === []
                    && $context['entity'] === 'order'
                    && $context['entity_id'] === 10;
            });

        $this->auditLogger->log('order.deleted', 'order', 10);
    }

    public function test_includes_timestamp(): void
    {
        Log::shouldReceive('channel')
            ->with('audit')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $action, array $context) {
                return isset($context['timestamp'])
                    && !empty($context['timestamp']);
            });

        $this->auditLogger->log('product.updated', 'product', 1);
    }
}

