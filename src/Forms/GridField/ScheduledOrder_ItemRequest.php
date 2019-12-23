<?php

namespace Isobar\Flow\Forms\GridField;

use Exception;
use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Services\FlowStatus;
use Isobar\Flow\Model\ScheduledOrder;
use Isobar\Flow\Services\FlowAPIConnector;
use Isobar\Flow\Services\Product\OrderAPIService;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\ValidationResult;
use SwipeStripe\Order\Status\OrderStatus;
use SwipeStripe\Order\Status\OrderStatusUpdate;

/**
 * Class ScheduledOrder_ItemRequest
 * @package App\Flow\Admin
 *
 * @property \Isobar\Flow\Model\ScheduledOrder $record
 */
class ScheduledOrder_ItemRequest extends GridFieldDetailForm_ItemRequest
{
    private static $allowed_actions = [
        'edit',
        'view',
        'ItemEditForm'
    ];

    /**
     * @return HTTPResponse|Form
     */
    public function ItemEditForm()
    {
        $editForm = parent::ItemEditForm();

        $editForm->Actions()->push(FormAction::create('doSendToFlow', 'Send to Flow')
            ->setUseButtonTag(true)
            ->addExtraClass('btn-primary')
            ->setReadonly(false));

        if ($this->record->Status == FlowStatus::COMPLETED) {
            $editForm->Actions()->fieldByName('action_doSendToFlow')->setReadonly(true);
        }

        return $editForm;
    }

    /**
     * @param array $data
     * @param Form $form
     * @return HTTPResponse|DBHTMLText
     * @throws ValidationException
     * @throws FlowException
     */
    public function doSendToFlow($data, $form)
    {
        // Save from form data
        $xmlData = $this->record->XmlData;

        if ($xmlData === false) {
            // Do not send invalid data
            $message = 'Order has no valid Flow products. Completed successfully but not sent to Flow.';
        } else {

            try {
                $api = OrderAPIService::singleton();

                $connector = singleton(FlowAPIConnector::class);
                $api->setConnector($connector);

                $result = $api->order($xmlData);

                $message = 'Sent order to Flow.';
            } catch (Exception $e) {
                $form->sessionMessage('An error occurred: ' . $e->getMessage(), ValidationResult::TYPE_ERROR, ValidationResult::CAST_HTML);
                throw new FlowException($e->getMessage(), $e->getCode());
            }
        }

        $this->record->setField('Status', FlowStatus::COMPLETED);
        $this->record->write();

        // Save order record
        $statusUpdateData = [
            'NotifyCustomer'  => 0,
            'CustomerVisible' => 0,
            'Message'         => $message,
            'Status'          => OrderStatus::COMPLETED
        ];

        $order = $this->record->Order();

        if ($order && $order->exists()) {
            // Create status update
            $update = OrderStatusUpdate::create($statusUpdateData);

            $update->write();

            $order->OrderStatusUpdates()->add($update);

            // Mark as sent to flow
            $order->setField('SentToFlow', 1);

            // Ensure order status itself is correct
            $order->setField('Status', $this->record->Status);

            $order->write();
        }

        $form->sessionMessage($message, 'good', ValidationResult::CAST_HTML);

        // Redirect after save
        return $this->redirectAfterSave(false);
    }
}
