<?php

namespace MJS\OptimisticLocking;

class StaleObjectException extends \Exception
{

    /**
     * @var object
     */
    protected $object;

    /**
     * @param object  $object
     * @param integer $version
     *
     * @return StaleObjectException
     */
    public static function createFromObject($object, $version)
    {
        $message = sprintf('Object with version %d is outdated', $version);
        $exception = new static($message);
        $exception->setObject($object);

        return $exception;
    }

    /**
     * @return object
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param object $object
     */
    public function setObject($object)
    {
        $this->object = $object;
    }
}