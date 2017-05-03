<?php

namespace Hateoas\Serializer\Metadata;

use Hateoas\ClassReflectionProvider\ClassReflectionProviderInterface;
use Hateoas\ClassReflectionProvider\ReflectedClassMarkerInterface;
use Hateoas\Configuration\Exclusion;
use Hateoas\Configuration\RelationProvider;
use Hateoas\Configuration\Route;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\ExpressionPropertyMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Metadata\Driver\DriverInterface;
use Metadata\MetadataFactoryInterface;

/**
 * @author Asmir Mustafic <goetas@gmail.com>
 */
class EmbeddedMetadataDriver implements DriverInterface
{
    /**
     * @var MetadataFactoryInterface
     */
    private $factory;
    /**
     * @var ClassReflectionProviderInterface
     */
    private $classReflectionProvider;

    public function __construct(MetadataFactoryInterface $factory, ClassReflectionProviderInterface $classReflectionProvider)
    {
        $this->factory = $factory;
        $this->classReflectionProvider = $classReflectionProvider;
    }

    /**
     * @param \ReflectionClass $class
     *
     * @return \Metadata\ClassMetadata
     */
    public function loadMetadataForClass(\ReflectionClass $class)
    {
        if ($class->implementsInterface(ReflectedClassMarkerInterface::class)) {
            return $this->handleEmbedProxy($class);
        }
    }

    /**
     * @param \ReflectionClass $class
     * @return ClassMetadata
     */
    private function handleEmbedProxy(\ReflectionClass $class)
    {
        $parentClass = $this->classReflectionProvider->getOriginalClass($class->name);
        $type = $this->classReflectionProvider->getOriginalType($class->name);

        /** @var $hateoasClassMetadata \Hateoas\Configuration\Metadata\ClassMetadata */
        $hateoasClassMetadata = $this->factory->getMetadataForClass($parentClass);

        $classMetadata = new ClassMetadata($class->name);

        if (!$hateoasClassMetadata) {
            return $classMetadata;
        }

        foreach ($hateoasClassMetadata->getRelations() as $relation) {
            if ($type === 'Embedded' && $relation->getEmbedded()) {

                $property = new ExpressionPropertyMetadata($class->name, $relation->getName(), $relation->getEmbedded()->getContent());

                $property->xmlEntryName = $relation->getEmbedded()->getXmlElementName();

                if ($exclusion = $relation->getEmbedded()->getExclusion()) {
                    $this->handleExclusion($exclusion, $property);
                }
                $classMetadata->addPropertyMetadata($property);

            } elseif ($type === 'Links' && $relation->getHref()) {

                // @todo what about cacheability of StaticPropertyMetadata? .... :-/
                $fakeInstance = $this->classReflectionProvider->createRelationInstance($parentClass, 'Link__' . $relation->getName());
                $property = new StaticPropertyMetadata($class->name, $relation->getName(), $fakeInstance);

                $property->setType(get_class($fakeInstance));

                if ($exclusion = $relation->getExclusion()) {
                    $this->handleExclusion($exclusion, $property);
                }
                $classMetadata->addPropertyMetadata($property);

            } elseif ($type === 'Link__' . $relation->getName() && $relation->getHref()) {

                if (is_string($relation->getHref())) {
                    $property = new StaticPropertyMetadata($class->name, 'href', $relation->getHref());
                } else {
                    /**
                     * @var $route Route
                     */
                    $route = $relation->getHref();
                    $expression = "service('{$route->getGenerator()}').generate('{$route->getName()}', {} , " . var_dump($route->isAbsolute()) . ")";
                    $property = new ExpressionPropertyMetadata($class->name, 'href', $expression);
                }
                $property->xmlAttribute = true;
                $classMetadata->addPropertyMetadata($property);

                foreach ($relation->getAttributes() as $name => $value) {
                    $property = new StaticPropertyMetadata($class->name, $name, $value);
                    $property->xmlAttribute = true;
                    $classMetadata->addPropertyMetadata($property);
                }
            }
        }

        return $classMetadata;
    }

    /*
     private function enrichMetadata(HateoasClassMetadata $hateoasClassMetadata, ClassMetadata $classMetadata)
     {
         foreach ($hateoasClassMetadata->getRelations() as $relation) {
             if ($relation->getEmbedded()) {
                 $property = new ExpressionPropertyMetadata(
                     $classMetadata->name,
                     '_embedded_',
                     "service('hateoas.relation_builder').buildRelation('" . addslashes($classMetadata->name) . "', 'Embedded', object)");
                 //$classMetadata->addPropertyMetadata($property);
             }
             if ($relation->getHref()) {
                 $property = new ExpressionPropertyMetadata(
                     $classMetadata->name,
                     '_links_',
                     "service('hateoas.relation_builder').buildRelation('" . addslashes($classMetadata->name) . "', 'Links', object)");
                 $classMetadata->addPropertyMetadata($property);
             }
         }
     }
     */

    private function handleExclusion(Exclusion $exclusion, PropertyMetadata $property)
    {
        $property->groups = $exclusion->getGroups();
        $property->untilVersion = $exclusion->getUntilVersion();
        $property->sinceVersion = $exclusion->getSinceVersion();
        $property->excludeIf = $exclusion->getExcludeIf();
        $property->maxDepth = $exclusion->getMaxDepth();
    }
}

