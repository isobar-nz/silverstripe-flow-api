<?php


namespace Isobar\Flow\Tasks\Services;

use Exception;
use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Services\FlowAPIConnector;
use Isobar\Flow\Services\FlowStatus;
use Isobar\Flow\Model\CompletedTask;
use Isobar\Flow\Model\ScheduledWineProduct;
use Isobar\Flow\Model\ScheduledWineVariation;
use Isobar\Flow\Services\Product\ProductAPIService;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ValidationException;

/**
 * Class ProductImport
 * @package App\Flow
 * @co-author Lauren Hodgson <lauren.hodgson@littlegiant.co.nz>
 */
class ProductImport
{

    /**
     * Imports data from Flow XML feed
     *
     * @return CompletedTask
     *
     * @throws FlowException
     */
    public function runImport()
    {
        if (Director::is_cli()) {
            echo "Started Product Import\n\n";
        }

        // log task on CompletedTasks as started
        $task = CompletedTask::create();
        $count = CompletedTask::get()->count();

        $task->Title = 'Import Product Task ' . ($count + 1);
        $task->Status = FlowStatus::PENDING;

        try {
            $task->write();
        } catch (ValidationException $e) {
            throw new FlowException($e->getMessage(), $e->getCode());
        }

        // import PIMS data to temp table
        try {
            $this->importData($task);
        } catch (Exception $e) {
            throw new FlowException($e->getMessage(), $e->getCode());
        }

        if (Director::is_cli()) {
            echo "\nCompleted Product Import\n\n";
        }

        return $task;
    }

    /**
     * @param CompletedTask $task
     * @throws ValidationException
     * @throws FlowException
     */
    private function importData(CompletedTask $task)
    {
        // Get product data
        $api = ProductAPIService::singleton();

        $connector = singleton(FlowAPIConnector::class);
        $api->setConnector($connector);

        $result = $api->products();

        // Empty result triggers failure
        if (empty($result)) {
            $task->setField('Status', FlowStatus::CANCELLED);

            $task->addError('Data from Flow is empty.');

            $task->write();

            return;
        }

        // Get count
        $task->setField('ProductCount', count($result));

        // truncate scheduledProduct and scheduledProductData table before importing data
        DB::query('TRUNCATE TABLE "App_Flow_Model_ScheduledWineProduct"');
        DB::query('TRUNCATE TABLE "App_Flow_Model_ScheduledWineVariation"');

        // loop through product data and build
        foreach ($result as $product) {
            try {
                $this->importProductData($product, $task);
            } catch (ValidationException $e) {
                $task->Status = FlowStatus::FAILED;

                $task->addError($e->getMessage());

                $task->write();
                throw new FlowException($e->getMessage(), $e->getCode());
            }
        }

        if ($task->Status != FlowStatus::FAILED) {
            $task->Status = FlowStatus::PENDING;
        }
        $task->write();
    }

    /**
     * @param array $product
     * @param CompletedTask $task
     * @throws ValidationException
     */
    public function importProductData(array $product, CompletedTask $task)
    {
        // Find or make forecast group
        if (Director::is_cli()) {
            echo "Importing product " . $product['productCode'] . "\n";
        }

        // Find or make forecast group
        /** @var ScheduledWineProduct $scheduledProduct */
        $scheduledProduct = ScheduledWineProduct::get()->filter([
            'ForecastGroup' => $product['forecastGroup']
        ])->first();

        if (!$scheduledProduct) {
            $title = preg_replace('!\s+!', ' ', $product['productDescription']);

            $scheduledProduct = ScheduledWineProduct::create([
                'ForecastGroup' => $product['forecastGroup'],
                'Description'   => $title
            ]);

            $scheduledProduct->write();
        }

        // Check to see if this is a non-vintage option
        if ($product['vintage'] != 'NVIN') {

            // Import variations
            $scheduledVariation = ScheduledWineVariation::create([
                'Title'         => $product['vintage'],
                'SKU'           => $product['productCode'],
                'ForecastGroup' => $product['forecastGroup'],
                'VariationType' => 'Vintage'

            ]);
        } else {
            // Use the packing size instead
            $scheduledVariation = ScheduledWineVariation::create([
                'Title'         => $product['packingSize'],
                'SKU'           => $product['productCode'],
                'ForecastGroup' => $product['forecastGroup'],
                'VariationType' => 'Pack'
            ]);
        }

        $scheduledVariation->write();

        $scheduledProduct->ScheduledVariations()->add($scheduledVariation);

        $scheduledProduct->write();
    }
}
