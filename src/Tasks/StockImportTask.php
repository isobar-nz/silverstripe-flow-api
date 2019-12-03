<?php

namespace Isobar\Flow\Tasks;

use Exception;
use Isobar\Flow\Tasks\Services\StockImport;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\NullHTTPRequest;
use SilverStripe\CronTask\Interfaces\CronTask;
use SilverStripe\Dev\BuildTask;

/**
 * Class StockImportTask
 *
 * Responsible for scheduling the import of products
 *
 * @package App\Flow\Tasks
 * @co-author Lauren Hodgson <lauren.hodgson@littlegiant.co.nz>
 */
class StockImportTask extends BuildTask implements CronTask
{
    /**
     * @var string
     */
    private static $segment = 'StockImportTask';

    /**
     * @var string
     */
    protected $title = 'Import Stock Data';

    /**
     * @var string
     */
    protected $description = 'Import and process all stock information';

    /**
     * @return string
     */
    public function getSchedule()
    {
        return "*/30 * * * *"; // Import every 30 minutes
    }

    /**
     * Import handler for cron task
     */
    public function process()
    {
        $this->run(new NullHTTPRequest());
    }

    /**
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', 100000);

        // Product import
        $flowService = new StockImport();

        try {
            $flowService->runImport();
        } catch (Exception $e) {
            echo $e->getMessage();

            return;
        }
    }
}
