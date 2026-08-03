<?= "<?php\n" ?>
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace <?= $namespace ?>;

use <?= $entity_full_name ?>;
use c975L\UiBundle\Doctrine\VectorType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<<?= $entity_short_name ?>>
 */
class <?= $class_name ?> extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, <?= $entity_short_name ?>::class);
    }

    public function findOneByQuestionHash(string $questionHash): ?<?= $entity_short_name ?>
    {
        return $this->findOneBy(['questionHash' => $questionHash]);
    }

    // Raw SQL, DQL not knowing VEC_DISTANCE_COSINE(), which returns 1 - cosine_similarity
    // Names come from Doctrine's metadata, the app's naming strategy not being assumable
    /** @param float[] $embedding */
    public function findBestSemanticMatch(array $embedding, string $contextVersion, float $minSimilarity): ?<?= $entity_short_name ?>
    {
        $metadata = $this->getClassMetadata();
        $table = $metadata->getTableName();
        $contextVersionColumn = $metadata->getColumnName('contextVersion');
        $embeddingColumn = $metadata->getColumnName('questionEmbedding');
        $packedEmbedding = VectorType::pack($embedding);

        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            "SELECT id
             FROM {$table}
             WHERE {$contextVersionColumn} = ?
               AND {$embeddingColumn} IS NOT NULL
               AND VEC_DISTANCE_COSINE({$embeddingColumn}, ?) <= ?
             ORDER BY VEC_DISTANCE_COSINE({$embeddingColumn}, ?) ASC
             LIMIT 1",
            [$contextVersion, $packedEmbedding, 1 - $minSimilarity, $packedEmbedding],
            [ParameterType::STRING, ParameterType::BINARY, ParameterType::STRING, ParameterType::BINARY],
        );

        return false === $row ? null : $this->find((int) $row['id']);
    }
}
