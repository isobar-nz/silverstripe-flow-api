<?php

namespace Isobar\Flow\Services;

/**
 * Class FlowStatus
 * @package App\Flow
 */
final class FlowStatus
{
    /**
     * $db ENUM for order status - defaults to pending
     */
    const ENUM = 'Enum(array("' . self::PENDING . '","' . self::FAILED . '","' . self::PROCESSING . '","' .
    self::COMPLETED . '","' . self::CANCELLED . '", ), "' . self::PENDING . '")';

    const PENDING = 'Pending';
    const PROCESSING = 'Processing';
    const COMPLETED = 'Completed';
    const CANCELLED = 'Cancelled';
    const FAILED = 'Failed';
}
