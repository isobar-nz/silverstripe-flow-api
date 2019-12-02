<?php
declare(strict_types=1);

namespace Isobar\Tests\Flow\Fixtures;

/**
 * Class Fixtures
 * @package SwipeStripe\Common\Tests\Fixtures
 */
final class Fixtures
{
    const BASE_PATH = __DIR__;

    const ORDER               = self::BASE_PATH . '/order.yml';
    const WINE_PRODUCT        = self::BASE_PATH . '/wine-product.yml';
    const SCHEDULED_PRODUCTS  = self::BASE_PATH . '/scheduled-products.yml';
    const BASE_COMMERCE_PAGES = self::BASE_PATH . '/base-commerce-pages.yml';
}
