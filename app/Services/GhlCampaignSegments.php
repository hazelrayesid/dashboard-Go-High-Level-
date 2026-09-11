<?php

namespace App\Services;

class GhlCampaignSegments
{
    /**
     * @return array<string, array<int, array{label: string, tag?: string, tags?: array<int, string>, requires_empty_audit_report_url?: bool}>>
     */
    public function groups(): array
    {
        return [
            'Sent, has website' => [
                ['label' => 'Plain', 'tags' => ['audit outreach - has website - plain', 'audit outreach - has website - plain (top4 signup)']],
                ['label' => 'Styled', 'tags' => ['audit outreach - has website - styled', 'audit outreach - has website - styled (top4 signup)']],
            ],
            'Sent, no website' => [
                ['label' => 'Plain', 'tags' => ['audit outreach - no website - plain', 'audit outreach - no website - plain (top4 signup)']],
                ['label' => 'Styled', 'tags' => ['audit outreach - no website - styled', 'audit outreach - no website - styled (top4 signup)']],
            ],
            'Report opened' => [
                ['label' => 'Report generated', 'tag' => 'audit outreach - report generated'],
            ],
            'Remaining' => [
                ['label' => 'Top4 signup', 'tag' => 'top4 signup', 'requires_empty_audit_report_url' => true],
                ['label' => 'Crazy Domains', 'tag' => 'crazy domains', 'requires_empty_audit_report_url' => true],
            ],
        ];
    }

    /**
     * @param  array{label: string, tag?: string, tags?: array<int, string>, requires_empty_audit_report_url?: bool}  $segment
     * @return array<int, string>
     */
    public function tags(array $segment): array
    {
        return collect($segment['tags'] ?? [$segment['tag']])
            ->filter(fn (mixed $tag): bool => is_string($tag) && filled($tag))
            ->values()
            ->all();
    }

    /**
     * @param  array{label: string, tag?: string, tags?: array<int, string>, requires_empty_audit_report_url?: bool}  $segment
     */
    public function tagLabel(array $segment): string
    {
        return collect($this->tags($segment))->implode(' + ');
    }

    public function count(): int
    {
        return collect($this->groups())
            ->sum(fn (array $segments): int => count($segments));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function emptyGroups(): array
    {
        return collect($this->groups())
            ->map(fn (array $segments): array => collect($segments)
                ->map(fn (array $segment): array => [
                    'label' => $segment['label'],
                    'tag' => $this->tagLabel($segment),
                    'tags' => $this->tags($segment),
                    'total' => 0,
                    'companies' => [],
                    'ok' => false,
                    'status' => null,
                ])
                ->all())
            ->all();
    }
}
