<?php

namespace CatLab\CharonFrontend\Exceptions;

use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Laravel\Exceptions\CharonHttpException;
use CatLab\Charon\Laravel\Models\ResourceResponse;

/**
 * Class UnexpectedResponse
 * @package CatLab\CharonFrontend\Exceptions
 */
class UnexpectedResponse extends CharonHttpException
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
