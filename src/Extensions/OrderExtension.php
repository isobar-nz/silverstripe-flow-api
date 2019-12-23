<?php
declare(strict_types=1);

namespace Isobar\Flow\Extensions;

use Exception;
use Isobar\Flow\Exception\FlowException;
use Isobar\Flow\Services\FlowStatus;
use Isobar\Flow\Model\ScheduledOrder;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\i18n\i18n;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
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
 * @property boolean $SentToFlow
 * @property boolean $Scheduled
 */
class OrderExtension extends DataExtension
{
    private static $db = [
        'SentToFlow' => DBBoolean::class,
        'Scheduled'  => DBBoolean::class
    ];


    public function updateCMSFields(FieldList $fields)
    {
//         Add an example to the Flow tab
        $fields->addFieldsToTab('Root.Flow', [
            CheckboxField::create('SentToFlow', 'Order has been sent to flow'),
            CheckboxField::create('Scheduled', 'Order has been scheduled for import'),
        ]);


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
                $scheduledOrder = ScheduledOrder::create([
                    'OrderID' => $this->owner->ID,
                    'Active'  => 1,
                    'Status'  => FlowStatus::PENDING,
                    'XmlData' => $xml
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
            'OrderNo'     => $this->owner->ID,
            'OrderDate'   => $this->owner->dbObject('ConfirmationTime')->Format('Y-MM-dd'),
            'WebDebtorNo' => 'VMNZWEB',
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
            'ShipNotes'            => $this->owner->ShippingAddressNotes,
//
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

//            'orderLines' => ''
        ];

        // Coupon
        $couponAddOns = $this->owner->OrderCouponAddOns();

        /** @var OrderCouponAddOn $couponAddOn */
        foreach ($couponAddOns as $couponAddOn) {
            // Coupons are passed in single amounts; there should only be one but provision for multiple
            $data['CouponCostsName']  = $couponAddOn->Coupon()->Code;
            $data['CouponCostsValue'] = $couponAddOn->getAmount()->getDecimalValue();
            $data['CouponCostsType']  = $couponAddOn->getTitle();
        }

        // Order add-ons - shipping
        /** @var ShippingAddOn $shippingAddOn */
        /** @noinspection PhpUndefinedMethodInspection */
        $shippingAddOn = $this->owner->getShippingAddOn();

        if ($shippingAddOn && $shippingAddOn->exists()) {
            $data['ShippingCostsName']  = $shippingAddOn->getTitle();
            $data['ShippingCostsValue'] = $shippingAddOn->getAmount()->getDecimalValue();
            $data['ShippingCostsType']  = $shippingAddOn->ShippingZone()->getTitle();
        }

        // Member functions
        if ($this->owner->MemberID) {
            /** @var Member $member */
            $member = $this->owner->Member();

            $data['CustomerNo']        = $member->ID;
            $data['CustomerEmail']     = $member->Email;
            $data['CustomerFirstName'] = $member->FirstName;
            $data['CustomerLastName']  = $member->Surname;

            // Shipping
            $data['ShipFirstName'] = $member->FirstName;
            $data['ShipSurname']   = $member->Surname;

            // Billing
            $data['BillFirstName'] = $member->FirstName;
            $data['BillSurname']   = $member->Surname;
        }


        // Payment details
        $payments = $this->owner->Payments();

        if ($payments && $payments->exists()) {

            /** @var Payment $payment */
            foreach ($payments as $payment) {
                if ($payment->isComplete()) {
                    $data['PaymentMethod']        = htmlspecialchars($payment->getGatewayTitle());
                    $data['PaymentTransactionId'] = htmlspecialchars($payment->TransactionReference);
                    $data['PaymentAmount']        = $payment->getAmount();
                    $data['PaymentCurrency']      = $payment->getCurrency();
                }
            }
//            'PaymentMethod' => '',
//            'PaymentTransactionId' => '',
//            'PaymentAmount' => '',
//            'PaymentCurrency' => '',
//            'PaymentToken' => '',
        }

        // Build up the order

        $xmlOrder = new SimpleXMLElement("<orderHeader/>");

        array_walk($data, function (&$value, &$key) use ($xmlOrder) {
            if (is_numeric($value)) {
                // Ensure negative values are parsed correctly
                $value = abs($value);
            }

            $xmlOrder->addChild($key, htmlspecialchars((string)$value));
        });

        // Look through products
        $orderItems = $this->owner->OrderItems();

        $validOrderItems = false;

        /** @var OrderItem $orderItem */
        foreach ($orderItems as $orderItem) {
            // Get SKU or forecast group
            $variation = ComplexProductVariation::get()->byID($orderItem->PurchasableID);

            if ($variation && $variation->exists()) {
                // Only add if it has an SKU
                if ($variation->SKU) {
                    $validOrderItems = true;

                    // Order lines
                    $orderLine = $xmlOrder->addChild('orderLines');

                    $orderLine->addChild('ProductCode', $variation->SKU);
                    $orderLine->addChild('Quantity', (string)$orderItem->Quantity);
                    try {
                        $orderLine->addChild('Price', (string)$orderItem->getBasePrice()->getDecimalValue());
                    } catch (Exception $e) {
                        throw new FlowException($e->getMessage(), $e->getCode());
                    }
                }


            } else {
                $sku = $orderItem->Purchasable()->ForecastGroup ?: $orderItem->Purchasable()->SKU;

                if ($sku) {
                    $validOrderItems = true;

                    // Order lines
                    $orderLine = $xmlOrder->addChild('orderLines');

                    try {
                        $orderLine->addChild('ProductCode', $sku);
                        $orderLine->addChild('Quantity', (string)$orderItem->Quantity);
                        $orderLine->addChild('Price', (string)$orderItem->getBasePrice()->getDecimalValue());
                    } catch (Exception $e) {
                        throw new FlowException($e->getMessage(), $e->getCode());
                    }
                }
            }
        }

        // If there are no valid entries, return false
        if ($validOrderItems === false) {
            return false;
        }

        return $xmlOrder->asXML();
    }
}
