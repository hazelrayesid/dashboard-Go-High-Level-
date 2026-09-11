<?php

namespace Tests\Feature;

use App\Services\GhlClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GhlDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_dashboard_renders_company_campaign_segments_from_ghl_tags(): void
    {
        $this->withoutVite();

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

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://services.leadconnectorhq.com/contacts/search'
            && $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request->data() === [
                'locationId' => 'loc_123',
                'page' => 1,
                'pageLimit' => 100,
                'filters' => [
                    [
                        'field' => 'tags',
                        'operator' => 'eq',
                        'value' => 'audit outreach - has website - plain',
                    ],
                ],
            ]);

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://services.leadconnectorhq.com/contacts/search'
            && $request->data()['filters'] === [
                [
                    'field' => 'tags',
                    'operator' => 'eq',
                    'value' => 'top4 signup',
                ],
                [
                    'field' => 'customFields.audit_field_123',
                    'operator' => 'not_eq',
                    'value' => '',
                ],
            ]);
    }

    public function test_date_range_loads_matching_samples_on_the_server(): void
    {
        $this->withoutVite();

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

        Http::assertSent(fn ($request): bool => collect($request->data()['filters'] ?? [])
            ->contains(fn (array $filter): bool => $filter === [
                'field' => 'dateAdded',
                'operator' => 'range',
                'value' => [
                    'gte' => '2026-09-10T00:00:00.000000Z',
                    'lte' => '2026-09-10T23:59:59.999999Z',
                ],
            ]));
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
}
