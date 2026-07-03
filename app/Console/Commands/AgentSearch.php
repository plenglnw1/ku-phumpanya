<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GraphRag\Agent\AgentPipeline;
use Illuminate\Console\Command;

class AgentSearch extends Command
{
    protected $signature = 'agent:search {query : Natural language search query}';

    protected $description = 'Run Gemini agent pipeline (EllieSQL 3-tier) and print JSON result';

    public function handle(AgentPipeline $pipeline): int
    {
        $query = (string) $this->argument('query');
        $this->info("Query: {$query}");
        $this->newLine();

        $result = $pipeline->run($query);

        $this->line('Tier: '.($result['tier'] ?? 'unknown'));
        $this->line('Gemini calls: '.($result['_meta']['calls'] ?? 0));
        $this->line('Docs: '.($result['_meta']['docs_retrieved'] ?? 0));
        $this->line('Relations: '.($result['_meta']['relations_retrieved'] ?? 0));
        $this->newLine();

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
