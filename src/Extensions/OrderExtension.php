<?php
declare(strict_types=1);

namespace Isobar\Flow\Extensions;

use Exception;
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
        $xml = htmlentities($this->formatDataForFlow());

//         Add an example to the Flow tab
        $fields->addFieldsToTab('Root.Flow', [
            CheckboxField::create('SentToFlow', 'Order has been sent to flow'),
            CheckboxField::create('Scheduled', 'Order has been scheduled for import'),
//            LiteralField::create('XmlData', DBField::create_field(DBHTMLText::class, '<div style="border:1px solid #e7ebf0;padding: 10px; background-color: #f1f3f6">' . $xml . '</div>'))
        ]);


    }

    /**
     * Send order data to Flow
     */
    public function paymentCaptured()
    {
        $this->scheduleOrder();
    }

    /**
     *
     */
    public function scheduleOrder()
    {
        if (!$this->owner->Scheduled) {
            $xml = $this->formatDataForFlow();

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
                error_log($e->getMessage());
            }

            $this->owner->setField('Scheduled', 1);
            try {
                $this->owner->write();
            } catch (ValidationException $e) {
                error_log($e->getMessage());
            }
        }
    }

    public function formatDataForFlow()
    {
        // Get specific region data
        /** @var ShippingRegion $billingAddressRegionObject */
        /** @noinspection PhpUndefinedFieldInspection */
        $billingAddressRegionObject = ShippingRegion::get()->byID($this->owner->BillingAddressRegion);

        $billingAddressRegion = $billingAddressRegionObject ? $billingAddressRegionObject->Title : '';

        /** @var ShippingRegion $shippingAddressRegionObject */
        /** @noinspection PhpUndefinedFieldInspection */
        $shippingAddressRegionObject = ShippingRegion::get()->byID($this->owner->ShippingAddressRegion);

        $shippingAddressRegion = $shippingAddressRegionObject ? $shippingAddressRegionObject->Title : '';

        // Get appropriate country data
        $countryList = i18n::getData()->getCountries();

        // If the full title is available use that
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        $billingCountry = array_key_exists($this->owner->BillingAddressCountry, $countryList)
            ? $countryList[$this->owner->BillingAddressCountry]
            : $this->owner->BillingAddressCountry;

        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        $shippingCountry = array_key_exists($this->owner->ShippingAddressCountry, $countryList)
            ? $countryList[$this->owner->ShippingAddressCountry]
            : $this->owner->ShippingAddressCountry;

        // Initial data: all fields are required
        // Fields must be in the correct order
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUndefinedFieldInspection */
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
        /** @noinspection PhpUndefinedMethodInspection */
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
        /** @noinspection PhpUndefinedFieldInspection */
        if ($this->owner->MemberID) {
            /** @var Member $member */
            /** @noinspection PhpUndefinedMethodInspection */
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
                    $data['PaymentMethod']        = $payment->getGatewayTitle();
                    $data['PaymentTransactionId'] = $payment->TransactionReference;
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
            $xmlOrder->addChild($key, (string)$value);
        });

        // Look through products
        /** @noinspection PhpUndefinedMethodInspection */
        $orderItems = $this->owner->OrderItems();

        /** @var OrderItem $orderItem */
        foreach ($orderItems as $orderItem) {
            // Get SKU or forecast group
            $variation = ComplexProductVariation::get()->byID($orderItem->PurchasableID);

            if ($variation && $variation->exists()) {
                // TODO: Case for purchasing non-variation
//            $sku = $purchaseable instanceof ComplexProductVariation ? $purchaseable->SKU : $purchaseable->ForecastGroup;

                // Order lines
                $orderLine = $xmlOrder->addChild('orderLines');

                $orderLine->addChild('ProductCode', $variation->SKU);
                $orderLine->addChild('Quantity', (string)$orderItem->Quantity);
                try {
                    $orderLine->addChild('Price', (string)$orderItem->getBasePrice()->getDecimalValue());
                } catch (Exception $e) {
                    error_log($e->getMessage());
                }
            } else {
                // Order lines
                $orderLine = $xmlOrder->addChild('orderLines');

                try {
                    $orderLine->addChild('ProductCode', $orderItem->Purchasable()->ForecastGroup ?: $orderItem->Purchasable()->SKU);
                    $orderLine->addChild('Quantity', (string)$orderItem->Quantity);
                    $orderLine->addChild('Price', (string)$orderItem->getBasePrice()->getDecimalValue());
                } catch (Exception $e) {
                    error_log($e->getMessage());
                }

            }
        }

        return $xmlOrder->asXML();
    }
}
