<?php

namespace Isobar\Flow\Tasks;

use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Tasks\Services\StockImport;

/**
 * Class StockImportTask
 *
 * Responsible for scheduling the import of products
 *
 * @package App\Flow\Tasks
 * @co-author Lauren Hodgson <lauren.hodgson@littlegiant.co.nz>
 */
class StockImportTask extends BaseFlowTask
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
     * @throws FlowException
     */
    public function process()
    {
        // Product import
        $flowService = new StockImport();

        $flowService->runImport();
    }
}
