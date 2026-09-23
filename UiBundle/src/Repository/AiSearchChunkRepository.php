<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Repository;

use c975L\UiBundle\Entity\AiSearchChunk;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @extends ServiceEntityRepository<AiSearchChunk>
 */
class AiSearchChunkRepository extends ServiceEntityRepository
{
    // What currentVersion() is kept under, emptied by replaceAll() - the search asks it on every page, the index only changing when it is rebuilt
    private const string VERSION_CACHE_KEY = 'ui_ai_search_index_version';

    // Asked several times per page (the navbar's trigger, the footer's), read once
    private ?string $version = null;
    private bool $versionRead = false;

    public function __construct(
        ManagerRegistry $registry,
        private readonly TagAwareCacheInterface $cache,
    ) {
        parent::__construct($registry, AiSearchChunk::class);
    }

    // The words of a question saying nothing about what is looked for, in the languages the bundle speaks: "vous" matches every page of a French site
    private const array STOPWORDS = [
        'avec', 'avez', 'avoir', 'combien', 'comme', 'comment', 'dans', 'elle', 'est-ce', 'faire', 'faites', 'leur', 'nous', 'pour', 'pourquoi', 'pouvez', 'puis', 'quand', 'quel', 'quelle', 'quelles', 'quels', 'sont', 'sur', 'votre', 'vous',
        'about', 'does', 'from', 'have', 'what', 'when', 'where', 'which', 'with', 'your',
        'como', 'cual', 'cuando', 'donde', 'para', 'puedo', 'tiene', 'usted', 'vuestro',
    ];

    // Kept at the head of a word: "contacter", "contact" and "contacté" all begin "conta", which is the stemming MariaDB's FULLTEXT index does not do
    private const int STEM_LENGTH = 5;

    // The question as a BOOLEAN MODE query: each word of four letters or more that says something, cut to its first STEM_LENGTH letters and matched as a prefix. Operators a visitor could type are dropped with the rest of the punctuation. Empty when nothing is left to look for
    public static function booleanQuery(string $question): string
    {
        preg_match_all('/[\p{L}\p{N}]{4,}/u', mb_strtolower($question), $matches);

        $terms = [];
        foreach ($matches[0] as $word) {
            if (!\in_array($word, self::STOPWORDS, true)) {
                $terms[mb_substr($word, 0, self::STEM_LENGTH) . '*'] = true;
            }
        }

        return implode(' ', array_keys($terms));
    }

    // The passages closest to a question, best first, matched on the stems booleanQuery() keeps. Native SQL: DQL has no MATCH ... AGAINST. Narrowed to the visitor's locale when the index holds any passage in it, the whole index otherwise - a single-language site declares its pages under one locale, whatever the visitor's browser says
    /** @return list<array{url: string, title: string, content: string}> */
    public function search(string $question, string $locale, int $limit): array
    {
        $query = self::booleanQuery($question);
        if ('' === $query) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $table = $this->getClassMetadata()->getTableName();

        $hasLocale = (bool) $connection->fetchOne("SELECT 1 FROM $table WHERE locale = ? LIMIT 1", [$locale]);
        $where = $hasLocale ? 'AND locale = :locale' : '';

        // The question bound under two names: a named placeholder used twice only works while PDO emulates its prepares
        $parameters = ['score' => $query, 'question' => $query];
        if ($hasLocale) {
            $parameters['locale'] = $locale;
        }

        $rows = $connection->fetchAllAssociative(
            "SELECT url, title, content, MATCH(title, content) AGAINST (:score IN BOOLEAN MODE) AS score
            FROM $table
            WHERE MATCH(title, content) AGAINST (:question IN BOOLEAN MODE) $where
            ORDER BY score DESC
            LIMIT " . max(1, $limit),
            $parameters,
        );

        return array_map(fn (array $row): array => [
            'url' => (string) $row['url'],
            'title' => (string) $row['title'],
            'content' => (string) $row['content'],
        ], $rows);
    }

    // The version the whole index was written with, null while nothing was ever indexed
    public function currentVersion(): ?string
    {
        if ($this->versionRead) {
            return $this->version;
        }

        // An empty string stands for "never indexed", a null the pool could not tell from a miss
        $version = $this->cache->get(self::VERSION_CACHE_KEY, function (ItemInterface $item): string {
            $item->expiresAfter(null);

            $row = $this->createQueryBuilder('c')
                ->select('c.indexVersion')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            return (string) ($row['indexVersion'] ?? '');
        });

        $this->versionRead = true;

        return $this->version = '' === $version ? null : $version;
    }

    // Swaps the whole index in one transaction, so a visitor asking during a rebuild reads the old one or the new one, never half of each. The chunks stay managed afterwards, which a run bounded by AiSearchIndexer::MAX_PAGES can afford
    // @param list<AiSearchChunk> $chunks
    public function replaceAll(array $chunks): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->wrapInTransaction(function () use ($entityManager, $chunks): void {
            $entityManager->createQuery('DELETE FROM ' . AiSearchChunk::class . ' c')->execute();
            foreach ($chunks as $chunk) {
                $entityManager->persist($chunk);
            }
            $entityManager->flush();
        });

        $this->cache->delete(self::VERSION_CACHE_KEY);
        $this->versionRead = false;
    }
}
