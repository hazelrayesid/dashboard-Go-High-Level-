<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GhlDashboardTest extends TestCase
{
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
            ->assertSee('Remaining Empty Company')
            ->assertDontSee('Plain, Top4 signup')
            ->assertDontSee('Remaining Filled Company');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://services.leadconnectorhq.com/contacts/search'
            && $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request->data() === [
                'locationId' => 'loc_123',
                'page' => 1,
                'pageLimit' => 6,
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
}
