<?php

namespace Hateoas\Serializer\EventSubscriber;

use Hateoas\ClassReflectionProvider\ClassReflectionProviderInterface;
use Hateoas\ClassReflectionProvider\ReflectedClassMarkerInterface;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use Metadata\MetadataFactoryInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class SerializationEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var MetadataFactoryInterface
     */
    private $factory;
    /**
     * @var ClassReflectionProviderInterface
     */
    private $classReflectionProvider;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            array(
                'event' => Events::POST_SERIALIZE,
                'method' => 'onPostSerialize',
            ),
        );
    }

    private $visited = array();

    public function __construct(MetadataFactoryInterface $factory, ClassReflectionProviderInterface $classReflectionProvider)
    {
        $this->factory = $factory;
        $this->classReflectionProvider = $classReflectionProvider;
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        $object = $event->getObject();


        if ($object instanceof ReflectedClassMarkerInterface) {
            return;
        }

        $hateoasMetadata = $this->factory->getMetadataForClass(get_class($object));
        if (!$hateoasMetadata) {
            return;
        }

        if (isset($this->visited[spl_object_hash($object)])) {
            return;
        }
        $this->visited[spl_object_hash($object)] = true;

        /**
         * @var $visitor JsonSerializationVisitor
         */
        $visitor = $event->getVisitor();
        $context = $event->getContext();


        if ($context->isVisiting($object)) {
            return;
        }

        if ($hateoasMetadata->hasLinks()) {
            $fakeInstance = $this->classReflectionProvider->createRelationInstance(get_class($object), 'Links');

            $property = new StaticPropertyMetadata(get_class($object), '_links', $object);
            $property->setType(get_class($fakeInstance));

            $visitor->visitProperty($property, $object, $context);
        }

        if ($hateoasMetadata->hasEmbedded()) {
            $fakeInstance = $this->classReflectionProvider->createRelationInstance(get_class($object), 'Embedded');

            $property = new StaticPropertyMetadata(get_class($object), '_embedded', $object);
            $property->setType(get_class($fakeInstance));

            $visitor->visitProperty($property, $object, $context);
        }
    }
}
