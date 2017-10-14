<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Dtc\QueueBundle\Doctrine\BaseJobManager;
use Dtc\QueueBundle\Entity\Job;

class JobManager extends BaseJobManager
{
    public function stopIdGenerator($objectName)
    {
        $objectManager = $this->getObjectManager();
        $repository = $objectManager->getRepository($objectName);
        /** @var ClassMetadata $metadata */
        $metadata = $objectManager->getClassMetadata($repository->getClassName());
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new AssignedGenerator());
    }

    public function countJobsByStatus($objectName, $status, $workerName = null, $method = null)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();

        $qb = $objectManager
            ->createQueryBuilder()
            ->select('count(a.id)')
            ->from($objectName, 'a')
            ->where('a.status = :status');

        if ($workerName) {
            $qb->andWhere('a.workerName = :workerName')
                ->setParameter(':workerName', $workerName);
        }

        if ($method) {
            $qb->andWhere('a.method = :method')
                ->setParameter(':method', $workerName);
        }

        $count = $qb->setParameter(':status', $status)
            ->getQuery()->getSingleScalarResult();

        if (!$count) {
            return 0;
        }

        return $count;
    }

    /**
     * @param string $workerName
     * @param string $method
     */
    public function pruneErroneousJobs($workerName = null, $method = null)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder()->delete($this->getArchiveObjectName(), 'j');
        $qb = $qb
            ->where('j.status = :status')
            ->setParameter(':status', Job::STATUS_ERROR);

        if ($workerName) {
            $qb->andWhere('j.workerName = :workerName')->setParameter(':workerName', $workerName);
        }

        if ($method) {
            $qb->andWhere('j.method = :method')->setParameter(':method', $method);
        }

        $query = $qb->getQuery();

        return $query->execute();
    }

    /**
     * @param string $workerName
     * @param string $method
     */
    public function pruneExpiredJobs($workerName = null, $method = null)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder()->delete($this->getObjectName(), 'j');
        $qb = $qb
            ->where('j.expiresAt <= :expiresAt')
            ->setParameter(':expiresAt', new \DateTime());

        if ($workerName) {
            $qb->andWhere('j.workerName = :workerName')->setParameter(':workerName', $workerName);
        }

        if ($method) {
            $qb->andWhere('j.method = :method')->setParameter(':method', $method);
        }

        $query = $qb->getQuery();

        return $query->execute();
    }

    /**
     * Removes archived jobs older than $olderThan.
     *
     * @param \DateTime $olderThan
     */
    public function pruneArchivedJobs(\DateTime $olderThan)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder()->delete($this->getArchiveObjectName(), 'j');
        $qb = $qb
            ->where('j.updatedAt < :updatedAt')
            ->setParameter(':updatedAt', $olderThan);

        $query = $qb->getQuery();

        return $query->execute();
    }

    public function getJobCount($workerName = null, $method = null)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder('j');

        $qb = $qb->select('count(j)')->from($this->getObjectName(), 'j');

        $where = 'where';
        if ($workerName) {
            if ($method) {
                $qb->where($qb->expr()->andX(
                    $qb->expr()->eq('j.workerName', ':workerName'),
                                                $qb->expr()->eq('j.method', ':method')
                ))
                    ->setParameter(':method', $method);
            } else {
                $qb->where('j.workerName = :workerName');
            }
            $qb->setParameter(':workerName', $workerName);
            $where = 'andWhere';
        } elseif ($method) {
            $qb->where('j.method = :method')->setParameter(':method', $method);
            $where = 'andWhere';
        }

        $dateTime = new \DateTime();
        // Filter
        $qb
            ->$where($qb->expr()->orX(
                $qb->expr()->isNull('j.whenAt'),
                                        $qb->expr()->lte('j.whenAt', ':whenAt')
            ))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('j.expiresAt'),
                $qb->expr()->gt('j.expiresAt', ':expiresAt')
            ))
            ->andWhere('j.locked is NULL')
            ->setParameter(':whenAt', $dateTime)
            ->setParameter(':expiresAt', $dateTime);

        $query = $qb->getQuery();

        return $query->getSingleScalarResult();
    }

    /**
     * Get Jobs statuses.
     */
    public function getStatus()
    {
        $result = [];
        $this->getStatusByEntityName($this->getObjectName(), $result);
        $this->getStatusByEntityName($this->getObjectName(), $result);

        $finalResult = [];
        foreach ($result as $key => $item) {
            ksort($item);
            $finalResult[$key] = $item;
        }

        return $finalResult;
    }

    /**
     * @param string $entityName
     */
    protected function getStatusByEntityName($entityName, array &$result)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        $result1 = $objectManager->getRepository($entityName)->createQueryBuilder('j')->select('j.workerName, j.method, j.status, count(j) as c')
            ->groupBy('j.workerName, j.method, j.status')->getQuery()->getArrayResult();

        foreach ($result1 as $item) {
            $method = $item['workerName'].'->'.$item['method'];
            if (!isset($result[$method])) {
                $result[$method] = [Job::STATUS_NEW => 0,
                                    Job::STATUS_RUNNING => 0,
                                    Job::STATUS_SUCCESS => 0,
                                    Job::STATUS_ERROR => 0, ];
            }
            $result[$method][$item['status']] += intval($item['c']);
        }
    }

    /**
     * Get the next job to run (can be filtered by workername and method name).
     *
     * @param string $workerName
     * @param string $methodName
     * @param bool   $prioritize
     *
     * @return Job|null
     */
    public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        $uniqid = uniqid(gethostname().'-'.getmypid(), true);
        $hash = hash('sha256', $uniqid);

        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();

        $objectManager->beginTransaction();

        /** @var EntityRepository $repository */
        $repository = $this->getRepository();
        $qb = $repository->createQueryBuilder('j');
        $dateTime = new \DateTime();
        $qb
            ->select('j')
            ->where('j.status = :status')->setParameter(':status', Job::STATUS_NEW)
            ->andWhere('j.locked is NULL')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('j.whenAt'),
                        $qb->expr()->lte('j.whenAt', ':whenAt')
            ))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('j.expiresAt'),
                        $qb->expr()->gt('j.expiresAt', ':expiresAt')
            ))
            ->setParameter(':whenAt', $dateTime)
            ->setParameter(':expiresAt', $dateTime);

        if ($workerName) {
            $qb->andWhere('j.workerName = :workerName')
                ->setParameter(':workerName', $workerName);
        }

        if ($methodName) {
            $qb->andWhere('j.method = :method')
                ->setParameter(':method', $methodName);
        }

        if ($prioritize) {
            $qb->add('orderBy', 'j.priority ASC, j.whenAt ASC');
        } else {
            $qb->orderBy('j.whenAt', 'ASC');
        }
        $qb->setMaxResults(1);

        /** @var QueryBuilder $qb */
        $query = $qb->getQuery();
        $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        $jobs = $query->getResult();

        if ($jobs) {
            /** @var Job $job */
            $job = $jobs[0];
            if (!$job) {
                throw new \Exception("No job found for $hash, even though last result was count ".count($jobs));
            }
            $job->setLocked(true);
            $job->setLockedAt(new \DateTime());
            $job->setStatus(Job::STATUS_RUNNING);
            $job->setRunId($runId);
            $objectManager->commit();
            $objectManager->flush();

            return $job;
        }

        $objectManager->rollback();

        return null;
    }
}
