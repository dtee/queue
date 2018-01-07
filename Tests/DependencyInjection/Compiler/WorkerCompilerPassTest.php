<?php

namespace Dtc\QueueBundle\Tests\DependencyInjection\Compiler;

use Dtc\GridBundle\Manager\GridSourceManager;
use Dtc\QueueBundle\DependencyInjection\Compiler\WorkerCompilerPass;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Manager\WorkerManager;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\ODM\JobTimingManager;
use Dtc\QueueBundle\ODM\LiveJobsGridSource;
use Dtc\QueueBundle\ODM\RunManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class WorkerCompilerPassTest extends TestCase
{
    protected function getBaseContainer($type = 'odm', $runManagerType = 'odm', $jobTimingManagerType = 'odm')
    {
        $container = new ContainerBuilder();

        $count = count($container->getDefinitions());
        $compilerPass = new WorkerCompilerPass();
        $compilerPass->process($container);
        self::assertEquals($count, count($container->getDefinitions()));

        $container = new ContainerBuilder();
        $definition1 = new Definition();
        $definition1->setClass(WorkerManager::class);
        $container->setParameter('dtc_queue.manager.job', $type);
        $container->setParameter('dtc_queue.manager.run', $runManagerType);
        $container->setParameter('dtc_queue.manager.job_timing', $jobTimingManagerType);
        $container->setParameter('dtc_queue.class.job', null);
        $container->setParameter('dtc_queue.class.job_archive', null);
        $container->setParameter('dtc_queue.odm.document_manager', 'default');
        $container->setParameter('dtc_queue.orm.entity_manager', 'default');
        $definition2 = new Definition();
        $definition2->setClass(JobManager::class);
        $definition3 = new Definition();
        $definition3->setClass(RunManager::class);
        $definition4 = new Definition();
        $definition4->setClass(EventDispatcher::class);
        $definition5 = new Definition();
        $definition5->setClass(JobTimingManager::class);
        $definition6 = new Definition();
        $definition6->setClass(GridSourceManager::class);
        $definition7 = new Definition();
        $definition7->setClass(LiveJobsGridSource::class);
        $definition8 = new Definition();
        $definition8->setClass(LiveJobsGridSource::class);
        $container->addDefinitions([
            'dtc_queue.manager.worker' => $definition1,
            'dtc_queue.manager.job.'.$type => $definition2,
            'dtc_queue.manager.job_timing.'.$jobTimingManagerType => $definition5,
            'dtc_queue.manager.run.'.$runManagerType => $definition3,
            'dtc_queue.event_dispatcher' => $definition4,
            'dtc_grid.manager.source' => $definition6,
            'dtc_queue.grid_source.jobs_waiting.odm' => $definition7,
            'dtc_queue.grid_source.jobs_waiting.orm' => $definition8,
            'dtc_queue.grid_source.jobs_running.odm' => $definition7,
            'dtc_queue.grid_source.jobs_running.orm' => $definition8,
        ]);

        return $container;
    }

    public function testProcess()
    {
        $container = $this->getBaseContainer();
        $count = count($container->getAliases());
        $compilerPass = new WorkerCompilerPass();
        $compilerPass->process($container);

        self::assertNotEquals($count, count($container->getAliases()));
    }

    public function testProcessInvalidWorker()
    {
        $container = $this->getBaseContainer();
        $definition5 = new Definition();
        $definition5->setClass(JobManager::class);
        $definition5->addTag('dtc_queue.worker');
        $container->addDefinitions(['some.worker' => $definition5]);

        $count = count($container->getAliases());
        $compilerPass = new WorkerCompilerPass();
        $failed = false;
        try {
            $compilerPass->process($container);
            $failed = true;
        } catch (\Exception $e) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);
        self::assertNotEquals($count, count($container->getAliases()));
    }

    public function testProcessValidWorker()
    {
        $this->runProcessValidWorker('odm');
        $this->runProcessValidWorker('odm', 'orm', 'orm');
        $this->runProcessValidWorker('orm');
        $this->runProcessValidWorker('orm', 'odm', 'orm');
        $this->runProcessValidWorker('beanstalkd');
        $this->runProcessValidWorker('rabbit_mq');
        $this->runProcessValidWorker('redis');
    }

    public function testBadManagerType()
    {
        $failure = false;
        try {
            $this->runProcessValidWorker('bad');
        } catch (\Exception $e) {
            $failure = true;
        }
        self::assertTrue($failure);
    }

    public function runProcessValidWorker($type, $runManagerType = 'odm', $jobTimingManagerType = 'odm')
    {
        $container = $this->getBaseContainer($type, $runManagerType, $jobTimingManagerType);
        $definition5 = new Definition();
        $definition5->setClass(FibonacciWorker::class);
        $definition5->addTag('dtc_queue.worker');
        $container->addDefinitions(['some.worker' => $definition5]);

        $count = count($container->getAliases());
        $compilerPass = new WorkerCompilerPass();
        $compilerPass->process($container);
        self::assertNotEquals($count, count($container->getAliases()));
    }
}
