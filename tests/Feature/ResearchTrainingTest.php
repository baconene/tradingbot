<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

final class ResearchTrainingTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_requires_operator_token_and_never_enables_execution(): void
    {
        Cache::forget('astra:research:training:lock');
        $this->withoutMiddleware(ThrottleRequests::class);
        config()->set('astra.research_training_token','operator-secret');
        config()->set('queue.default','database');
        Queue::fake();
        $this->postJson('/api/research/training')->assertUnauthorized();
        $this->withToken('incorrect')->postJson('/api/research/training')->assertUnauthorized();
        $this->withToken('operator-secret')->postJson('/api/research/training')->assertAccepted()
            ->assertJsonPath('execution_enabled',false);
        Queue::assertPushed(\App\Jobs\RunResearchTraining::class,1);
        $this->withToken('operator-secret')->postJson('/api/research/training')->assertStatus(409);
        Cache::forget('astra:research:training:lock');
        Cache::forget('astra:research:training:status');
    }

    public function test_sync_queue_is_refused_and_history_is_read_only(): void
    {
        Cache::forget('astra:research:training:lock');
        config()->set('astra.research_training_token','operator-secret');
        config()->set('queue.default','sync');
        $this->withToken('operator-secret')->postJson('/api/research/training')->assertStatus(503);
        $this->get('/api/research/training')->assertOk()->assertJsonPath('execution_enabled',false)
            ->assertJsonPath('runs',[]);
    }
}
