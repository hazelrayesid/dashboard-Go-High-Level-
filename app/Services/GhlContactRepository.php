<?php

namespace App\Services;

use App\Models\GhlContact;
use Illuminate\Database\Eloquent\Builder;

class GhlContactRepository
{
    public function hasSyncedContacts(): bool
    {
        return GhlContact::query()->exists();
    }

    public function lastSyncedAt(): ?string
    {
        return GhlContact::query()->max('synced_at');
    }

    /**
     * @param  array{label: string, tag?: string, tags?: array<int, string>, requires_empty_audit_report_url?: bool}  $segment
     * @param  array<int, string>  $tags
     * @param  array{from?: string|null, to?: string|null}  $dateRange
     * @return array{ok: bool, status: int|null, data: array<string, mixed>, error: string|null}
     */
    public function segmentResult(array $segment, array $tags, int $sampleLimit, array $dateRange): array
    {
        $query = $this->baseSegmentQuery($tags, $dateRange);

        if ($segment['requires_empty_audit_report_url'] ?? false) {
            $query->where(fn (Builder $query): Builder => $query
                ->whereNull('audit_report_url')
                ->orWhere('audit_report_url', ''));
        }

        return [
            'ok' => true,
            'status' => null,
            'data' => [
                'contacts' => (clone $query)
                    ->orderByDesc('created_at_ghl')
                    ->limit($sampleLimit)
                    ->get()
                    ->map(fn (GhlContact $contact): array => $this->payload($contact))
                    ->all(),
                'total' => (clone $query)->count(),
            ],
            'error' => null,
        ];
    }

    /**
     * @param  array<int, string>  $tags
     * @param  array{from?: string|null, to?: string|null}  $dateRange
     */
    private function baseSegmentQuery(array $tags, array $dateRange): Builder
    {
        $query = GhlContact::query()
            ->where(fn (Builder $query): Builder => collect($tags)
                ->reduce(fn (Builder $query, string $tag): Builder => $query->orWhereJsonContains('tags', $tag), $query));

        if (filled($dateRange['from'] ?? null)) {
            $query->whereDate('created_date', '>=', $dateRange['from']);
        }

        if (filled($dateRange['to'] ?? null)) {
            $query->whereDate('created_date', '<=', $dateRange['to']);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(GhlContact $contact): array
    {
        $payload = is_array($contact->raw_payload) ? $contact->raw_payload : [];

        return array_merge($payload, [
            'id' => $contact->ghl_contact_id,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'businessName' => $contact->business,
            'dateAdded' => $contact->created_at_ghl?->toISOString() ?? $contact->created_date?->toDateString(),
            'tags' => $contact->tags ?? [],
            'customFields' => $contact->custom_fields ?? [],
        ]);
    }
}
