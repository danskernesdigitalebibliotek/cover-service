<?php

namespace App\Repository;

use App\Entity\Source;
use App\Entity\Vendor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\QueryException;
use Doctrine\Persistence\ManagerRegistry;

class SourceRepository extends ServiceEntityRepository
{
    /**
     * SourceRepository constructor.
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Source::class);
    }

    /**
     * Find sources from list of match IDs and vendor.
     *
     * @param string $matchType
     * @param array $matchIdList
     *
     * @return mixed
     *  Array of sources indexed by match id
     *
     * @throws QueryException
     */
    public function findByMatchIdList(string $matchType, array $matchIdList, Vendor $vendor)
    {
        if (key($matchIdList)) {
            $idList = array_keys($matchIdList);
        } else {
            $idList = $matchIdList;
        }

        return $this->createQueryBuilder('s')
            ->andWhere('s.matchType = (:type)')
            ->andWhere('s.matchId IN (:ids)')
            ->andWhere('s.vendor = (:vendor)')
            ->setParameter('type', $matchType)
            ->setParameter('ids', $idList)
            ->setParameter('vendor', $vendor)
            ->orderBy('s.matchId', 'ASC')
            ->indexBy('s', 's.matchId')
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete all sources not found in given list match IDs.
     *
     * @return mixed
     */
    public function removeIdsNotInList(array $matchIdList, Vendor $vendor)
    {
        return $this->createQueryBuilder('s')
            ->delete('App:Source', 's')
            ->andWhere('s.matchId NOT IN (:ids)')
            ->andWhere('s.vendor = (:vendor)')
            ->setParameter('ids', $matchIdList)
            ->setParameter('vendor', $vendor)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find single Source entity with the highest rank (lowest rank value).
     *
     * @param string $type
     *   The identifier type
     * @param string $identifier
     *   The identifier to lookup
     *
     *   If found Source entity else false
     */
    public function findOneByVendorRank(string $type, string $identifier): Source|bool
    {
        $sources = $this->createQueryBuilder('s')
            ->andWhere('s.matchId = :identifier')
            ->andWhere('s.matchType = :type')
            ->leftJoin('s.vendor', 'vendor')
            ->orderBy('vendor.rank')
            ->setParameter('identifier', $identifier)
            ->setParameter('type', $type)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return end($sources);
    }

    /**
     * Get a query for sources that is limited by the parameters.
     *
     * @param int $limit
     *   The number of records to fetch
     * @param \DateTimeImmutable|null $lastIndexedDate
     *   Limit the fetched records by last indexed time
     * @param int $vendorId
     *   The vendor to fetch sources for
     * @param string|null $identifier
     *   Limit to single identifier
     * @param bool $withoutImage
     *   Include sources that does not have an image
     */
    public function findReindexabledSources(int $limit = 0, ?\DateTimeImmutable $lastIndexedDate = null, int $vendorId = 0, ?string $identifier = '', ?bool $withoutImage = false): Query
    {
        $queryBuilder = $this->createQueryBuilder('s');

        if (false === $withoutImage) {
            $queryBuilder->select('s')
                ->where('s.image IS NOT NULL');
        }

        if (0 < $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        if (0 < $vendorId) {
            $queryBuilder->andWhere('s.vendor = :vendorId')
                ->setParameter('vendorId', $vendorId);
        }

        if (!empty($identifier)) {
            $queryBuilder->andWhere('s.matchId = :identifier')
                ->setParameter('identifier', $identifier);
        }

        if (!is_null($lastIndexedDate)) {
            $queryBuilder->andWhere('s.lastIndexed < :lastIndexedDate OR s.lastIndexed is null')
                ->setParameter('lastIndexedDate', $lastIndexedDate, Types::DATETIME_IMMUTABLE);
        }

        // Order by date to ensure the newest is fetched first during reindex as they maybe the most wanted.
        $queryBuilder->orderBy('s.date', 'DESC');

        return $queryBuilder->getQuery();
    }
}
