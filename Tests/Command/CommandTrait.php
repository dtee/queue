<?php

namespace Dtc\QueueBundle\Tests\Command;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Tests\StubJobManager;
use Dtc\QueueBundle\Tests\StubJobTimingManager;
use Dtc\QueueBundle\Tests\StubRunManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CommandTrait
{
    /**
     * @param string $commandClass
     */
    protected function runCommand($commandClass, ContainerInterface $container, array $params)
    {
        $this->runCommandExpect($commandClass, $container, $params, 0);
    }

    /**
     * @param string $commandClass
     */
    private function prepCommand($commandClass, ContainerInterface $container, array $params)
    {
        $command = new $commandClass();
        if (method_exists($command, 'setRunLoop')) {
            $command->setRunLoop($container->get('dtc_queue.run.loop'));
        }
        if (method_exists($command, 'setJobManager')) {
            $command->setJobManager($container->get('dtc_queue.manager.job'));
        }
        if (method_exists($command, 'setWorkerManager')) {
            $command->setWorkerManager($container->get('dtc_queue.manager.worker'));
        }
        if (method_exists($command, 'setRunManager')) {
            $command->setRunManager($container->get('dtc_queue.manager.run'));
        }
        if (method_exists($command, 'setJobTimingManager')) {
            $command->setJobTimingManager($container->get('dtc_queue.manager.job_timing'));
        }
        $input = new ArrayInput($params);
        $output = new NullOutput();

        return [$command, $input, $output];
    }

    /**
     * @param string $commandClass
     */
    protected function runCommandException($commandClass, ContainerInterface $container, array $params)
    {
        list($command, $input, $output) = $this->prepCommand($commandClass, $container, $params);
        $failed = false;
        try {
            $command->run($input, $output);
            $failed = true;
        } catch (\Exception $exception) {
            TestCase::assertTrue(true);
        }
        TestCase::assertFalse($failed);
    }

    /**
     * @param string $commandClass
     * @param int    $expectedResult
     */
    protected function runCommandExpect($commandClass, ContainerInterface $container, array $params, $expectedResult)
    {
        list($command, $input, $output) = $this->prepCommand($commandClass, $container, $params);
        try {
            $result = $command->run($input, $output);
        } catch (\Exception $exception) {
            TestCase::fail("Shouldn't throw exception: ".get_class($exception).' - '.$exception->getMessage());

            return;
        }
        TestCase::assertEquals($expectedResult, $result);
    }

    protected function runStubCommand($className, $params, $call, $expectedResult = 0)
    {
        $managerType = 'job';
        if (false !== strrpos($call, 'Runs')) {
            $managerType = 'run';
        } elseif (false !== strrpos($call, 'Timings')) {
            $managerType = 'jobTiming';
        }
        $jobTimingManager = new StubJobTimingManager(JobTiming::class, false);
        $runManager = new StubRunManager(\Dtc\QueueBundle\Model\Run::class);
        $jobManager = new StubJobManager($runManager, $jobTimingManager, Job::class);
        $container = new Container();
        $container->set('dtc_queue.manager.job', $jobManager);
        $container->set('dtc_queue.manager.run', $runManager);
        $container->set('dtc_queue.manager.job_timing', $jobTimingManager);
        $this->runCommandExpect($className, $container, $params, $expectedResult);
        $manager = "${managerType}Manager";
        if (0 === $expectedResult) {
            self::assertTrue(isset($$manager->calls[$call][0]));
            self::assertTrue(!isset($$manager->calls[$call][1]));
        }

        return $$manager;
    }
}
