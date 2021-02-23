<?php


namespace Isobar\Flow\Tasks\Services;

use Exception;
use Isobar\Flow\Config\FlowConfig;
use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Services\FlowAPIConnector;
use Isobar\Flow\Services\Product\StockAPIService;
use SilverStripe\Control\Director;
use SilverStripe\ORM\ValidationException;
use SwipeStripe\Common\Product\ComplexProduct\ComplexProductVariation;

/**
 * Class StockImport
 * @package App\Flow
 */
class StockImport
{
    use ChangeSetPublishTrait;

    protected $threshold;

    /**
     * Imports data from Flow XML feed
     *
     * @throws FlowException
     * @throws ValidationException
     */
    public function runImport()
    {
        if (Director::is_cli()) {
            echo "Started Stock Import\n\n";
        }

        // import PIMS data to temp table
        try {
            $this->initChangeSet();
            $this->importData();
        } catch (Exception $e) {
            throw new FlowException($e->getMessage(), $e->getCode());
        } finally {
            $this->finishChangeSet();;
        }

        if (Director::is_cli()) {
            echo "\nCompleted Stock Import\n\n";
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function importData()
    {
        $api = StockAPIService::singleton();

        $connector = singleton(FlowAPIConnector::class);
        $api->setConnector($connector);

        $result = $api->products();

        if (empty($result)) {
            if (Director::is_cli()) {
                echo "No data from Flow\n\n";
            }
            return false;
        }

        // Save SOH threshold for easy access
        $threshold = FlowConfig::config()->get('soh_threshold') ?: 6;

        $this->threshold = $threshold;

        // loop through product data and build
        foreach ($result as $stockOnHand) {
            $this->importStockData($stockOnHand);
        }

        return true;
    }

    /**
     * @param array $stockOnHand
     * @throws Exception
     */
    public function importStockData(array $stockOnHand)
    {
        if (empty($stockOnHand)) {
            throw new FlowException('Empty data');
        }

        // Find or make forecast group

        if (Director::is_cli()) {
            echo "Importing stock data for " . $stockOnHand['productCode'] . PHP_EOL;
        }

        // Get the variation for the SKU
        /** @var ComplexProductVariation $productVariation */
        $productVariations = ComplexProductVariation::get()
            ->filter([
                'SKU' => $stockOnHand['productCode']
            ])
            ->exclude(['ProductID' => 0]);

        // Changed to a loop now
        foreach ($productVariations as $productVariation) {
            // only process stock if variation exists
            if ($productVariation && $productVariation->exists()) {
                // Set the new stock
                $newStock = $stockOnHand['stock'];

                if (Director::is_cli()) {
                    echo "Found stock: {$newStock}" . PHP_EOL;
                }

                if ($newStock < $this->threshold) {
                    $productVariation->setField('OutOfStock', 1);

                    if (Director::is_cli()) {
                        echo $stockOnHand['productCode'] . " marked out of stock." . PHP_EOL;
                    }
                } else {
                    $productVariation->setField('OutOfStock', 0);
                }

                $this->publish($productVariation);
            }
        }
    }
}
