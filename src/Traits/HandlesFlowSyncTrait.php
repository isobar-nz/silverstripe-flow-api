<?php


namespace Isobar\Flow\Traits;


use Isobar\Flow\Tasks\ProductImportTask;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

trait HandlesFlowSyncTrait
{
    public abstract function actionComplete($form, $message, $code);

    /**
     * Decorate actions with fluent-specific details
     *
     * @param FieldList            $actions
     * @param DataObject|Versioned $record
     */
    protected function updateFlowActions(FieldList $actions, DataObject $record)
    {
        // Skip if record isn't saved
        if (!$record->isInDB()) {
            return;
        }

        // Make sure the menu isn't going to get cut off
        $actions->insertBefore('RightGroup', FormAction::create('doFlowSync', _t('Isobar\\Flow.SYNC_BUTTON', 'Sync from Flow'))
            ->addExtraClass('btn btn-primary font-icon-sync')
        );
    }

    /**
     * @param array $data
     * @param Form $form
     * @return HTTPResponse
     */
    public function doFlowSync($data, $form)
    {
        ob_start();
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', 100000);

        $message = 'Flow synced.';
        $code = 200;

        // Product
        $productTask = new ProductImportTask();
//
//        try {
//            $productTask->process();
//        } catch (Exception $e) {
//            $message = $e->getMessage();
//            $code = $e->getCode();
//        }
//
//        // Process
//        $productTask = new ProcessProductsTask();
//
//        try {
//            $productTask->process();
//        } catch (Exception $e) {
//            $message = $e->getMessage();
//            $code = $e->getCode();
//        }
//
//        // Stock
//        $stockTask = new StockImportTask();
//
//        try {
//            $stockTask->process();
//        } catch (Exception $e) {
//            $message = $e->getMessage();
//            $code = $e->getCode();
//        }

        // Suppress echo
        ob_end_clean();

        return $this->actionComplete($form, $message, $code);
    }


}
