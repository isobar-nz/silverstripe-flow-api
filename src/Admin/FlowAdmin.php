<?php
declare(strict_types=1);

namespace Isobar\Flow\Admin;

use App\Extensions\ExtensionHelper;
use Isobar\Flow\Extensions\FlowLeftAndMainExtension;
use Isobar\Flow\Forms\GridField\CompletedTask_ItemRequest;
use Isobar\Flow\Forms\GridField\GridFieldSyncFlowButton;
use Isobar\Flow\Forms\GridField\ScheduledOrder_ItemRequest;
use Isobar\Flow\Model\CompletedTask;
use Isobar\Flow\Model\ScheduledOrder;
use Isobar\Flow\Model\ScheduledWineProduct;
use Isobar\Flow\Model\ScheduledWineVariation;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDetailForm;

/**
 * Class ShippingAdmin
 *
 * @package SwipeStripe\Shipping
 *
 * @mixin FlowLeftAndMainExtension
 */
class FlowAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $menu_title = 'Flow';

    /**
     * @var string
     */
    private static $url_segment = 'flow';

    /**
     * @var array
     */
    private static $managed_models = [
        CompletedTask::class,
        ScheduledOrder::class,
        ScheduledWineProduct::class,
        ScheduledWineVariation::class
    ];

    private static $extensions = [
        FlowLeftAndMainExtension::class
    ];

    public $showImportForm = false;

    /**
     * @param null $id
     * @param null $fields
     * @return Form
     */
    public function getEditForm($id = null, $fields = null): Form
    {
        $editForm = parent::getEditForm($id, $fields);

        $completedTaskField = $editForm->Fields()->dataFieldByName(ExtensionHelper::sanitiseClassName(CompletedTask::class));

        if ($completedTaskField instanceof GridField) {
            $config = $completedTaskField->getConfig();

            /** @var GridFieldDetailForm $detailForm */
            $detailForm = $config->getComponentByType(GridFieldDetailForm::class);

            $detailForm->setItemRequestClass(CompletedTask_ItemRequest::class);

            $config->addComponent(
                new GridFieldSyncFlowButton('buttons-before-left')
            );
        }

        // Scheduled Order
        $scheduledOrderField = $editForm->Fields()->dataFieldByName(ExtensionHelper::sanitiseClassName(ScheduledOrder::class));

        if ($scheduledOrderField instanceof GridField) {
            $config = $scheduledOrderField->getConfig();

            /** @var GridFieldDetailForm $detailForm */
            $detailForm = $config->getComponentByType(GridFieldDetailForm::class);

            $detailForm->setItemRequestClass(ScheduledOrder_ItemRequest::class);
        }

        return $editForm;
    }
}
