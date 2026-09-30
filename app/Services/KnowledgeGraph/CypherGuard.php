<?php

declare(strict_types=1);

namespace App\Services\KnowledgeGraph;

/**
 * Screens ad-hoc Cypher from the /graph console.
 *
 * The database is already read-only, so this is defence in depth against what a
 * read-only database still allows: procedures (n10s can fetch URLs), LOAD CSV
 * (server-side requests), USE (other databases, including system) and admin commands.
 */
final class CypherGuard
{
    public const MAX_LENGTH = 2000;

    private const FORBIDDEN = [
        'CALL', 'LOAD', 'USE', 'CREATE', 'MERGE', 'DELETE', 'DETACH', 'SET', 'REMOVE',
        'DROP', 'ALTER', 'RENAME', 'GRANT', 'DENY', 'REVOKE', 'SHOW', 'TERMINATE',
        'START', 'STOP', 'FOREACH', 'ENABLE', 'DEALLOCATE',
    ];

    /**
     * @throws KnowledgeGraphException when the query is not a plain read.
     */
    public function assertReadOnly(string $cypher): void
    {
        $query = trim($cypher);

        if ($query === '') {
            throw new KnowledgeGraphException('Query is empty.');
        }
        if (mb_strlen($query) > self::MAX_LENGTH) {
            throw new KnowledgeGraphException('Query is longer than '.self::MAX_LENGTH.' characters.');
        }

        $code = $this->withoutLiteralsAndComments($query);

        if (str_contains(rtrim($code, "; \n\r\t"), ';')) {
            throw new KnowledgeGraphException('Only one statement is allowed.');
        }

        $pattern = '/\b('.implode('|', self::FORBIDDEN).')\b/i';
        if (preg_match($pattern, $code, $match) === 1) {
            throw new KnowledgeGraphException(sprintf(
                '%s is not allowed here. Use MATCH … RETURN queries only.',
                strtoupper($match[1]),
            ));
        }
        if (preg_match('/\b(MATCH|RETURN)\b/i', $code) !== 1) {
            throw new KnowledgeGraphException('Use MATCH … RETURN queries only.');
        }
    }

    /**
     * Keywords inside strings, identifiers in backticks or comments must not trip the check.
     */
    private function withoutLiteralsAndComments(string $query): string
    {
        return (string) preg_replace(
            ['/\/\*.*?\*\//s', '/\/\/[^\n]*/', "/'(?:\\\\.|[^'\\\\])*'/s", '/"(?:\\\\.|[^"\\\\])*"/s', '/`[^`]*`/'],
            ' ',
            $query,
        );
    }
}
