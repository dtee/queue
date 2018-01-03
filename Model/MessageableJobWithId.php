<?php

namespace Dtc\QueueBundle\Model;

abstract class MessageableJobWithId extends MessageableJob
{
    /**
     * @return string A json_encoded version of a queueable version of the object
     */
    public function toMessage()
    {
        $arr = $this->toMessageArray();
        $arr['id'] = $this->getId();

        return json_encode($arr);
    }

    /**
     * @param string $message a json_encoded version of the object
     */
    public function fromMessage($message)
    {
        $arr = json_decode($message, true);
        if (is_array($arr)) {
            $this->fromMessageArray($arr);
            if (isset($arr['id'])) {
                $this->setId($arr['id']);
            }
        }
    }
}
