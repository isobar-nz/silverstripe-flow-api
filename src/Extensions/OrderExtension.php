<?php
declare(strict_types=1);

namespace Isobar\Flow\Extensions;

use DOMDocument;
use Exception;
use Isobar\Flow\Config\FlowConfig;
use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Model\ScheduledOrder;
use Isobar\Flow\Services\FlowStatus;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\i18n\i18n;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;
use SimpleXMLElement;
use SwipeStripe\Common\Product\ComplexProduct\ComplexProductVariation;
use SwipeStripe\Coupons\Order\OrderCouponAddOn;
use SwipeStripe\Order\Order;
use SwipeStripe\Order\OrderItem\OrderItem;
use SwipeStripe\Order\Status\OrderStatus;
use SwipeStripe\Order\Status\OrderStatusUpdate;
use SwipeStripe\Shipping\Order\ShippingAddOn;
use SwipeStripe\Shipping\ShippingRegion;

/**
 * Class OrderExtension
 *
 * @package App\Flow\Order
 * @property Order|OrderExtension $owner
 * @property boolean              $SentToFlow
 * @property boolean              $Scheduled
 */
class OrderExtension extends DataExtension
{
    private static $db = [
        'SentToFlow'    => DBBoolean::class,
        'Scheduled'     => DBBoolean::class,
        'FlowReference' => DBVarchar::class
    ];

    public function updateCMSFields(FieldList $fields)
    {
//         Add an example to the Flow tab
        $fields->addFieldsToTab('Root.Flow', [
            TextField::create('FlowReference', 'Flow Order Reference'),
            CheckboxField::create('SentToFlow', 'Order has been sent to flow'),
            CheckboxField::create('Scheduled', 'Order has been scheduled for import')
        ]);

        if (!Director::isLive()) {
            $fields->addFieldsToTab('Root.Flow', [
                TextareaField::create('XMLData', 'XML')
                    ->setValue(mb_convert_encoding($this->formatDataForFlow(), 'UTF8'))
                    ->setDescription('Note: Internal coding is UTF-16, converted to UTF-8 for CMS preview')
                    ->setRows(20)
            ]);
        }
    }

    /**
     * Send order data to Flow
     * @throws FlowException
     */
    public function paymentCaptured()
    {
        $this->scheduleOrder();
    }

    /**
     * @throws FlowException
     */
    public function scheduleOrder()
    {
        if (!$this->owner->Scheduled) {
            $xml = $this->formatDataForFlow();

            // If it is false, we don't want to continue scheduling
            if ($xml === false) {
                // Add a status update
                $statusUpdateData = [
                    'NotifyCustomer'  => 0,
                    'CustomerVisible' => 0,
                    'Message'         => 'Order does not contain any Flow products: cancelling scheduled order.',
                    'Status'          => OrderStatus::COMPLETED
                ];

                $update = OrderStatusUpdate::create($statusUpdateData);

                try {
                    $update->write();
                } catch (ValidationException $e) {
                    throw new FlowException($e->getMessage(), $e->getCode());
                }

                $this->owner->OrderStatusUpdates()->add($update);

                $this->owner->setField('Scheduled', 1);
                $this->owner->setField('Status', OrderStatus::COMPLETED);
            } else {
                // Create scheduled order object
                $scheduledOrder = ScheduledOrder::create();
                $scheduledOrder->update([
                    'OrderID' => $this->owner->ID,
                    'Active'  => 1,
                    'Status'  => FlowStatus::PENDING
                ]);

                try {
                    $scheduledOrder->write();
                } catch (ValidationException $e) {
                    throw new FlowException($e->getMessage(), $e->getCode());
                }

                $this->owner->setField('Scheduled', 1);
            }

            try {
                $this->owner->write();
            } catch (ValidationException $e) {
                throw new FlowException($e->getMessage(), $e->getCode());
            }
        }
    }

    /**
     * @param array $fields
     */
    public function updateSummaryFields(&$fields)
    {
        unset($fields['Title']);

        $fields = ['FlowTitle' => 'Order Reference'] + $fields;
    }

    public function FlowTitle()
    {
        return $this->getFlowTitle();
    }

    /**
     * @inheritDoc
     */
    public function getFlowTitle()
    {
        $ref = $this->owner->getField('FlowReference') ?: $this->owner->ID;

        return _t(self::class . '.FlowTitle', '{name} #{id}', [
            'name' => $this->owner->i18n_singular_name(),
            'id'   => $ref,
        ]);
    }

