<?php

namespace TriTran\SqsQueueBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use TriTran\SqsQueueBundle\Service\BaseQueue;

/**
 * Class SQSQueuePass
 * @package TriTran\SqsQueueBundle\DependencyInjection\Compiler
 */
class SQSQueuePass implements CompilerPassInterface
{
    /**
     * Load configuration of Machine Engine
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('aws.sqs')) {
            throw new \InvalidArgumentException('AWS SQSClient is required to use queue.');
        }
        if ($container->hasParameter('tritran.sqs_queue.queues')) {
            /** @var array $queues */
            $queues = $container->getParameter('tritran.sqs_queue.queues');
            foreach ($queues as $queueName => $queueOption) {
                $defaultQueueServices = ['queues', 'queue_factory', 'queue_manager', 'queue_worker'];
                if (in_array($queueName, $defaultQueueServices, true)) {
                    throw new \InvalidArgumentException(sprintf('Invalid queue-name [%s]. Predefined queue-name: (%s)', $queueName, implode(', ', $defaultQueueServices)));
                }
                $queueOption['worker'] = preg_replace('/^@/', '', $queueOption['worker']);
                if ($container->has($queueOption['worker'])) {
                    $callable = new Reference($queueOption['worker']);
                } elseif (class_exists($queueOption['worker'])) {
                    $callable = new Definition($queueOption['worker']);
                } else {
                    throw new \InvalidArgumentException(sprintf('Invalid worker of queue [%s]', $queueName));
                }
                $queueDefinition = new Definition(BaseQueue::class);
                $queueDefinition->setFactory([new Reference('tritran.sqs_queue.queue_factory'), 'create'])->setPublic(true)->setArguments([$queueName, $queueOption['queue_url'], $callable, ['DelaySeconds' => call_user_func(function ($v1, $v2) {
                    return isset($v1) ? $v1 : $v2;
                }, @$queueOption['attributes']['delay_seconds'], @0), 'MaximumMessageSize' => call_user_func(function ($v1, $v2) {
                    return isset($v1) ? $v1 : $v2;
                }, @$queueOption['attributes']['maximum_message_size'], @262144), 'MessageRetentionPeriod' => call_user_func(function ($v1, $v2) {
                    return isset($v1) ? $v1 : $v2;
                }, @$queueOption['attributes']['message_retention_period'], @345600), 'ReceiveMessageWaitTimeSeconds' => call_user_func(function ($v1, $v2) {
                    return isset($v1) ? $v1 : $v2;
                }, @$queueOption['attributes']['receive_message_wait_time_seconds'], @20), 'VisibilityTimeout' => call_user_func(function ($v1, $v2) {
                    return isset($v1) ? $v1 : $v2;
                }, @$queueOption['attributes']['visibility_timeout'], @30), 'RedrivePolicy' => !empty($queueOption['attributes']['redrive_policy']['dead_letter_queue']) ? json_encode(['deadLetterTargetArn' => call_user_func(function ($v1, $v2) {
                    return isset($v1) ? $v1 : $v2;
                }, @$queueOption['attributes']['redrive_policy']['dead_letter_queue'], @''), 'maxReceiveCount' => call_user_func(function ($v1, $v2) {
                    return isset($v1) ? $v1 : $v2;
                }, @$queueOption['attributes']['redrive_policy']['max_receive_count'], @5)]) : '', 'ContentBasedDeduplication' => call_user_func(function ($v1, $v2) {
                    return isset($v1) ? $v1 : $v2;
                }, @$queueOption['attributes']['content_based_deduplication'], @false)]]);
                $queueId = sprintf('tritran.sqs_queue.%s', $queueName);
                $container->setDefinition($queueId, $queueDefinition);
            }
        }
    }
}