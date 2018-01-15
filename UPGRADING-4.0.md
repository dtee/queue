# 4.0

## Configuration changes:

### 3.0 (old, deprecated)
```yaml
dtc_queue:
   document_manager: default
   entity_manager: default
   default_manager: odm
   run_manager: orm
   job_timing_manager: orm
   record_timings: true
   record_timings_timezone_offset: -8
   # ...
   class_job: ...
   class_job_archive: ...
   priority_max: ...
   priority_direction: ...
```

### 4.0 (new)
```yaml
dtc_queue:
   # ...
   orm:
      entity_manager: default
   odm:
      default_manager: default
   manager:
      job: odm
      run: orm
      job_timing: orm
   timings:
      record: true
      timezone_offset: -8
   class:
       job: ...
       job_archive: ...
       # etc.
   priority:
       max: ...
       direction: ...
```

## ORM
_(Mysql, etc.)_

Job tables will need a migration or table update as several fields have been renamed

   * **RENAMES**
      * error_count -> exceptions
      * stalled_count -> stalls
      * max_stalled -> max_stalls
      * max_errors -> max_exceptions
   * **NEW**
      * failures
      * max_failures
      * when_us
   * **DELETED**
      * locked
      * locked_at
      * when_at

## Worker updates
   * **Important**: Remove all $this->jobClass = // some class
   * Replace any $this->jobClass or $this->getJobClass() function calls with:
      * $this->getJobManager()->getJobClass()
   * Replace any $this->jobManager direct access calls with:
      * $this->getJobManager()

## Refactoring
   * If you extended JobManager, please note the following
      * Base classes have been moved to their own "Manager" folder
      * resetErroneousJobs -> resetExceptionJobs
   * If you extended any of the Job classes in Model, please note the following
      * locked and lockedAt have been removed (as they were redundant)
      * stallCount and maxStalled are now in StallableJob (used to be stalledCount / maxStalled)
      * errorCount and maxErrors are renamed to exceptions and maxExceptions and are now in RetryableJob
