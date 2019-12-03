<?php

namespace Isobar\Flow\Config;

use LogicException;
use SilverStripe\Core\Environment;

trait RequiresConfig
{
    /**
     * Get an environment value. If $default is not set and the environment isn't set either this will error.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getEnv($key, $default = null)
    {
        $value = Environment::getEnv($key);
        if ($value) {
            return $value;
        }
        if (func_num_args() === 1) {
            throw new LogicException("Required environment variable {$key} not set");
        }
        return $default;
    }
}
