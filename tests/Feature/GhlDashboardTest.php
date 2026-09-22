<?php

namespace Tests\Feature;

use App\Jobs\ProcessGhlContactWebhook;
use App\Jobs\SyncGhlContacts;
use App\Services\GhlClient;
use App\Services\GhlContactRepository;
use App\Services\GhlSyncStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GhlDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->bindSyncStatus();
    }

    public function test_dashboard_renders_company_campaign_segments_from_ghl_tags(): void
    {
        $this->withoutVite();
        $this->bindDashboardContacts();

        config([
            'services.ghl.base_url' => 'https://services.leadconnectorhq.com',
            'services.ghl.access_token' => 'fake-token',
            'services.ghl.version' => '2021-07-28',
            'services.ghl.company_id' => null,
            'services.ghl.location_id' => 'loc_123',
            'services.ghl.audit_report_url_field_id' => 'audit_field_123',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://services.leadconnectorhq.com/contacts/search' => function ($request) {
                $tag = $request->data()['filters'][0]['value'];
                $filters = $request->data()['filters'];

                if (collect($filters)->contains(fn (array $filter): bool => $filter['field'] === 'customFields.audit_field_123')) {
                    return Http::response([
                        'contacts' => [],
                        'total' => 1,
                    ]);
                }

                if (in_array($tag, ['top4 signup', 'crazy domains'], true) && $request->data()['pageLimit'] === 100) {
                    return Http::response([
                        'contacts' => [
                            [
                                'id' => 'remaining_empty',
                                'firstName' => 'Remaining',
                                'lastName' => 'Empty',
                                'email' => 'remaining-empty@example.test',
                                'phone' => '+10000000001',
                                'businessName' => 'Remaining Empty Company',
                                'dateAdded' => '2026-09-10T02:30:00.000Z',
                                'tags' => [$tag],
                                'customFields' => [],
                            ],
                            [
                                'id' => 'remaining_filled',
                                'firstName' => 'Remaining',
                                'lastName' => 'Filled',
                                'email' => 'remaining-filled@example.test',
                                'phone' => '+10000000002',
                                'businessName' => 'Remaining Filled Company',
                                'dateAdded' => '2026-09-10T02:30:00.000Z',
                                'tags' => [$tag],
                                'customFields' => [
                                    [
                                        'id' => 'audit_field_123',
                                        'value' => 'https://example.test/report',
                                    ],
                                ],
                            ],
                        ],
                        'total' => 3,
                    ]);
                }

                if ($tag === 'audit outreach - no website - styled') {
                    return Http::response([
                        'contacts' => [
                            [
                                'id' => 'hidden_empty_business',
                                'firstName' => 'Hidden',
                                'lastName' => 'Empty',
                                'email' => 'hidden-empty@example.test',
                                'phone' => '+10000000000',
                                'dateAdded' => '2026-09-10T02:30:00.000Z',
                                'tags' => [$tag],
                            ],
                            ...collect(range(1, 6))->map(fn (int $index): array => [
                                'id' => 'replacement_'.$index,
                                'firstName' => 'Replacement',
                                'lastName' => (string) $index,
                                'email' => 'replacement-'.$index.'@example.test',
                                'phone' => '+1000000000'.$index,
                                'businessName' => 'Replacement Company '.$index,
                                'dateAdded' => '2026-09-10T02:30:00.000Z',
                                'tags' => [$tag],
                            ])->all(),
                        ],
                        'total' => 7,
                    ]);
                }

                return Http::response([
                    'contacts' => [
                        [
                            'id' => 'contact_123',
                            'firstName' => 'POC',
                            'lastName' => 'Contact',
                            'email' => 'team@example.test',
                            'phone' => '+10000000000',
                            'businessName' => 'POC Company',
                            'dateAdded' => '2026-09-10T02:30:00.000Z',
                            'tags' => [$tag],
                        ],
                    ],
                    'total' => 3,
                ]);
            },
        ]);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Company Campaign Control')
            ->assertSee('GHL connected')
            ->assertSee('Sent, has website')
            ->assertSee('Sent, no website')
            ->assertSee('Report opened')
            ->assertSee('Remaining')
            ->assertSee('POC Company')
            ->assertSee('Replacement Company 6')
            ->assertDontSee('Hidden Empty')
            ->assertSee('Remaining Empty Company')
            ->assertDontSee('Plain, Top4 signup')
            ->assertDontSee('Remaining Filled Company');

        Http::assertNothingSent();
    }

    public function test_date_range_loads_matching_samples_on_the_server(): void
    {
        $this->withoutVite();
        $this->bindDashboardContacts();

        config([
            'services.ghl.base_url' => 'https://services.leadconnectorhq.com',
            'services.ghl.access_token' => 'fake-token',
            'services.ghl.version' => '2021-07-28',
            'services.ghl.company_id' => null,
            'services.ghl.location_id' => 'loc_123',
            'services.ghl.audit_report_url_field_id' => 'audit_field_123',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://services.leadconnectorhq.com/contacts/search' => function ($request) {
                $filters = $request->data()['filters'];

                if (collect($filters)->contains(fn (array $filter): bool => $filter['field'] === 'customFields.audit_field_123')) {
                    return Http::response([
                        'contacts' => [],
                        'total' => 0,
                    ]);
                }

                return Http::response([
                    'contacts' => [
                        [
                            'id' => 'old_contact',
                            'firstName' => 'Old',
                            'lastName' => 'Contact',
                            'email' => 'old@example.test',
                            'businessName' => 'Old Company',
                            'dateAdded' => '2026-09-09T12:00:00.000Z',
                            'tags' => [$filters[0]['value']],
                        ],
                        [
                            'id' => 'matching_contact',
                            'firstName' => 'Matching',
                            'lastName' => 'Contact',
                            'email' => 'matching@example.test',
                            'businessName' => 'In Range Company',
                            'dateAdded' => '2026-09-10T12:00:00.000Z',
                            'tags' => [$filters[0]['value']],
                        ],
                    ],
                    'total' => 2,
                ]);
            },
        ]);

        $response = $this->get('/?from=2026-09-10&to=2026-09-10');

        $response
            ->assertOk()
            ->assertSee('In Range Company')
            ->assertDontSee('Old Company');

        Http::assertNothingSent();
    }

    public function test_dashboard_renders_ghl_email_open_rate_card(): void
    {
        $this->withoutVite();
        $this->bindDashboardContacts();

        config([
            'services.ghl.base_url' => 'https://services.leadconnectorhq.com',
            'services.ghl.access_token' => 'fake-token',
            'services.ghl.email_stats_access_token' => 'fake-email-token',
            'services.ghl.email_stats_version' => 'v3',
            'services.ghl.location_id' => 'loc_123',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://services.leadconnectorhq.com/emails/locations/loc_123/campaigns/workflows*' => Http::response([
                'campaigns' => [
                    [
                        'id' => 'workflow_123',
                        'sourceId' => 'workflow_source_123',
                        'name' => 'No Website - Plain',
                        'status' => 'published',
                        'updatedAt' => '2026-09-18T09:00:00.000Z',
                    ],
                ],
                'total' => 1,
            ]),
            'https://services.leadconnectorhq.com/emails/locations/loc_123/campaigns/stats/workflow-campaigns/workflow_source_123' => Http::response([
                'stats' => [
                    'sent' => 89134,
                    'delivered' => 58927,
                    'opened' => 10258,
                    'unsubscribed' => 177,
                    'complained' => 18,
                    'permanentFail' => 1200,
                    'temporaryFail' => 800,
                    'rejected' => 200,
                    'failed' => 89,
                ],
            ]),
        ]);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Performance Analysis')
            ->assertSee('Open Rate by selected workflow')
            ->assertSee('58,927')
            ->assertSee('2,289')
            ->assertSee('17.41%')
            ->assertSee('10,258')
            ->assertSee('1 campaign stats loaded');
    }

    public function test_dashboard_aggregates_workflow_email_action_stats(): void
    {
        $this->withoutVite();
        $this->bindDashboardContacts();

        config([
            'services.ghl.base_url' => 'https://services.leadconnectorhq.com',
            'services.ghl.access_token' => 'fake-token',
            'services.ghl.email_stats_access_token' => 'fake-email-token',
            'services.ghl.email_stats_version' => 'v3',
            'services.ghl.location_id' => 'loc_123',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://services.leadconnectorhq.com/emails/locations/loc_123/campaigns/workflows*' => Http::response([
                'campaigns' => [
                    [
                        'id' => 'workflow_123',
                        'sourceId' => 'workflow_source_123',
                        'name' => 'No Website - Plain',
                        'status' => 'published',
                        'updatedAt' => '2026-09-18T09:00:00.000Z',
                    ],
                ],
                'total' => 1,
            ]),
            'https://services.leadconnectorhq.com/emails/locations/loc_123/campaigns/stats/workflow-campaigns/workflow_source_123' => Http::response([
                'stats' => ['delivered' => 150, 'opened' => 40],
            ]),
        ]);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('150')
            ->assertSee('26.67%')
            ->assertSee('40')
            ->assertSee('1 campaign stats loaded');
    }

    public function test_ghl_client_uses_recent_cache_when_connection_times_out(): void
    {
        config([
            'services.ghl.base_url' => 'https://services.leadconnectorhq.com',
            'services.ghl.access_token' => 'fake-token',
            'services.ghl.version' => '2021-07-28',
            'services.ghl.location_id' => 'loc_123',
            'services.ghl.timeout' => 6,
        ]);

        Cache::flush();
        Http::preventStrayRequests();

        $attempt = 0;
        Http::fake([
            'https://services.leadconnectorhq.com/contacts/search' => function () use (&$attempt) {
                $attempt++;

                if ($attempt === 1) {
                    return Http::response([
                        'contacts' => [
                            [
                                'id' => 'cached_contact',
                                'businessName' => 'Cached Company',
                            ],
                        ],
                        'total' => 1,
                    ]);
                }

                throw new ConnectionException('Connection timed out.');
            },
        ]);

        $client = app(GhlClient::class);
        $liveResult = $client->contactsByTag('cached tag', 1, 6);
        $cachedResult = $client->contactsByTag('cached tag', 1, 6);

        $this->assertTrue($liveResult['ok']);
        $this->assertTrue($cachedResult['ok']);
        $this->assertSame('Cached Company', data_get($cachedResult, 'data.contacts.0.businessName'));
        $this->assertSame(1, data_get($cachedResult, 'data.total'));
    }

    public function test_ghl_sync_request_queues_background_job(): void
    {
        Queue::fake();

        $response = $this->post('/ghl/sync');

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('ghl_sync_status', 'HighLevel sync queued. The database will update in the background.');

        Queue::assertPushedOn('ghl-sync', SyncGhlContacts::class);
    }

    public function test_ghl_webhook_queues_contact_processing_job(): void
    {
        Queue::fake();
        config(['services.ghl.webhook_secret' => 'webhook-secret']);

        $response = $this->postJson('/webhooks/ghl', [
            'type' => 'ContactUpdate',
            'contact' => [
                'id' => 'contact_123',
                'email' => 'team@example.test',
            ],
        ], [
            'X-GHL-Webhook-Secret' => 'webhook-secret',
        ]);

        $response
            ->assertOk()
            ->assertJson(['message' => 'GHL webhook accepted.']);

        Queue::assertPushedOn('ghl-sync', ProcessGhlContactWebhook::class);
    }

    public function test_ghl_webhook_rejects_invalid_secret(): void
    {
        Queue::fake();
        config(['services.ghl.webhook_secret' => 'webhook-secret']);

        $response = $this->postJson('/webhooks/ghl', [
            'type' => 'ContactUpdate',
            'contact' => ['id' => 'contact_123'],
        ], [
            'X-GHL-Webhook-Secret' => 'wrong-secret',
        ]);

        $response->assertUnauthorized();
        Queue::assertNothingPushed();
    }

    private function bindDashboardContacts(): void
    {
        $this->app->instance(GhlContactRepository::class, new class extends GhlContactRepository
        {
            public function hasSyncedContacts(): bool
            {
                return true;
            }

            public function segmentResult(array $segment, array $tags, int $sampleLimit, array $dateRange): array
            {
                $contacts = match ($segment['label']) {
                    'Styled' => $this->styledNoWebsiteContacts($tags[0]),
                    'Top4 signup' => [$this->remainingEmptyContact($tags[0])],
                    default => [$this->pocContact($tags[0])],
                };

                if (($dateRange['from'] ?? null) === '2026-09-10') {
                    $contacts = [$this->matchingContact($tags[0])];
                }

                return [
                    'ok' => true,
                    'status' => null,
                    'data' => [
                        'contacts' => array_slice($contacts, 0, $sampleLimit),
                        'total' => count($contacts),
                    ],
                    'error' => null,
                ];
            }

            private function pocContact(string $tag): array
            {
                return [
                    'id' => 'contact_123',
                    'firstName' => 'POC',
                    'lastName' => 'Contact',
                    'email' => 'team@example.test',
                    'phone' => '+10000000000',
                    'businessName' => 'POC Company',
                    'dateAdded' => '2026-09-10T02:30:00.000Z',
                    'tags' => [$tag],
                ];
            }

            private function styledNoWebsiteContacts(string $tag): array
            {
                return [
                    ...collect(range(1, 6))->map(fn (int $index): array => [
                        'id' => 'replacement_'.$index,
                        'firstName' => 'Replacement',
                        'lastName' => (string) $index,
                        'email' => 'replacement-'.$index.'@example.test',
                        'phone' => '+1000000000'.$index,
                        'businessName' => 'Replacement Company '.$index,
                        'dateAdded' => '2026-09-10T02:30:00.000Z',
                        'tags' => [$tag],
                    ])->all(),
                    ['id' => 'hidden_empty_business', 'firstName' => 'Hidden', 'lastName' => 'Empty', 'email' => 'hidden-empty@example.test', 'dateAdded' => '2026-09-10T02:30:00.000Z', 'tags' => [$tag]],
                ];
            }

            private function remainingEmptyContact(string $tag): array
            {
                return [
                    'id' => 'remaining_empty',
                    'firstName' => 'Remaining',
                    'lastName' => 'Empty',
                    'email' => 'remaining-empty@example.test',
                    'phone' => '+10000000001',
                    'businessName' => 'Remaining Empty Company',
                    'dateAdded' => '2026-09-10T02:30:00.000Z',
                    'tags' => [$tag],
                    'customFields' => [],
                ];
            }

            private function matchingContact(string $tag): array
            {
                return [
                    'id' => 'matching_contact',
                    'firstName' => 'Matching',
                    'lastName' => 'Contact',
                    'email' => 'matching@example.test',
                    'businessName' => 'In Range Company',
                    'dateAdded' => '2026-09-10T12:00:00.000Z',
                    'tags' => [$tag],
                ];
            }
        });
    }

    private function bindSyncStatus(): void
    {
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
    }
}
