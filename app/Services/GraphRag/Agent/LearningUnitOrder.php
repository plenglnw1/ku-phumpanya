<?php

declare(strict_types=1);

namespace App\Services\GraphRag\Agent;

/**
 * Orders learning units by the concepts they teach and need. Port of KU-BCG
 * lib/learningPath.ts `orderUnits`, which is the reference and carries the tests
 * for the data checker.
 *
 * B comes after A when B needs a concept A teaches. A unit's phase is the length of
 * the longest chain of such units before it; units in one phase are listed easiest
 * first, ties keeping retrieval order.
 */
final class LearningUnitOrder
{
    /**
     * @param  array<string, mixed>  $doc
     */
    public static function isUnit(array $doc): bool
    {
        return is_array($doc['teaches'] ?? null) || is_array($doc['needs'] ?? null);
    }

    /**
     * `ordered` is false when no unit depends on another; the caller then groups by
     * topic and must not present the result as a sequence.
     *
     * @param  list<array<string, mixed>>  $units
     * @return array{ordered: bool, phases: list<list<array<string, mixed>>>}
     */
    public static function order(array $units): array
    {
        $units = array_values($units);
        $deps = [];
        foreach ($units as $i => $unit) {
            $needs = self::concepts($unit['needs'] ?? []);
            $deps[$i] = [];
            foreach ($units as $j => $other) {
                if ($j !== $i && array_intersect(self::concepts($other['teaches'] ?? []), $needs) !== []) {
                    $deps[$i][] = $j;
                }
            }
        }

        $depth = [];
        $visiting = [];
        $depthOf = function (int $i) use (&$depthOf, &$depth, &$visiting, $deps): int {
            if (isset($depth[$i])) {
                return $depth[$i];
            }
            // A cycle means bad data; break it here rather than recurse forever.
            if (isset($visiting[$i])) {
                return 0;
            }
            $visiting[$i] = true;
            $depth[$i] = $deps[$i] === [] ? 0 : 1 + max(array_map($depthOf, $deps[$i]));
            unset($visiting[$i]);

            return $depth[$i];
        };

        $phases = [];
        foreach (array_keys($units) as $i) {
            $phases[$depthOf($i)][] = [$i, $units[$i]];
        }
        ksort($phases);

        $sorted = array_map(function (array $phase): array {
            usort($phase, fn (array $a, array $b): int => [self::difficulty($a[1]), $a[0]] <=> [self::difficulty($b[1]), $b[0]]);

            return array_column($phase, 1);
        }, array_values($phases));

        return [
            'ordered' => collect($deps)->contains(fn (array $d): bool => $d !== []),
            'phases' => $sorted,
        ];
    }

    /**
     * Concept fields as sent on a module, only those the document has.
     *
     * @param  array<string, mixed>  $doc
     * @return array{teaches?: list<string>, needs?: list<string>, difficulty?: int, concept_source?: string}
     */
    public static function moduleFields(array $doc): array
    {
        if (! self::isUnit($doc)) {
            return [];
        }

        $fields = [
            'teaches' => self::concepts($doc['teaches'] ?? []),
            'needs' => self::concepts($doc['needs'] ?? []),
        ];
        if (in_array((int) ($doc['difficulty'] ?? 0), [1, 2, 3], true)) {
            $fields['difficulty'] = (int) $doc['difficulty'];
        }
        if (in_array($doc['concept_source'] ?? null, ['curated', 'ai'], true)) {
            $fields['concept_source'] = $doc['concept_source'];
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private static function concepts(mixed $value): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $c): string => is_scalar($c) ? trim((string) $c) : '',
            (array) $value,
        )));
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    private static function difficulty(array $unit): int
    {
        $level = (int) ($unit['difficulty'] ?? 0);

        return in_array($level, [1, 2, 3], true) ? $level : 2;
    }
}
