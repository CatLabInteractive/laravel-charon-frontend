<?php

namespace CatLab\CharonFrontend\Exceptions;

use CatLab\Laravel\Exceptions\Exception;

/**
 * Class UnresolvedMethodException
 * @package CatLab\CharonFrontend\Exceptions
 */
class UnresolvedMethodException extends Exception
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