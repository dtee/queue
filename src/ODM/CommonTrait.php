<?php

namespace Dtc\QueueBundle\ODM;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ODM\MongoDB\DocumentManager;

trait CommonTrait
{
    /**
     * @param string    $objectName
     * @param string    $field
     * @param \DateTime $olderThan
     *
     * @return int
     */
    protected function removeOlderThan($objectName, $field, \DateTime $olderThan)
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($objectName);
        $qb
            ->remove()
            ->field($field)->lt($olderThan);

        $query = $qb->getQuery();
        $result = $query->execute();
        if (isset($result['n'])) {
            return $result['n'];
        }

        return 0;
    }

    /**
     * @return ObjectManager
     */
    abstract public function getObjectManager();

    /**
     * @param string $objectName
     */
    public function stopIdGenerator($objectName)
    {
        // Not needed for ODM
    }

    public function restoreIdGenerator($objectName)
    {
        // Not needed for ODM
    }
}
