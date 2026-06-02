<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GraphRag\ElasticIndexer;
use App\Services\GraphRag\GraphSeedBuilder;
use App\Services\GraphRag\PdfSeedParser;
use Illuminate\Console\Command;

class GraphRagSeedFromPdf extends Command
{
    protected $signature = 'graphrag:seed
        {pdf=/Users/pleng/cs-ku/ocs/docs/SKE_CrossSource_Links.pdf : PDF seed path}
        {--recreate : recreate indices before indexing}';

    protected $description = 'Seed GraphRAG indices from SKE Cross Source PDF package';

    public function __construct(
        private readonly PdfSeedParser $parser,
        private readonly GraphSeedBuilder $builder,
        private readonly ElasticIndexer $indexer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $pdfPath = (string) $this->argument('pdf');
        $topics = $this->parser->parse($pdfPath);
        $payload = $this->builder->build($topics);

        $this->indexer->createIndices();

        $indices = config('elasticsearch.indices');

        $this->indexer->bulkIndex($indices['docs'], $payload['docs']);
        $this->indexer->bulkIndex($indices['triples'], $payload['triples']);
        $this->indexer->bulkIndex($indices['entity_links'], $payload['links']);
        $this->indexer->bulkIndex($indices['learning_paths'], $payload['paths']);

        $this->info('GraphRAG seed completed.');
        $this->line('docs: '.count($payload['docs']));
        $this->line('triples: '.count($payload['triples']));
        $this->line('entity_links: '.count($payload['links']));
        $this->line('learning_paths: '.count($payload['paths']));

        return self::SUCCESS;
    }
}

