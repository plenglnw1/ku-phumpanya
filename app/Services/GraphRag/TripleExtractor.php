<?php

declare(strict_types=1);

namespace App\Services\GraphRag;

use Illuminate\Support\Str;

final class TripleExtractor
{
    /**
     * @param  array<string, mixed>  $topic
     * @return list<array<string, mixed>>
     */
    public function fromTopic(array $topic): array
    {
        $topicName = (string) ($topic['title'] ?? 'Unknown Topic');
        $topicId = (string) ($topic['topic_id'] ?? Str::slug($topicName));

        $triples = [];

        foreach (($topic['sources'] ?? []) as $source) {
            $sourceName = (string) ($source['source'] ?? 'UNKNOWN');
            $triples[] = $this->makeTriple($topicId, $topicName, 'sourcedFrom', $sourceName, $source);
        }

        foreach (($topic['courses'] ?? []) as $course) {
            $courseId = (string) ($course['course_id'] ?? 'course');
            $triples[] = $this->makeTriple($topicId, $topicName, 'hasCourse', $courseId, $course);
        }

        foreach (($topic['bcg_tags'] ?? []) as $tag) {
            $triples[] = $this->makeTriple($topicId, $topicName, 'relatedTo', "BCG_{$tag}", []);
        }

        foreach (($topic['faculty_tags'] ?? []) as $faculty) {
            $triples[] = $this->makeTriple($topicId, $topicName, 'linkedFaculty', (string) $faculty, []);
        }

        return $triples;
    }

    /**
     * @param  array<string, mixed>  $sourceMeta
     * @return array<string, mixed>
     */
    private function makeTriple(
        string $topicId,
        string $subject,
        string $predicate,
        string $object,
        array $sourceMeta
    ): array {
        $source = (string) ($sourceMeta['source'] ?? 'derived');
        $url = (string) ($sourceMeta['url'] ?? '');

        return [
            'triple_id' => sha1("{$topicId}|{$subject}|{$predicate}|{$object}|{$url}"),
            'subject' => $subject,
            'predicate' => $predicate,
            'object' => $object,
            'source' => $source,
            'url' => $url,
            'topic' => $topicId,
            'confidence' => 0.9,
        ];
    }
}

