<?php

namespace Annotiny\Annotations;

class Target
{
    const TARGET_CLASS      = 1;
    const TARGET_METHOD     = 2;
    const TARGET_PROPERTY   = 4;
    const TARGET_FUNCTION   = 8;
    const TARGET_ANNOTATION = 16;
    const TARGET_ALL        = 31;

    private static $map = [
        'class'      => self::TARGET_CLASS,
        'method'     => self::TARGET_METHOD,
        'property'   => self::TARGET_PROPERTY,
        'function'   => self::TARGET_FUNCTION,
        'annotation' => self::TARGET_ANNOTATION,
        'all'        => self::TARGET_ALL
    ];

    public static function getTargetValue($targets)
    {
        if (!is_array($targets)) {
            self::enforceValidTarget($targets);
            $targets = [$targets];
        }

        $mask = 0;
        foreach ($targets as $target) {
            self::enforceValidTarget($target);
            $mask |= self::$map[ $target ];
        }

        return $mask;
    }

    public static function check($target, $expected)
    {
        $target = self::getTargetValue($target);

        return ($target & $expected) !== 0;
    }

    /**
     * @param string $target
     *
     * @throws \InvalidArgumentException
     */
    private static function enforceValidTarget($target)
    {
        if (!is_string($target)) {
            throw new \InvalidArgumentException('Parameter $type must be a string.');
        }
        if (!isset(self::$map[ $target ])) {
            throw new \InvalidArgumentException("Type {$target} is invalid.");
        }
    }

    public $target;

    public function __construct($target)
    {
        $this->target = self::getTargetValue($target);
    }
}
