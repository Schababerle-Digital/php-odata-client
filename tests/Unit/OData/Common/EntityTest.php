<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Tests\Unit\OData\Common;

use PHPUnit\Framework\TestCase;
use SchababerleDigital\OData\Client\Common\Entity;
use SchababerleDigital\OData\Contract\EntityCollectionInterface;
use SchababerleDigital\OData\Contract\EntityInterface;

/**
 * @covers \SchababerleDigital\OData\Client\Common\Entity
 */
class EntityTest extends TestCase
{
    private const DEFAULT_ENTITY_TYPE = 'TestEntityType';
    private const DEFAULT_ID = '123';
    private const DEFAULT_ETAG = 'W/"abc"';

    /**
     * @test
     */
    public function constructorInitializesPropertiesCorrectly(): void
    {
        $properties = ['name' => 'Test Name', 'value' => 100];
        $entity = new Entity(
            self::DEFAULT_ENTITY_TYPE,
            $properties,
            self::DEFAULT_ID,
            self::DEFAULT_ETAG,
            false // isNew
        );

        $this->assertEquals(self::DEFAULT_ID, $entity->getId());
        $this->assertEquals(self::DEFAULT_ENTITY_TYPE, $entity->getEntityType());
        $this->assertEquals($properties, $entity->getProperties());
        $this->assertEquals(self::DEFAULT_ETAG, $entity->getETag());
        $this->assertFalse($entity->isNew());
    }

    /**
     * @test
     */
    public function constructorSetsIsNewToTrueIfIdIsNullAndNotSpecified(): void
    {
        $entity = new Entity(self::DEFAULT_ENTITY_TYPE, []);
        $this->assertTrue($entity->isNew());
    }

    /**
     * @test
     */
    public function constructorSetsIsNewToFalseIfIdIsProvidedAndNotSpecified(): void
    {
        $entity = new Entity(self::DEFAULT_ENTITY_TYPE, [], self::DEFAULT_ID);
        $this->assertFalse($entity->isNew());
    }

    /**
     * @test
     */
    public function constructorRespectsExplicitIsNewFlag(): void
    {
        $entity = new Entity(self::DEFAULT_ENTITY_TYPE, [], null, null, true);
        $this->assertTrue($entity->isNew());

        $entityExplicitFalse = new Entity(self::DEFAULT_ENTITY_TYPE, [], null, null, false);
        $this->assertFalse($entityExplicitFalse->isNew());
    }

    /**
     * @test
     */
    public function setPropertyAndGetPropertyWorkCorrectly(): void
    {
        $entity = new Entity(self::DEFAULT_ENTITY_TYPE);
        $this->assertNull($entity->getProperty('newProp'));

        $entity->setProperty('newProp', 'newValue');
        $this->assertEquals('newValue', $entity->getProperty('newProp'));

        $entity->setProperty('anotherProp', 42);
        $this->assertEquals(42, $entity->getProperty('anotherProp'));
        $this->assertEquals(['newProp' => 'newValue', 'anotherProp' => 42], $entity->getProperties());
    }

    /**
     * @test
     */
    public function getPropertyReturnsNullForNonExistentProperty(): void
    {
        $entity = new Entity(self::DEFAULT_ENTITY_TYPE);
        $this->assertNull($entity->getProperty('nonExistent'));
    }

    /**
     * @test
     */
    public function setNavigationPropertyAndGetNavigationPropertyWorkCorrectly(): void
    {
        $entity = new Entity(self::DEFAULT_ENTITY_TYPE);
        $mockRelatedEntity = $this->createMock(EntityInterface::class);
        $mockRelatedCollection = $this->createMock(EntityCollectionInterface::class);

        $this->assertNull($entity->getNavigationProperty('RelatedEntity'));

        $entity->setNavigationProperty('RelatedEntity', $mockRelatedEntity);
        $this->assertSame($mockRelatedEntity, $entity->getNavigationProperty('RelatedEntity'));

        $entity->setNavigationProperty('RelatedCollection', $mockRelatedCollection);
        $this->assertSame($mockRelatedCollection, $entity->getNavigationProperty('RelatedCollection'));

        $expectedNavProps = [
            'RelatedEntity' => $mockRelatedEntity,
            'RelatedCollection' => $mockRelatedCollection,
        ];
        $this->assertEquals($expectedNavProps, $entity->getNavigationProperties());
    }

    /**
     * @test
     */
    public function getNavigationPropertyReturnsNullForNonExistentNavProperty(): void
    {
        $entity = new Entity(self::DEFAULT_ENTITY_TYPE);
        $this->assertNull($entity->getNavigationProperty('nonExistentNav'));
    }

    /**
     * @test
     */
    public function setETagAndGetETagWorkCorrectly(): void
    {
        $entity = new Entity(self::DEFAULT_ENTITY_TYPE);
        $this->assertNull($entity->getETag());

        $entity->setETag(self::DEFAULT_ETAG);
        $this->assertEquals(self::DEFAULT_ETAG, $entity->getETag());
    }

    /**
     * @test
     */
    public function toArrayReturnsProperties(): void
    {
        $properties = ['propA' => 'valA', 'propB' => 123];
        $entity = new Entity(self::DEFAULT_ENTITY_TYPE, $properties);
        $this->assertEquals($properties, $entity->toArray());
    }

    /**
     * @test
     */
    public function markAsPersistedUpdatesIdETagAndIsNewFlag(): void
    {
        $entity = new Entity(self::DEFAULT_ENTITY_TYPE, [], null, null, true);
        $this->assertTrue($entity->isNew());
        $this->assertNull($entity->getId());
        $this->assertNull($entity->getETag());

        $newId = 'server-generated-id';
        $newETag = 'W/"xyz123"';
        $entity->markAsPersisted($newId, $newETag);

        $this->assertFalse($entity->isNew());
        $this->assertEquals($newId, $entity->getId());
        $this->assertEquals($newETag, $entity->getETag());
    }

    /**
     * @test
     */
    public function markAsPersistedCanUpdateOnlyETagIfIdIsNull(): void
    {
        $existingId = 'id-1';
        $entity = new Entity(self::DEFAULT_ENTITY_TYPE, [], $existingId, null, true);
        $newETag = 'W/"newETag"';

        $entity->markAsPersisted(null, $newETag);

        $this->assertFalse($entity->isNew());
        $this->assertEquals($existingId, $entity->getId()); // ID should remain unchanged
        $this->assertEquals($newETag, $entity->getETag());
    }
}