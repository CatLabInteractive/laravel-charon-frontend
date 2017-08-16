<?php

namespace CatLab\CharonFrontend\Exceptions;

use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Models\ResourceResponse;
use CatLab\Laravel\Exceptions\Exception;

/**
 * Class UnexpectedResponse
 * @package CatLab\CharonFrontend\Exceptions
 */
class UnexpectedResponse extends Exception
{
    /**
     * @param $actual
     * @return UnexpectedResponse
     */
    public static function createNoResourceCollection($actual)
    {
        return new self("Expected a " . ResourceResponse::class . " with " . ResourceCollection::class . ", " . get_class($actual) . " given");
    }
}