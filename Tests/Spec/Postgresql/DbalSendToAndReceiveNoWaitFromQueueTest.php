<?php

namespace Enqueue\Dbal\Tests\Spec\Postgresql;

use Interop\Queue\Spec\SendToAndReceiveNoWaitFromQueueSpec;

/**
 * @group functional
 */
class DbalSendToAndReceiveNoWaitFromQueueTest extends SendToAndReceiveNoWaitFromQueueSpec
{
    use CreateDbalContextTrait;

    protected function createContext()
    {
        return $this->createDbalContext();
    }
}
