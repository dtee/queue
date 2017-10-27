<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\ORM\EntityManager;

trait CommonTrait
{
    /**
     * @param \Doctrine\ODM\MongoDB\DocumentManager $entityManager
     * @param string                                $objectName
     * @param string                                $field
     * @param \DateTime                             $olderThan
     *
     * @return int
     */
    protected function removeOlderThan(EntityManager $entityManager, $objectName, $field, \DateTime $olderThan)
    {
        $qb = $entityManager->createQueryBuilder()->delete($objectName, 'j');
        $qb = $qb
            ->where('j.'.$field.' < :'.$field)
            ->setParameter(':'.$field, $olderThan);

        $query = $qb->getQuery();

        return $query->execute();
    }
}