    /**
     * @return SimpleXMLElement|boolean
     * @throws FlowException
     */
    public function formatDataForFlow()
    {
        // Get specific region data
        /** @var ShippingRegion $billingAddressRegionObject */
        $billingAddressRegionObject = ShippingRegion::get()->byID($this->owner->BillingAddressRegion);

        $billingAddressRegion = $billingAddressRegionObject ? $billingAddressRegionObject->Title : '';

        /** @var ShippingRegion $shippingAddressRegionObject */
        $shippingAddressRegionObject = ShippingRegion::get()->byID($this->owner->ShippingAddressRegion);

        $shippingAddressRegion = $shippingAddressRegionObject ? $shippingAddressRegionObject->Title : '';

        // Get appropriate country data
        $countryList = i18n::getData()->getCountries();

        // If the full title is available use that
        $billingCountry = array_key_exists($this->owner->BillingAddressCountry, $countryList)
            ? $countryList[$this->owner->BillingAddressCountry]
            : $this->owner->BillingAddressCountry;

        $shippingCountry = array_key_exists($this->owner->ShippingAddressCountry, $countryList)
            ? $countryList[$this->owner->ShippingAddressCountry]
            : $this->owner->ShippingAddressCountry;

        // Initial data: all fields are required
        // Fields must be in the correct order
        $data = [
            'OrderNo'     => $this->owner->FlowReference,
            'OrderDate'   => $this->owner->dbObject('ConfirmationTime')->Format('Y-MM-dd'),
            'WebDebtorNo' => FlowConfig::config()->get('web_debtor_code'),
            'CustomerNo'  => $this->owner->CustomerEmail,

            'SubTotalPrice' => $this->owner->SubTotal()->getDecimalValue(),
            'TotalPrice'    => $this->owner->Total()->getDecimalValue(),
            'PriceCurrency' => $this->owner->Total()->getCurrencyCode(),

            'ShippingCostsName'  => '',
            'ShippingCostsValue' => 0.00,
            'ShippingCostsType'  => '',

            'CouponCostsName'  => '',
            'CouponCostsValue' => 0.00,
            'CouponCostsType'  => '',

            'CustomerFirstName' => $this->owner->CustomerName,
            'CustomerLastName'  => '',
            'CustomerEmail'     => $this->owner->CustomerEmail,
            'CustomerPhone'     => $this->owner->Phone,

            'PaymentMethod'        => '',
            'PaymentTransactionId' => '',
            'PaymentAmount'        => 0.00,
            'PaymentCurrency'      => '',
            'PaymentToken'         => '',

            // Shipping
            'ShipFirstName'        => $this->owner->CustomerName,
            'ShipSurname'          => '',
            'ShipCompany'          => '',
            'ShipPhone'            => $this->owner->Phone,
            'ShipAddress'          => $this->owner->ShippingAddressUnit . ' ' . $this->owner->ShippingAddressStreet,
            'ShipAddress2'         => $this->owner->ShippingAddressSuburb,
            'ShipCity'             => $this->owner->ShippingAddressCity,
            'ShipPostcode'         => $this->owner->ShippingAddressPostcode,
            'ShipState'            => $shippingAddressRegion,
            'ShipCountry'          => $shippingCountry,
            'ShipNotes'            => str_replace(["\n", "\r", '’', '>', '<'], ['', '', "'", '', ''], $this->owner->ShippingAddressNotes),

//            // Billing
            'BillFirstName'        => $this->owner->CustomerName,
            'BillSurname'          => '',
            'BillCompany'          => '',
            'BillPhone'            => $this->owner->Phone,
            'BillAddress'          => $this->owner->BillingAddressUnit . ' ' . $this->owner->BillingAddressStreet,
            'BillAddress2'         => $this->owner->BillingAddressSuburb,
            'BillCity'             => $this->owner->BillingAddressCity,
            'BillPostcode'         => $this->owner->BillingAddressPostcode,
            'BillState'            => $billingAddressRegion,
            'BillCountry'          => $billingCountry,
            'BillNotes'            => $this->owner->BillingAddressNotes,
        ];

        // Coupon
        $couponAddOns = $this->owner->OrderCouponAddOns();

        /** @var OrderCouponAddOn $couponAddOn */
        foreach ($couponAddOns as $couponAddOn) {
            // Coupons are passed in single amounts; there should only be one but provision for multiple
            $data['CouponCostsName'] = $couponAddOn->Coupon()->Code;
            $data['CouponCostsValue'] = $couponAddOn->getAmount()->getDecimalValue();
            $data['CouponCostsType'] = $couponAddOn->getTitle();
        }

        // Order add-ons - shipping
        /** @var ShippingAddOn $shippingAddOn */
        /** @noinspection PhpUndefinedMethodInspection */
        $shippingAddOn = $this->owner->getShippingAddOn();

        if ($shippingAddOn && $shippingAddOn->exists()) {
            $data['ShippingCostsName'] = $shippingAddOn->getTitle();
            $data['ShippingCostsValue'] = $shippingAddOn->getAmount()->getDecimalValue();
            $data['ShippingCostsType'] = $shippingAddOn->ShippingZone()->getTitle();
        }

        // Member functions
        if ($this->owner->MemberID) {
            /** @var Member $member */
            $member = $this->owner->Member();

            $data['CustomerNo'] = $member->ID;
            $data['CustomerEmail'] = $member->Email;
            $data['CustomerFirstName'] = $member->FirstName;
            $data['CustomerLastName'] = $member->Surname;

            // Shipping
            $data['ShipFirstName'] = $member->FirstName;
            $data['ShipSurname'] = $member->Surname;

            // Billing
            $data['BillFirstName'] = $member->FirstName;
            $data['BillSurname'] = $member->Surname;
        }


        // Payment details
        $payments = $this->owner->Payments();

        if ($payments && $payments->exists()) {

            /** @var Payment $payment */
            foreach ($payments as $payment) {
                if ($payment->isComplete()) {
                    $data['PaymentMethod'] = $payment->getGatewayTitle();
                    $data['PaymentTransactionId'] = $payment->TransactionReference;
                    $data['PaymentAmount'] = $payment->getAmount();
                    $data['PaymentCurrency'] = $payment->getCurrency();
                }
            }
        }

        // Build up the order
        $xmlDocument = new DOMDocument();
        $xmlDocument->version = '1.0';
        $xmlDocument->encoding = 'UTF-16';
        $xmlOrder = $xmlDocument->createElement('orderHeader');

        array_walk($data, function (&$value, &$key) use ($xmlOrder, $xmlDocument) {
            if (is_numeric($value) && $value < 0) {
                // Ensure negative values are parsed correctly
                $value = abs($value);
            }
            $orderPropertyValue = $xmlDocument->createTextNode((string)$value);
            $orderProperty = $xmlDocument->createElement($key);
            $orderProperty->appendChild($orderPropertyValue);
            $xmlOrder->appendChild($orderProperty);
        });

        // Look through products
        $orderItems = $this->owner->OrderItems();

        $validOrderItems = false;

        /** @var OrderItem $orderItem */
        foreach ($orderItems as $orderItem) {
            $product = $orderItem->Purchasable();
            $sku = '';
            $quantity = $orderItem->Quantity;
            $price = $orderItem->getBasePrice()->getDecimalValue();

            // Get the SKU
            if ($product instanceof ComplexProductVariation) {
                $sku = $product->SKU ? $product->SKU : $product->Product()->ForecastGroup;

                // Change to live ProductAttributeOptions rather than the archived one as
                // there are issues with lastEdited date on versions.
                $currStage = Versioned::get_stage();
                Versioned::set_stage(Versioned::LIVE);

                $option = $product->ProductAttributeOptions()->filter([
                    'ProductAttribute.Title' => 'Pack'
                ])->exclude([
                    'Title' => '1'
                ])->first();

                Versioned::set_stage($currStage);

                // Additionally, if this is a Pack, change the quantity to match the number of bottles
                if ($option) {
                    // Try and get the number of items, as an int
                    if (is_numeric($option->Title)) {
                        $packSize = (int)$option->Title;
                        // A pack of 6 should pass through 6 bottles
                        // And the unit price should be for 1 bottle
                        if ($packSize > 1) {
                            $quantity = $orderItem->Quantity * $packSize;
                            $price = $price / $packSize;
                        }
                    }
                }

            } elseif ($product->ForecastGroup) {
                $sku = $product->ForecastGroup;
            }

            if ($sku) {
                // Order lines
                $orderLine = $xmlDocument->createElement('orderLines');

                try {
                    $orderLine->appendChild($xmlDocument->createElement('ProductCode', $sku));
                    $orderLine->appendChild($xmlDocument->createElement('Quantity', (string)$quantity));
                    $orderLine->appendChild($xmlDocument->createElement('Price', (string)$price));
                    $xmlOrder->appendChild($orderLine);
                    $validOrderItems = true;
                } catch (Exception $e) {
                    throw new FlowException($e->getMessage(), $e->getCode());
                }
            } elseif (method_exists($product, 'getFlowIdentifier')) {
                // Assume this is an event / ticket
                $eventLine = $xmlDocument->createElement('EventLines');

                // check if event type is available
                $identifier = $product->getFlowIdentifier();

                $eventLine->appendChild($xmlDocument->createElement('EventCategory', $identifier));
                $eventLine->appendChild($xmlDocument->createElement('EventName', $product->Title));
                $eventLine->appendChild($xmlDocument->createElement('Quantity', (string)$orderItem->Quantity));
                $eventLine->appendChild($xmlDocument->createElement('Price', (string)$orderItem->getBasePrice()->getDecimalValue()));
                $xmlOrder->appendChild($eventLine);
                $validOrderItems = true;
            }
        }
        $xmlDocument->appendChild($xmlOrder);

        // Extend here to add additional XML
        $this->owner->extend('updateFlowOrderXML', $xmlOrder, $validOrderItems);

        // If there are no valid entries, return false
        if ($validOrderItems === false) {
            return false;
        }

        return $xmlDocument->saveXML();
    }
}
