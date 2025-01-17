<?php

declare(strict_types=1);

namespace Enqueue\Dbal\Tests\Spec\Postgresql;

use Enqueue\Dbal\DbalContext;
use Interop\Queue\Context;
use Interop\Queue\Spec\SubscriptionConsumerConsumeFromAllSubscribedQueuesSpec;

/**
 * @group functional
 * @group Dbal
 */
class DbalSubscriptionConsumerConsumeFromAllSubscribedQueuesTest extends SubscriptionConsumerConsumeFromAllSubscribedQueuesSpec
{
    use CreateDbalContextTrait;

    /**
     * @return DbalContext
     */
    protected function createContext()
    {
        return $this->createDbalContext();
    }

    /**
     * @param DbalContext $context
     */
    protected function createQueue(Context $context, $queueName)
    {
        $queue = parent::createQueue($context, $queueName);
        $context->purgeQueue($queue);

        return $queue;
    }
}
