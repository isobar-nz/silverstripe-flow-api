<?php declare(strict_types=1);


namespace Isobar\Tests\Flow;


use App\Ecommerce\Product\WineProduct;
use Isobar\Flow\Services\FlowStatus;
use Isobar\Flow\Model\CompletedTask;
use Isobar\Flow\Model\ScheduledWineProduct;
use Isobar\Flow\Model\ScheduledWineVariation;
use Isobar\Flow\Order\OrderExtension;
use Isobar\Flow\Services\Connector\BasicAuthConnector;
use Isobar\Flow\Tasks\Services\ProcessProducts;
use Isobar\Flow\Services\Product\OrderAPIService;
use Isobar\Flow\Services\Product\PricingAPIService;
use Isobar\Flow\Services\Product\ProductAPIService;
use Isobar\Flow\Services\Product\StockAPIService;
use App\Pages\ShopWinesPage;
use Isobar\Tests\Flow\Fixtures\Fixtures;
use Money\Money;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophecy\ObjectProphecy;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\ORM\ValidationException;
use SimpleXMLElement;
use SwipeStripe\Common\Product\ComplexProduct\ComplexProduct;
use SwipeStripe\Common\Product\ComplexProduct\ComplexProductVariation;
use SwipeStripe\Common\Product\ComplexProduct\ProductAttribute;
use SwipeStripe\Common\Product\ComplexProduct\ProductAttributeOption;
use SwipeStripe\Order\Cart\ViewCartPage;
use SwipeStripe\Order\Order;
use SwipeStripe\Order\ViewOrderPage;

class FlowApiTest extends BaseTest
{
    /**
     * @var array
     */
    protected static $fixture_file = [
        Fixtures::WINE_PRODUCT,
        Fixtures::SCHEDULED_PRODUCTS,
        Fixtures::BASE_COMMERCE_PAGES
    ];

    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var WineProduct|ComplexProduct $barrique
     */
    protected $barrique;

    /**
     * @var WineProduct|ComplexProduct $vmcalbag
     */
    protected $vmcalbag;

    /**
     * @var Order|OrderExtension
     */
    protected $order;

    public function testGetProducts()
    {
        /** @var ObjectProphecy|ProductAPIService $dupeMock */
        $dupeMock = $this->prophesize(ProductAPIService::class);

        /** @var MethodProphecy $methodMock */
        $methodMock = $dupeMock->products();

        $methodMock->willReturn([
            [
                'forecastGroup'      => 'VMCALBAG',
                'vintage'            => '2017',
                'productCode'        => 'VMCALBAG176Z',
                'productDescription' => 'VM CS Albarino      2017       Gisborne 750mL 6-Pk     NZ',
                'packingSize'        => '6'
            ]
        ]);

        /** @var ProductAPIService $dupeMockRevealed */
        $dupeMockRevealed = $dupeMock->reveal();

        $this->assertEquals(
            [[
                'forecastGroup'      => 'VMCALBAG',
                'vintage'            => '2017',
                'productCode'        => 'VMCALBAG176Z',
                'productDescription' => 'VM CS Albarino      2017       Gisborne 750mL 6-Pk     NZ',
                'packingSize'        => '6'
            ]],
            $dupeMockRevealed->products()
        );

        // Get the products
        /** @var ComplexProductVariation $barrique2017 */
        $barrique2017 = $this->objFromFixture(ComplexProductVariation::class, 'barrique-vintage-2017');

        $this->assertTrue($barrique2017->getBasePrice()->getMoney()->equals(new Money(4999, $this->currency)));
    }

    /**
     *
     */
    public function testProcessProducts()
    {
        /** @var ScheduledWineProduct $scheduledWine */
        $scheduledWine = $this->objFromFixture(ScheduledWineProduct::class, 'vmcalbag');

        $processor = new ProcessProducts();

        try {
            $processor->runProcessData();
        } catch (ValidationException $e) {
            $this->fail($e->getMessage());
        }

        /** @var ScheduledWineProduct $scheduledWineComplete */
        $scheduledWineComplete = ScheduledWineProduct::get()->byID($scheduledWine->ID);

        $this->assertEquals(FlowStatus::COMPLETED, $scheduledWineComplete->Status);

        $variations = $scheduledWine->ScheduledVariations();

        $this->assertEquals(2, $variations->count());

        // These should both be COMPLETED
        /** @var ScheduledWineVariation $variation */
        foreach ($variations as $variation) {
            $this->assertEquals(FlowStatus::COMPLETED, $variation->Status);
        }

        /** @var CompletedTask $task */
        $task = $this->objFromFixture(CompletedTask::class, 'task');

        $this->assertEquals(1, $task->ProductsUpdated);
        $this->assertEquals(FlowStatus::COMPLETED, $task->Status);

        // Price should be updated
        /** @var WineProduct $wineProduct */
        $wineProduct = WineProduct::get()->filter('ForecastGroup', 'VMCALBAG')->first();
        $this->assertEquals(17.99, $wineProduct->BasePrice->getDecimalValue());

        // Get the product variations
        $productVariations = $wineProduct->ProductVariations();

        $this->assertEquals(2, $productVariations->count());

        // SKUs and prices
        $SKUs = $productVariations->column('SKU');
        $this->assertEquals(['VMCALBAG176Z', 'VMCALBAG186Z'], $SKUs);

        /** @var ComplexProductVariation $productVariation */
        foreach ($productVariations as $productVariation) {
            $this->assertEquals(17.99, $productVariation->BasePrice->getDecimalValue());
        }
    }

