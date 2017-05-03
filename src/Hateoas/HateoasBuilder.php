<?php

namespace Hateoas;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\FileCacheReader;
use Doctrine\Common\Annotations\Reader;
use Hateoas\ClassReflectionProvider\ClassReflectionProviderInterface;
use Hateoas\ClassReflectionProvider\EvalClassReflectionProvider;
use Hateoas\Configuration\Metadata\Driver\AnnotationDriver;
use Hateoas\Configuration\Metadata\Driver\XmlDriver;
use Hateoas\Configuration\Metadata\Driver\YamlDriver;
use Hateoas\Serializer\EventSubscriber\SerializationEventSubscriber;
use Hateoas\Serializer\Metadata\EmbeddedMetadataDriver;
use JMS\Serializer\Builder\DefaultDriverFactory;
use JMS\Serializer\EventDispatcher\EventDispatcherInterface;
use JMS\Serializer\Expression\ExpressionEvaluator;
use JMS\Serializer\SerializerBuilder;
use Metadata\Cache\FileCache;
use Metadata\Driver\DriverChain;
use Metadata\Driver\FileLocator;
use Metadata\MetadataFactory;
use Metadata\MetadataFactoryInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 *
 * Some code (metadata things) from this class has been taken from
 * https://github.com/schmittjoh/serializer/blob/a29f1e5083654ba2c126acd94ddb2287069b0b5b/src/JMS/Serializer/SerializerBuilder.php
 */
class HateoasBuilder
{
    /**
     * @var SerializerBuilder
     */
    private $serializerBuilder;

    private $metadataDirs = array();

    private $debug = false;

    private $cacheDir;

    private $annotationReader;

    private $includeInterfaceMetadata = false;

    /**
     * @param SerializerBuilder $serializerBuilder
     *
     * @return HateoasBuilder
     */
    public static function create(SerializerBuilder $serializerBuilder = null)
    {
        return new static($serializerBuilder);
    }

    /**
     * @return Hateoas
     */
    public static function buildHateoas()
    {
        $builder = static::create();

        return $builder->build();
    }

    public function __construct(SerializerBuilder $serializerBuilder = null)
    {
        $this->serializerBuilder = $serializerBuilder ?: SerializerBuilder::create();
    }

    /**
     * Build a configured Hateoas instance.
     *
     * @return Hateoas
     */
    public function build()
    {
        $metadataFactory = $this->buildMetadataFactory();

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(new BasicSerializerFunctionsProvider());

        $reflectedClassProvider = new EvalClassReflectionProvider();

        /*
        $container = new Container();
        $container->set('hateoas_link_helper', $reflectedClassProvider);

        $context = array('container' => $container);
        */

        $this->serializerBuilder
            ->setMetadataDriverFactory(new FooDefaultDriverFactory($metadataFactory, $reflectedClassProvider))
            ->addDefaultListeners()
            ->configureListeners(function (EventDispatcherInterface $dispatcher) use ($metadataFactory, $reflectedClassProvider) {
                $dispatcher->addSubscriber(new SerializationEventSubscriber($metadataFactory, $reflectedClassProvider));
            });
        $this->serializerBuilder->setExpressionEvaluator(new ExpressionEvaluator($expressionLanguage));

        $jmsSerializer = $this->serializerBuilder->build();

        return new Hateoas($jmsSerializer);
    }

    /**
     * Add a new URL generator. If you pass `null` as name, it will be the
     * default URL generator.
     *
     * @param string|null $name
     * @param UrlGeneratorInterface $urlGenerator
     *
     * @return HateoasBuilder
     */
    public function setUrlGenerator($name, UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGeneratorRegistry->set($name, $urlGenerator);

        return $this;
    }

    /**
     * Add a new relation provider resolver.
     *
     * @param RelationProviderResolverInterface $resolver
     *
     * @return HateoasBuilder
     */
    public function addRelationProviderResolver(RelationProviderResolverInterface $resolver)
    {
        $this->chainResolver->addResolver($resolver);

        return $this;
    }

    /**
     * @param ConfigurationExtensionInterface $configurationExtension
     *
     * @return HateoasBuilder
     */
    public function addConfigurationExtension(ConfigurationExtensionInterface $configurationExtension)
    {
        $this->configurationExtensions[] = $configurationExtension;

        return $this;
    }

    /**
     * @param boolean $debug
     *
     * @return HateoasBuilder
     */
    public function setDebug($debug)
    {
        $this->debug = (boolean)$debug;

        return $this;
    }

    /**
     * @param string $dir
     *
     * @return HateoasBuilder
     */
    public function setCacheDir($dir)
    {
        if (!is_dir($dir)) {
            $this->createDir($dir);
        }

        if (!is_writable($dir)) {
            throw new \InvalidArgumentException(sprintf('The cache directory "%s" is not writable.', $dir));
        }

        $this->cacheDir = $dir;

        return $this;
    }

    /**
     * @param boolean $include Whether to include the metadata from the interfaces
     *
     * @return HateoasBuilder
     */
    public function includeInterfaceMetadata($include)
    {
        $this->includeInterfaceMetadata = (boolean)$include;

        return $this;
    }

    /**
     * Set a map of namespace prefixes to directories.
     *
     * This method overrides any previously defined directories.
     *
     * @param array $namespacePrefixToDirMap
     *
     * @return HateoasBuilder
     */
    public function setMetadataDirs(array $namespacePrefixToDirMap)
    {
        foreach ($namespacePrefixToDirMap as $dir) {
            if (!is_dir($dir)) {
                throw new \InvalidArgumentException(sprintf('The directory "%s" does not exist.', $dir));
            }
        }

        $this->metadataDirs = $namespacePrefixToDirMap;

        return $this;
    }

