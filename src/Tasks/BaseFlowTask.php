<?php


namespace Isobar\Flow\Tasks;


use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\CronTask\Interfaces\CronTask;
use SilverStripe\Dev\BuildTask;

class BaseFlowTask extends BuildTask implements CronTask
{
    public function __construct()
    {
        parent::__construct();

        Environment::increaseMemoryLimitTo(-1);
        Environment::increaseTimeLimitTo(100000);
    }

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $this->process();
    }

    /**
     * Return a string for a CRON expression. If a "falsy" value is returned, the CronTaskController will assume the
     * CronTask is disabled.
     *
     * @return string
     */
    public function getSchedule()
    {
        return "15 1 * * *"; // Import every night
    }

    /**
     * When this script is supposed to run the CronTaskController will execute
     * process().
     *
     * @return void
     */
    public function process()
    {

    }
}
