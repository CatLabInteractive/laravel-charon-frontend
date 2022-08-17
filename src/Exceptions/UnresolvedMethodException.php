<?php

namespace CatLab\CharonFrontend\Exceptions;

use CatLab\Charon\Laravel\Exceptions\CharonHttpException;

/**
 * Class UnresolvedMethodException
 * @package CatLab\CharonFrontend\Exceptions
 */
class UnresolvedMethodException extends CharonHttpException
{
    /**
     * @param $resourceController
     * @param $action
     * @return UnresolvedMethodException
     */
    public static function create($resourceController, $action)
    {
        return new self('Could not map method for ' . $action . ' in ' . get_class($resourceController));
    }
}
