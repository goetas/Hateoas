<?php

namespace Hateoas\ClassReflectionProvider;

interface ClassReflectionProviderInterface
{
    function createRelationInstance($class, $type);
    function getOriginalType($class);
    function getOriginalClass($class);
}
