<?php

namespace Hateoas\ClassReflectionProvider;

final class EvalClassReflectionProvider implements ClassReflectionProviderInterface
{
    private $marker = '__HATEOAS__';
    private $useEval = true;

    public function __construct(array $options = array())
    {
        if (!empty($options['marker'])) {
            $this->marker = $options['marker'];
        }
        if (isset($options['use_eval'])) {
            $this->useEval = !!$options['use_eval'];
        }
    }

    /**
     * @param string $class
     * @param string $type
     * @return ReflectedClassMarkerInterface
     */
    public function createRelationInstance($class, $type)
    {
        if (strpos($class, $this->marker) !== false) {
            return new $class();
        }

        $ns = $class . "\\" . $this->marker;
        $className = "$ns\\$type";

        if (!class_exists($className, false)) {
            $body = "namespace $ns {class $type implements \\Hateoas\\ClassReflectionProvider\\ReflectedClassMarkerInterface{}}";
            if ($this->useEval) {
                eval($body);
            } else {
                $tmp = tempnam(sys_get_temp_dir(), 'EvalClassReflectionProvider');
                @file_put_contents($tmp, $body);
                require $tmp;
            }
        }

        return new $className();
    }

    public function getOriginalType($class)
    {
        $pos = strpos($class, $this->marker);
        return substr($class, 1 + $pos + strlen($this->marker));
    }

    public function getOriginalClass($class)
    {
        $pos = strpos($class, $this->marker);
        return substr($class, 0, $pos - 1);
    }
}