    /**
     * Add a directory where the serializer will look for class metadata.
     *
     * The namespace prefix will make the names of the actual metadata files a bit shorter. For example, let's assume
     * that you have a directory where you only store metadata files for the ``MyApplication\Entity`` namespace.
     *
     * If you use an empty prefix, your metadata files would need to look like:
     *
     * ``my-dir/MyApplication.Entity.SomeObject.yml``
     * ``my-dir/MyApplication.Entity.OtherObject.yml``
     *
     * If you use ``MyApplication\Entity`` as prefix, your metadata files would need to look like:
     *
     * ``my-dir/SomeObject.yml``
     * ``my-dir/OtherObject.yml``
     *
     * Please keep in mind that you currently may only have one directory per namespace prefix.
     *
     * @param string $dir The directory where metadata files are located.
     * @param string $namespacePrefix An optional prefix if you only store metadata for specific namespaces in this directory.
     *
     * @return HateoasBuilder
     */
    public function addMetadataDir($dir, $namespacePrefix = '')
    {
        if (!is_dir($dir)) {
            throw new \InvalidArgumentException(sprintf('The directory "%s" does not exist.', $dir));
        }

        if (isset($this->metadataDirs[$namespacePrefix])) {
            throw new \InvalidArgumentException(sprintf('There is already a directory configured for the namespace prefix "%s". Please use replaceMetadataDir() to override directories.', $namespacePrefix));
        }

        $this->metadataDirs[$namespacePrefix] = $dir;

        return $this;
    }

    /**
     * Add a map of namespace prefixes to directories.
     *
     * @param array $namespacePrefixToDirMap
     *
     * @return HateoasBuilder
     */
    public function addMetadataDirs(array $namespacePrefixToDirMap)
    {
        foreach ($namespacePrefixToDirMap as $prefix => $dir) {
            $this->addMetadataDir($dir, $prefix);
        }

        return $this;
    }

    /**
     * Similar to addMetadataDir(), but overrides an existing entry.
     *
     * @param string $dir
     * @param string $namespacePrefix
     *
     * @return HateoasBuilder
     */
    public function replaceMetadataDir($dir, $namespacePrefix = '')
    {
        if (!is_dir($dir)) {
            throw new \InvalidArgumentException(sprintf('The directory "%s" does not exist.', $dir));
        }

        if (!isset($this->metadataDirs[$namespacePrefix])) {
            throw new \InvalidArgumentException(sprintf('There is no directory configured for namespace prefix "%s". Please use addMetadataDir() for adding new directories.', $namespacePrefix));
        }

        $this->metadataDirs[$namespacePrefix] = $dir;

        return $this;
    }

    private function buildMetadataFactory()
    {
        $annotationReader = $this->annotationReader;

        if (null === $annotationReader) {
            $annotationReader = new AnnotationReader();

            if (null !== $this->cacheDir) {
                $this->createDir($this->cacheDir . '/annotations');
                $annotationReader = new FileCacheReader($annotationReader, $this->cacheDir . '/annotations', $this->debug);
            }
        }

        if (!empty($this->metadataDirs)) {
            $fileLocator = new FileLocator($this->metadataDirs);
            $metadataDriver = new DriverChain(array(
                new YamlDriver($fileLocator),
                new XmlDriver($fileLocator),
                new AnnotationDriver($annotationReader),
            ));
        } else {
            $metadataDriver = new AnnotationDriver($annotationReader);
        }

        $metadataFactory = new MetadataFactory($metadataDriver, null, $this->debug);
        $metadataFactory->setIncludeInterfaces($this->includeInterfaceMetadata);

        if (null !== $this->cacheDir) {
            $this->createDir($this->cacheDir . '/metadata');
            $metadataFactory->setCache(new FileCache($this->cacheDir . '/metadata'));
        }

        return $metadataFactory;
    }

    /**
     * @param string $dir
     */
    private function createDir($dir)
    {
        if (is_dir($dir)) {
            return;
        }

        if (false === @mkdir($dir, 0777, true)) {
            throw new \RuntimeException(sprintf('Could not create directory "%s".', $dir));
        }
    }
}

class FooDefaultDriverFactory extends DefaultDriverFactory
{
    /**
     * @var MetadataFactoryInterface
     */
    private $factory;

    /**
     * @var ClassReflectionProviderInterface
     */
    private $reflectedClassProvider;

    public function __construct(MetadataFactoryInterface $factory, ClassReflectionProviderInterface $reflectedClassProvider)
    {
        $this->factory = $factory;
        $this->reflectedClassProvider = $reflectedClassProvider;
    }

    public function createDriver(array $metadataDirs, Reader $annotationReader)
    {
        $driver = parent::createDriver($metadataDirs, $annotationReader);

        return new DriverChain([new EmbeddedMetadataDriver($this->factory, $this->reflectedClassProvider), $driver]);
    }
}

class BasicSerializerFunctionsProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        return [
            new ExpressionFunction('service', function ($arg) {
                return sprintf('$this->get(%s)', $arg);
            }, function (array $variables, $value) {
                return $variables['container']->get($value);
            }),
            new ExpressionFunction('parameter', function ($arg) {
                return sprintf('$this->getParameter(%s)', $arg);
            }, function (array $variables, $value) {
                return $variables['container']->getParameter($value);
            }),
            new ExpressionFunction('is_granted', function ($attribute, $object = null) {
                return sprintf('call_user_func_array(array($this->get(\'security.authorization_checker\'), \'isGranted\'), array(%s, %s))', $attribute, $object);
            }, function (array $variables, $attribute, $object = null) {
                return call_user_func_array(
                    array($variables['container']->get('security.authorization_checker'), 'isGranted'),
                    [$attribute, $object]
                );
            }),
        ];
    }
}
