<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

/**
 * @deprecated Use RelationGraphBuilder directly. Thin wrapper for backward compatibility.
 */
final class SubgraphBuilder
{
    public function __construct(
        private readonly RelationGraphBuilder $graphBuilder,
    ) {}

    /**
     * @param  list<string>  $entities
     * @return array{nodes: list<array<string, string>>, edges: list<array<string, string>>, center: array<string, string>, description: string}
     */
    public function build(array $entities, int $maxNodes = 40, int $maxEdges = 80): array
    {
        return $this->graphBuilder->build($entities, [], $maxNodes, $maxEdges);
    }
}
