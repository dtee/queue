<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\GridBundle\Annotation as Grid;

/**
 * @Grid\Grid(actions={@Grid\ShowAction(), @Grid\DeleteAction(label="Archive")})
 * @ODM\Document(db="dtc_queue", collection="run")
 */
class Run extends BaseRun
{
}
