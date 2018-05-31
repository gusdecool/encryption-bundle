<?php

namespace Gdc\EncryptionBundle\Subscriber;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Gdc\EncryptionBundle\Annotation\AesEncrypt;
use GusDeCooL\Cryptography\Encryption\AesEncryption;

/**
 * Class AesSubscriber
 * @package Gdc\EncryptionBundle\Subscriber
 */
class AesSubscriber implements EventSubscriber
{

    #----------------------------------------------------------------------------------------------
    # Properties
    #----------------------------------------------------------------------------------------------

    /**
     * @var AesEncryption
     */
    private $encryptor;

    /**
     * @var Reader
     */
    private $annotationReader;

    #----------------------------------------------------------------------------------------------
    # Magic methods
    #----------------------------------------------------------------------------------------------

    /**
     * AesSubscriber constructor.
     *
     * @param string $key encryption key
     * @param Reader $reader
     */
    public function __construct(string $key, Reader $reader)
    {
        $this->encryptor = new AesEncryption($key);
        $this->annotationReader = $reader;
    }

    #----------------------------------------------------------------------------------------------
    # Public methods
    #----------------------------------------------------------------------------------------------

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        return [Events::prePersist, Events::preUpdate, Events::postLoad];
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \ReflectionException
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->encrypt($args);
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \ReflectionException
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->encrypt($args);
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \ReflectionException
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();
        $properties = $this->getAnnotatedProperties($entity);

        if (empty($properties)) {
            return;
        }

        foreach ($properties as $property) {
            $getter = $this->getGetter($property, $entity);
            $setter = $this->getSetter($property, $entity);

            if (null === $getter || null === $getter) {
                continue;
            }

            $encryptedValue = $entity->$getter();

            if ($encryptedValue !== null) {
                $entity->$setter($this->encryptor->decrypt($encryptedValue));
            }
        }
    }

    #----------------------------------------------------------------------------------------------
    # Private methods
    #----------------------------------------------------------------------------------------------

    /**
     * @param LifecycleEventArgs $args
     * @throws \ReflectionException
     */
    private function encrypt(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();
        $properties = $this->getAnnotatedProperties($entity);

        if (empty($properties)) {
            return;
        }

        foreach ($properties as $property) {
            $getter = $this->getGetter($property, $entity);
            $setter = $this->getSetter($property, $entity);

            if (null === $getter || null === $getter) {
                continue;
            }

            $originalValue = $entity->$getter();

            if ($originalValue !== null) {
                $entity->$setter($this->encryptor->encrypt($originalValue));
            }
        }
    }

    /**
     * @param object $entity
     *
     * @return \ReflectionProperty[]
     * @throws \ReflectionException
     */
    private function getAnnotatedProperties(object $entity): array
    {
        $annotatedProperties = [];

        $class = new \ReflectionClass(ClassUtils::getClass($entity));
        $properties = $class->getProperties();

        foreach ($properties as $property) {
            if (null !== $this->annotationReader->getPropertyAnnotation($property, AesEncrypt::class)) {
                $annotatedProperties[] = $property;
            }
        }

        return $annotatedProperties;
    }

    /**
     * @param \ReflectionProperty $property
     * @param object $entity
     *
     * @return string|null
     */
    private function getGetter(\ReflectionProperty $property, object $entity): ?string
    {
        $getter = 'get' . ucfirst($property->getName());

        if (method_exists($entity, $getter)) {
            return $getter;
        }

        return null;
    }

    /**
     * @param \ReflectionProperty $property
     * @param object $entity
     *
     * @return string|null
     */
    private function getSetter(\ReflectionProperty $property, object $entity): ?string
    {
        $setter = 'set' . ucfirst($property->getName());

        if (method_exists($entity, $setter)) {
            return $setter;
        }

        return null;
    }
}
