<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use RuntimeException;

final class PdfSeedParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public function parse(string $pdfPath): array
    {
        if (! file_exists($pdfPath)) {
            throw new RuntimeException("PDF seed file not found: {$pdfPath}");
        }

        /**
         * In this prototype, we keep the parser deterministic and rely on curated
         * seed structures distilled from the PDF package.
         */
        return config('graphrag_seed.topics', []);
    }
}

