<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

/**
 * The `source` values in ku_bcg_documents.
 */
final class DocumentSource
{
    public const KUKR = 'KUKR';

    public const FOREST = 'KU_Forest';

    public const MOOC = 'KU_MOOC';

    public const NON_DEGREE = 'KU_NonDegree';

    /** Sources whose documents are courses a learner can enrol in. */
    public const COURSES = [self::MOOC, self::NON_DEGREE];

    /** Text fields matched by free-text search; keep in step with the index mapping. */
    public const SEARCH_FIELDS = [
        'title^3',
        'title_en^2',
        'keywords^2',
        'topic_names_th',
        'topic_names_en',
        'abstract',
        'abstract_en',
    ];

    /**
     * @param  array<string, mixed>  $doc
     */
    public static function isCourse(array $doc): bool
    {
        return in_array($doc['source'] ?? '', self::COURSES, true);
    }
}
