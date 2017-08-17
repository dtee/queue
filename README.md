DtcQueueBundle
==============

[![Build Status](https://secure.travis-ci.org/mmucklo/DtcQueueBundle.png?branch=master)](http://travis-ci.org/mmucklo/DtcQueueBundle)

> Allow symfony developers to create background job as easily as: `$worker->later()->process(1,2,3)`

This bundle provides a way to queue long running jobs that should be
run in backend.

- Turn any long running code into background task with a few lines
- Add workers to your application with very little effort
- Atomic operation for jobs
- Logs errors from worker
- Command to run and debug jobs from console
- Works with GridBundle to provide queue management
- Well tested code: 15 tests, 74 assertions (plus 5 tests and 16 assertions more each service)


Supports
--------

- MongoDB via Doctrine-ODM
- Beanstalkd via pheanstalk
- RabbitMQ via php-amqplib

Installation
------------

Install via composer:

    composer require mmucklo/queue-bundle

Then add the bundle to AppKernel.php:

    class AppKernel extends Kernel
    {
        public function registerBundles()
        {
            $bundles = [
                        //...
                        new \Dtc\GridBundle\DtcGridBundle(),
                        new \Dtc\QueueBundle\DtcQueueBundle(),
   // ...
   
Add MongoDB ODM setting for Job Document.

	doctrine_mongodb:
	    document_managers:
	        default:
	            mappings:
	                DtcQueueBundle:
	                    dir: Documents/
	                    type: annotation

Usage
-----

Create a worker class that will work on the background job.

	class FibonacciWorker
	    extends \Dtc\QueueBundle\Model\Worker
	{
	    private $filename;
	    public function __construct() {
	        $this->filename = '/tmp/fib-result.txt';
	        $this->jobClass = 'Dtc\QueueBundle\Model\Job';
	    }

	    public function fibonacciFile($n) {
	        $feb = $this->fibonacci($n);
	        file_put_contents($this->filename, "{$n}: {$feb}");
	    }


	    public function fibonacci($n)
	    {
	        if($n == 0)
	            return 0; //F0
	        elseif ($n == 1)
	            return 1; //F1
	        else
	            return $this->fibonacci($n - 1) + $this->fibonacci($n - 2);
	    }

	    public function getName() {
	        return 'fibonacci';
	    }

	    public function getFilename()
	    {
	        return $this->filename;
	    }
	}


Create a DI service for the job, and tag it as a background worker.

XML:
	<service id="fibonacci_worker" class="FibonacciWorker">
	    <tag name="dtc_queue.worker" />
	</service>
	
YAML:

	fibonacci_worker:
	    class: FibonacciWorker
	    tags:
	        - { name: "dtc_queue.worker" }

Create a background job.

	//$fibonacciWorker->later()->fibonacci(20);
	//$fibonacciWorker->later()->fibonacciFile(20);
	//$fibonacciWorker->batchLater()->fibonacci(20); // Batch up runs into a single run
	$fibonacciWorker->later(90)->fibonacci(20); // Run 90 seconds later

Run the job

    bin/console dtc:queue_worker:run -t 100


To Debug message queue status.

	bin/console dtc:queue_worker:count
	bin/console dtc:queue_worker:run --id={jobId}

jobId could be obtained from mongodb.

Beanstalk Configuration
------------------------

// config.yml

    dtc_queue:
        beanstalkd:
            host: beanstalkd
            tube: some-tube-name [optional]
        default_manager: beanstalkd

RabbitMQ Configuration
----------------------

// config.yml

    dtc_queue:
        default_manager: rabbit_mq
        rabbit_mq:
            host: rabbitmq
            port: 5672
            user: guest
            password: guest
            vhost: "/" [optional defaults to "/"]
            ssl: [optional defaults to false - toggles to use AMQPSSLConnection]
            options: [optional options to pass to AMQPStreamConnection or AMQPSSLConnection]
            ssl_options: [optional extra ssl options to pass to AMQPSSLConnection]
            queue_args: [optional]
                queue: [optional queue name]
                passive: [optional defaults to false]
                durable: [optional defaults to true]
                exlusive: [optional defaults to false]
                auto_delete: [optional defaults to false]
            exchange_args: [optional]
                exchange: [optional queue name]
                type: [optional defaults to "direct"]
                passive: [optional defaults to false]
                durable: [optional defaults to true]
                auto_delete: [optional defaults to false]

Custom Jobs and Managers
------------------------

// config.yml

    dtc_queue:
         class: Some\Job\ClassName [optional]
         default_manager: some_name [optional]
        # (create your own manager service and name or alias it:
        #   dtc_queue.job_manager.<some_name> and put
        #   <some_name> in the default_manager field above)
 

Job Event Subscriber
--------------------

It's useful to listen to event in a long running script to clear doctrine manger or send email about status of a job. To
add a job event subscriber, create a new service with tag: dtc_queue.event_subscriber:

    services:
        voices.queue.listener.clear_manager:
            class: ClearManagerSubscriber
            arguments:
                - '@service_container'
            tags:
                - { name: dtc_queue.event_subscriber, connection: default }

ClearManagerSubscriber.php

    <?php
    use Dtc\QueueBundle\EventDispatcher\Event;
    use Dtc\QueueBundle\EventDispatcher\EventSubscriberInterface;
    use Symfony\Component\DependencyInjection\ContainerInterface;

    class ClearManagerSubscriber
        implements EventSubscriberInterface
    {
        private $container;
        public function __construct(ContainerInterface $container) {
            $this->container = $container;
        }

        public function onPostJob(Event $event)
        {
            $managerIds = [
                'doctrine.odm.mongodb.document_manager',
                'doctrine.orm.default_entity_manager',
                'doctrine.orm.content_entity_manager'
            ];

            foreach ($managerIds as $id) {
                $manager = $this->container->get($id);
                $manager->clear();
            }
        }

        public static function getSubscribedEvents()
        {
            return array(
                Event::POST_JOB => 'onPostJob',
            );
        }
    }


Running as upstart service:
---------------------------

1. Create the following file in /etc/init/. PHP is terrible at memory management
 and garbage collection: to deal with out of memory issues, run 20 jobs at
 a time. (Or a manageable job size)

	# /etc/init/queue.conf

	author "David Tee"
	description "Queue worker service, run 20 jobs at a time, process timeout of 3600"

	respawn
	start on startup

	script
	        /{path to}/console dtc:queue_worker:run -t 20 -v -to 3600>> /var/logs/queue.log 2>&1
	end script

2. Reload config: sudo initctl reload-configuration
3. Start the script: sudo start queue

Admin
-----

You can register admin routes to see queue status. In your routing.yml file, add the following:

	queue:
	    resource: "@DtcQueueBundle/Controller/QueueController.php"
	    prefix:  /queue/
	    type: annotation

Testing
-------

You can run unittest by typing `phpunit` in source folder. If you want to run
integration testing with Mongodb, you need to set up Mongodb server on
localhost and run:

	phpunit Tests/Documents/JobManagerTest.php

If you want to run Beanstalkd integration testing, you need to run a local
empty instance of beanstalkd for testing.

	sudo service beanstalkd restart; phpunit Tests/BeanStalkd/JobManagerTest.php


License
-------

This bundle is under the MIT license (see LICENSE file under [Resources/meta/LICENSE](Resources/meta/LICENSE)).

Credit
--------
Originally written by @dtee

