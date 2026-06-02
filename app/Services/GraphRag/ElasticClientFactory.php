<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

final class ElasticClientFactory
{
    public function make(): ?Client
    {
        if (! config('elasticsearch.enabled')) {
            return null;
        }

        $builder = ClientBuilder::create()
            ->setHosts([config('elasticsearch.host')]);

        $apiKey = (string) config('elasticsearch.api_key');
        if ($apiKey !== '') {
            $builder->setApiKey($apiKey);
        }

        $username = (string) config('elasticsearch.username');
        $password = (string) config('elasticsearch.password');
        if ($username !== '' && $password !== '') {
            $builder->setBasicAuthentication($username, $password);
        }

        return $builder->build();
    }
}

