<?php

namespace Tests\Feature;

use App\Services\GhlContactRepository;
use App\Services\GhlSyncStatus;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->app->instance(GhlContactRepository::class, new class extends GhlContactRepository
        {
            public function hasSyncedContacts(): bool
            {
                return false;
            }
        });
        $this->app->instance(GhlSyncStatus::class, new class extends GhlSyncStatus
        {
            public function summary(): array
            {
                return [
                    'pending' => 0,
                    'failed' => 0,
                    'contacts' => 0,
                    'active_contacts' => 0,
                ];
            }
        });

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
