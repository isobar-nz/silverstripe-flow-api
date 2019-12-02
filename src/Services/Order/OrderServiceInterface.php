<?php


namespace Isobar\Flow\Services\Order;

/**
 * Retrieves products from the Flow API
 *
 * Interface ProductServiceInterface
 * @package App\Flow\Services\Product
 */
interface OrderServiceInterface
{
    /**
     * @return array List of products
     */
    public function order();
}