    public function testGetStock()
    {
        /** @var ObjectProphecy|StockAPIService $dupeMock */
        $dupeMock = $this->prophesize(StockAPIService::class);

        /** @var MethodProphecy $methodMock */
        $methodMock = $dupeMock->products();

        $methodMock->willReturn([
            [
                'webDebtorCode' => 'VMWEBNZ1',
                'productCode'   => 'VMCALBAG176Z',
                'stock'         => 47
            ]
        ]);

        /** @var StockAPIService $dupeMockRevealed */
        $dupeMockRevealed = $dupeMock->reveal();

        $this->assertEquals(
            [[
                'webDebtorCode' => 'VMWEBNZ1',
                'productCode'   => 'VMCALBAG176Z',
                'stock'         => 47
            ]],
            $dupeMockRevealed->products()
        );
    }

    /**
     * @throws HTTPResponse_Exception
     */
    public function testGetPricing()
    {
        /** @var ObjectProphecy|PricingAPIService $dupeMock */
        $dupeMock = $this->prophesize(PricingAPIService::class);

        /** @var MethodProphecy $methodMock */
        $methodMock = $dupeMock->products();

        $methodMock->willReturn([
            [
                'market'                  => 'NZ',
                'webDebtorCode'           => 'VMWEBNZ1',
                'forecastGroup'           => 'VMCALBAG',
                'productCode'             => 'VMCALBAG176Z',
                'priceGroup'              => 'NZWB',
                'currentSellingPriceUnit' => '17.99'
            ]
        ]);

        /** @var PricingAPIService $dupeMockRevealed */
        $dupeMockRevealed = $dupeMock->reveal();

        $this->assertEquals(
            [[
                'market'                  => 'NZ',
                'webDebtorCode'           => 'VMWEBNZ1',
                'forecastGroup'           => 'VMCALBAG',
                'productCode'             => 'VMCALBAG176Z',
                'priceGroup'              => 'NZWB',
                'currentSellingPriceUnit' => '17.99'
            ]],
            $dupeMockRevealed->products()
        );
    }

    public function testPostOrder()
    {
        /** @var ComplexProductVariation $barrique2017 */
        $barrique2017 = $this->objFromFixture(ComplexProductVariation::class, 'barrique-vintage-2017');

        $order = $this->order;
        $order->CustomerEmail = 'customer@example.org';
        $order->addItem($barrique2017);
        $order->Lock();

        /** @var ObjectProphecy|OrderAPIService $dupeMock */
        $dupeMock = $this->prophesize(OrderAPIService::class);

        $data = $order->formatDataForFlow();

        // Test XML items
        $xml = new SimpleXMLElement($data);

        $processedData = BasicAuthConnector::convertXmlToArray($xml);

        $this->assertArrayHasKey('orderHeader', $processedData, 'XML is malformed, missing orderHeader');

        /** @var MethodProphecy $methodMock */
        $methodMock = $dupeMock->order($data);

        $methodMock->willReturn([
            'message' => "Order: '12345' successfully proccessed to Flow with Id: '{31DA2C48-70B3-43FC-B444-36424306A13D}'"
        ]);

        /** @var OrderAPIService $dupeMockRevealed */
        $dupeMockRevealed = $dupeMock->reveal();

        $this->assertEquals(
            [
                'message' => "Order: '12345' successfully proccessed to Flow with Id: '{31DA2C48-70B3-43FC-B444-36424306A13D}'"
            ],
            $dupeMockRevealed->order($data)
        );

        // Test a duplication
        $methodMock->willReturn([
            'message' => "Duplicate Order found! Order: 47286."
        ]);

        $this->assertEquals(
            [
                'message' => "Duplicate Order found! Order: 47286."
            ],
            $dupeMockRevealed->order($data)
        );

    }


    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        static::registerPublishingBlueprint(WineProduct::class);
        static::registerPublishingBlueprint(ProductAttribute::class);
        static::registerPublishingBlueprint(ProductAttributeOption::class);
        static::registerPublishingBlueprint(ComplexProductVariation::class);


        static::registerPublishingBlueprint(ScheduledWineProduct::class);
        static::registerPublishingBlueprint(ScheduledWineVariation::class);
        static::registerPublishingBlueprint(CompletedTask::class);

        static::registerPublishingBlueprint(ViewCartPage::class);
        static::registerPublishingBlueprint(ShopWinesPage::class);
        static::registerPublishingBlueprint(ViewOrderPage::class);

        parent::setUp();

        $this->barrique = $this->objFromFixture(WineProduct::class, 'barrique');;
        $this->vmcalbag = $this->objFromFixture(WineProduct::class, 'vmcalbag');;

        $this->order = Order::singleton()->createCart();
    }
}
