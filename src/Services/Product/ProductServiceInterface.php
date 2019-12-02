<?php


namespace Isobar\Flow\Services\Product;

/**
 * Retrieves products from the Flow API
 *
 * Interface ProductServiceInterface
 * @package App\Flow\Services\Product
 */
interface ProductServiceInterface
{
    /**
     * @return array List of products
     */
    public function products();
}
