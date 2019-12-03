<?php


namespace Isobar\Tests\Flow;

use App\Tests\PublishesFixtures;
use Exception;
use Money\Currency;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Versioned\Versioned;
use SwipeStripe\Price\SupportedCurrencies\SingleSupportedCurrency;
use SwipeStripe\Price\SupportedCurrencies\SupportedCurrenciesInterface;

/**
 * Class BaseTest
 * @package SwipeStripe\Common\Tests
 */
abstract class BaseTest extends SapphireTest
{
    use PublishesFixtures;

    const CURRENCY = 'NZD';

    /**
     * @var Currency
     */
    protected $currency;

    /**
     * @inheritDoc
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        Config::modify()->set(SingleSupportedCurrency::class, 'shop_currency', static::CURRENCY);
        Injector::inst()->registerService(new SingleSupportedCurrency(), SupportedCurrenciesInterface::class);
    }

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();

        Versioned::set_stage(Versioned::LIVE);
        $this->currency = new Currency(static::CURRENCY);
    }

    /**
     * @param int $seconds
     * @throws Exception
     */
    protected function mockWait(int $seconds = 5): void
    {
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + $seconds);
    }

    /**
     *
     */
    protected function tearDown()
    {
        parent::tearDown();
        DBDatetime::clear_mock_now();
    }
}
