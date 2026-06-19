<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class DocsSync extends Command
{
    protected $signature = 'docs:sync';

    protected $description = 'Generate llms.txt from docs/openapi.yaml for AI assistant context';

    public function handle(): int
    {
        $yamlPath = base_path('docs/openapi.yaml');

        if (! file_exists($yamlPath)) {
            $this->error('docs/openapi.yaml not found.');

            return self::FAILURE;
        }

        $spec = Yaml::parseFile($yamlPath);
        $out = $this->buildLlmsTxt($spec);

        file_put_contents(public_path('llms.txt'), $out);

        $this->info('llms.txt generated → public/llms.txt (' . substr_count($out, "\n") . ' lines)');

        return self::SUCCESS;
    }

    private function buildLlmsTxt(array $spec): string
    {
        $info = $spec['info'] ?? [];
        $baseUrl = ($spec['servers'][0]['url'] ?? 'http://127.0.0.1:8000');
        $paths = $spec['paths'] ?? [];
        $tags = collect($spec['tags'] ?? [])->keyBy('name');
        $schemas = $spec['components']['schemas'] ?? [];

        $lines = [];

        // ── Header ───────────────────────────────────────────────────────────

        $lines[] = '# ' . ($info['title'] ?? 'API') . ' — LLM Context';
        $lines[] = 'Version: ' . ($info['version'] ?? '1.0');
        $lines[] = 'Base URL: ' . $baseUrl;
        $lines[] = 'Interactive docs: ' . $baseUrl . '/api-docs';
        $lines[] = 'OpenAPI spec: ' . $baseUrl . '/api-docs/openapi.yaml';
        $lines[] = '';

        // ── Auth ─────────────────────────────────────────────────────────────

        $lines[] = '## Authentication';
        $lines[] = '';
        $lines[] = 'Bearer token via Laravel Sanctum. Prefix: pos_';
        $lines[] = 'Header: Authorization: Bearer pos_your_token_here';
        $lines[] = 'Header: Accept: application/json';
        $lines[] = '';
        $lines[] = 'Create token: POST /api/tokens';
        $lines[] = 'Body: {"name":"BotName","abilities":["read","write","capture"]}';
        $lines[] = 'Response includes plain_text_token (shown once only).';
        $lines[] = 'Abilities: read, write, capture, reports, tokens';
        $lines[] = '';

        // ── Quick Capture ─────────────────────────────────────────────────────

        $lines[] = '## Quick Capture — primary endpoint for AI/bot integration';
        $lines[] = '';
        $lines[] = 'POST /api/v1/quick-capture  (ability: capture)';
        $lines[] = 'Optional header: Idempotency-Key — deduplicates retried requests for 24h';
        $lines[] = '';
        $lines[] = 'Required fields: type, title (max 180)';
        $lines[] = 'Optional fields: date (YYYY-MM-DD), project_name (max 120), duration_minutes, notes';
        $lines[] = '';
        $lines[] = 'type values and what they create:';
        $lines[] = '  task           → Task with status=todo';
        $lines[] = '  blocker        → Task with status=blocked';
        $lines[] = '  work_log       → Work Log entry';
        $lines[] = '  daily_progress → Daily Progress entry';
        $lines[] = '  learning       → Learning entry (title used as topic)';
        $lines[] = '';
        $lines[] = 'Response: {"data":{...},"record":{...},"record_path":"/tasks/42","message":"Captured."}';
        $lines[] = '';

        // ── Endpoints by tag ─────────────────────────────────────────────────

        $lines[] = '## All Endpoints';
        $lines[] = '';

        $grouped = [];
        foreach ($paths as $path => $methods) {
            foreach ($methods as $method => $op) {
                if (! is_array($op)) {
                    continue;
                }
                $tag = $op['tags'][0] ?? 'Other';
                $grouped[$tag][] = compact('method', 'path', 'op');
            }
        }

        foreach ($grouped as $tag => $entries) {
            $desc = $tags[$tag]['description'] ?? '';
            $lines[] = '### ' . $tag . ($desc ? ' — ' . $desc : '');

            foreach ($entries as ['method' => $method, 'path' => $path, 'op' => $op]) {
                $ability = $this->ability($op);
                $summary = $op['summary'] ?? '';
                $prefix = strtoupper($method) . ' ' . $path;
                $suffix = ($ability ? "  [{$ability}]" : '') . ($summary ? "  — {$summary}" : '');
                $lines[] = $prefix . $suffix;

                $qp = $this->queryParams($op);
                if ($qp) {
                    $lines[] = '  Query: ' . $qp;
                }

                $body = $this->bodyFields($op, $schemas);
                if ($body) {
                    $lines[] = '  Body: ' . $body;
                }

                $ret = $this->returnShape($op);
                if ($ret) {
                    $lines[] = '  Returns: ' . $ret;
                }
            }

            $lines[] = '';
        }

        // ── Write schemas ─────────────────────────────────────────────────────

        $lines[] = '## Request Schemas (write operations)';
        $lines[] = '';

        $toDocument = [
            'QuickCaptureRequest', 'TaskRequest', 'WorkLogRequest',
            'DailyProgressRequest', 'LearningRequest', 'MilestoneRequest',
            'HabitRequest', 'GoalRequest', 'KeyResultRequest',
            'SavedViewRequest', 'ReferenceRequest', 'DocRequest',
        ];

        foreach ($toDocument as $name) {
            if (! isset($schemas[$name])) {
                continue;
            }
            $fields = $this->schemaFields($schemas[$name], $schemas);
            if ($fields) {
                $lines[] = $name . ':';
                foreach ($fields as $f) {
                    $lines[] = '  ' . $f;
                }
                $lines[] = '';
            }
        }

        // ── Response envelope ─────────────────────────────────────────────────

        $lines[] = '## Response Envelope';
        $lines[] = '';
        $lines[] = 'Item:       {"data":{...},"message":"...","<key>":{...}}';
        $lines[] = 'Collection: {"data":[...],"<key>":[...]}';
        $lines[] = 'Paginated:  {"data":[...],"meta":{"current_page":1,"last_page":3,"total":55,"per_page":20},"links":{"prev":null,"next":"..."},"<key>":[...]}';
        $lines[] = '';
        $lines[] = 'Named response keys per module:';
        $lines[] = '  daily-progress → entry / entries';
        $lines[] = '  work-logs      → log / logs';
        $lines[] = '  tasks          → task / tasks';
        $lines[] = '  learning       → entry / entries';
        $lines[] = '  milestones     → milestone / milestones';
        $lines[] = '  projects       → project / projects';
        $lines[] = '  habits         → habits';
        $lines[] = '  goals          → goal / goals';
        $lines[] = '  notifications  → notifications';
        $lines[] = '  docs           → doc / docs';
        $lines[] = '';

        // ── Error responses ───────────────────────────────────────────────────

        $lines[] = '## Error Responses';
        $lines[] = '';
        $lines[] = '401  {"message":"Unauthenticated."}';
        $lines[] = '403  {"message":"This action is unauthorized."}';
        $lines[] = '422  {"message":"The given data was invalid.","errors":{"field":["Error message."]}}';
        $lines[] = '404  {"message":"Not found."}';
        $lines[] = '';

        // ── Footer ────────────────────────────────────────────────────────────

        $lines[] = '---';
        $lines[] = 'Generated: ' . now()->toDateTimeString();
        $lines[] = 'Source: docs/openapi.yaml — run `php artisan docs:sync` to regenerate';

        return implode("\n", $lines) . "\n";
    }

    private function ability(array $op): string
    {
        $scopes = ($op['security'][0] ?? [])['sanctumBearer'] ?? [];

        return implode(',', $scopes);
    }

    private function queryParams(array $op): string
    {
        $params = array_filter($op['parameters'] ?? [], fn ($p) => ($p['in'] ?? '') === 'query');
        $names = array_map(fn ($p) => $p['name'] . (($p['required'] ?? false) ? '*' : ''), $params);

        return implode(', ', $names);
    }

    private function bodyFields(array $op, array $schemas): string
    {
        $content = $op['requestBody']['content']['application/json'] ?? null;
        if (! $content) {
            return '';
        }

        $ref = $content['schema']['$ref'] ?? null;
        if ($ref) {
            $name = $this->refName($ref);
            $schema = $schemas[$name] ?? null;
            if (! $schema) {
                return $name;
            }
            $fields = $this->inlineFields($schema, $schemas);
            $extra = count($fields) > 8 ? ', ...' : '';

            return implode(', ', array_slice($fields, 0, 8)) . $extra;
        }

        $props = $content['schema']['properties'] ?? [];
        $required = $content['schema']['required'] ?? [];
        $fields = array_map(
            fn ($n) => $n . (in_array($n, $required) ? '*' : ''),
            array_keys($props)
        );

        return implode(', ', array_slice($fields, 0, 8));
    }

    private function returnShape(array $op): string
    {
        $resp = $op['responses']['200'] ?? $op['responses']['201'] ?? null;
        if (! $resp) {
            return '';
        }

        $allOf = $resp['content']['application/json']['schema']['allOf'] ?? null;
        if ($allOf) {
            foreach ($allOf as $part) {
                if (isset($part['properties'])) {
                    return '{ ' . implode(', ', array_keys($part['properties'])) . ' }';
                }
            }
        }

        $example = $resp['content']['application/json']['example'] ?? null;
        if ($example && is_array($example)) {
            return '{ ' . implode(', ', array_slice(array_keys($example), 0, 5)) . ' }';
        }

        return $resp['description'] ?? '';
    }

    private function inlineFields(array $schema, array $schemas): array
    {
        $props = [];
        $required = [];

        if (isset($schema['allOf'])) {
            foreach ($schema['allOf'] as $part) {
                if (isset($part['$ref'])) {
                    $parent = $schemas[$this->refName($part['$ref'])] ?? [];
                    $props = array_merge($parent['properties'] ?? [], $props);
                    $required = array_merge($parent['required'] ?? [], $required);
                } else {
                    $props = array_merge($props, $part['properties'] ?? []);
                    $required = array_merge($required, $part['required'] ?? []);
                }
            }
        }

        $props = array_merge($props, $schema['properties'] ?? []);
        $required = array_merge($required, $schema['required'] ?? []);

        return array_map(
            fn ($n) => $n . (in_array($n, $required) ? '*' : ''),
            array_keys($props)
        );
    }

    private function schemaFields(array $schema, array $schemas): array
    {
        $props = [];
        $required = [];

        if (isset($schema['allOf'])) {
            foreach ($schema['allOf'] as $part) {
                if (isset($part['$ref'])) {
                    $parent = $schemas[$this->refName($part['$ref'])] ?? [];
                    $props = array_merge($parent['properties'] ?? [], $props);
                    $required = array_merge($parent['required'] ?? [], $required);
                } else {
                    $props = array_merge($props, $part['properties'] ?? []);
                    $required = array_merge($required, $part['required'] ?? []);
                }
            }
        }

        $props = array_merge($props, $schema['properties'] ?? []);
        $required = array_merge($required, $schema['required'] ?? []);

        $lines = [];
        foreach ($props as $name => $def) {
            $req = in_array($name, $required) ? ' (required)' : '';
            $type = is_array($def['type'] ?? null) ? implode('|', $def['type']) : ($def['type'] ?? 'any');
            $enum = isset($def['enum']) ? ' [' . implode('|', array_filter($def['enum'])) . ']' : '';
            $desc = isset($def['description']) ? ' — ' . $def['description'] : '';
            $lines[] = $name . $req . ': ' . $type . $enum . $desc;
        }

        return $lines;
    }

    private function refName(string $ref): string
    {
        return basename(str_replace('#/components/schemas/', '', $ref));
    }
}
