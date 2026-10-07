<?php

declare(strict_types=1);

namespace Plugin\HeaderFooterBuilder\Support;

use InvalidArgumentException;

final class Conditions
{
    public static function normalize(array $input): array
    {
        $rules = ['global' => !empty($input['global'])];
        foreach (
            ['pages', 'content_types', 'roles', 'exclude_pages', 'exclude_content_types', 'exclude_roles'] as $key
        ) {
            if (!is_array($input[$key] ?? [])) {
                throw new InvalidArgumentException('Display conditions must contain lists.');
            }
            $rules[$key] = array_values(array_unique(array_filter(array_map(
                static fn ($value): string => is_scalar($value) ? trim((string) $value) : '',
                $input[$key] ?? []
            ), static fn (string $value): bool => $value !== '')));
        }
        return $rules;
    }

    // Exclusions always win. Includes are ORed; a role restriction is ANDed.
    public static function score(array $rules, array $context): ?int
    {
        $page = (string) ($context['page_id'] ?? '');
        $type = (string) ($context['content_type'] ?? '');
        $roles = $context['roles'] ?? ['guest'];
        if (
            in_array($page, $rules['exclude_pages'] ?? [], true)
            || in_array($type, $rules['exclude_content_types'] ?? [], true)
            || array_intersect($roles, $rules['exclude_roles'] ?? []) !== []
        ) {
            return null;
        }
        if (($rules['roles'] ?? []) !== [] && array_intersect($roles, $rules['roles']) === []) {
            return null;
        }
        $score = in_array($page, $rules['pages'] ?? [], true) ? 300
        : (in_array($type, $rules['content_types'] ?? [], true) ? 200 : (!empty($rules['global']) ? 100 : null));
        return $score === null ? null : $score + (($rules['roles'] ?? []) !== [] ? 10 : 0);
    }

    public static function select(array $templates, string $kind, array $context): ?array
    {
        $matches = [];
        foreach ($templates as $template) {
            if ($template['kind'] !== $kind || empty($template['published'])) {
                continue;
            }
            $score = self::score($template['conditions'], $context);
            if ($score !== null) {
                $template['_score'] = $score;
                $matches[] = $template;
            }
        }
        usort($matches, static fn (array $a, array $b): int =>
            ($b['priority'] <=> $a['priority']) ?: ($b['_score'] <=> $a['_score']) ?: strcmp($a['id'], $b['id']));
        return $matches[0] ?? null;
    }
}
