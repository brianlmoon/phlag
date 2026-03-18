<?php

declare(strict_types=1);

namespace Moonspot\Phlag\Tests\Unit\Mapper;

use Moonspot\Phlag\Data\PhlagEnvironment;
use Moonspot\Phlag\Mapper\PhlagEnvironment as PhlagEnvironmentMapper;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for PhlagEnvironment mapper
 *
 * Tests the is_important field handling in the PhlagEnvironment mapper.
 * Focuses on value object defaults and mapper configuration.
 */
class PhlagEnvironmentTest extends TestCase {

    /**
     * Tests that value object property defaults to false
     *
     * Verifies that the is_important property on the PhlagEnvironment
     * value object has a default value of false.
     *
     * @return void
     */
    public function testValueObjectDefaultsToFalse(): void {

        $environment = new PhlagEnvironment();

        $this->assertFalse($environment->is_important);
        $this->assertIsBool($environment->is_important);
    }

    /**
     * Tests that value object accepts true value
     *
     * Verifies that is_important can be set to true.
     *
     * @return void
     */
    public function testValueObjectAcceptsTrue(): void {

        $environment = new PhlagEnvironment();
        $environment->is_important = true;

        $this->assertTrue($environment->is_important);
    }

    /**
     * Tests that value object accepts false value
     *
     * Verifies that is_important can be explicitly set to false.
     *
     * @return void
     */
    public function testValueObjectAcceptsFalse(): void {

        $environment = new PhlagEnvironment();
        $environment->is_important = true;
        $environment->is_important = false;

        $this->assertFalse($environment->is_important);
    }

    /**
     * Tests that mapper has is_important in MAPPING
     *
     * Verifies that the mapper configuration includes the is_important
     * field so it will be persisted to the database.
     *
     * @return void
     */
    public function testMapperIncludesIsImportantField(): void {

        $mapping = PhlagEnvironmentMapper::MAPPING;

        $this->assertArrayHasKey('is_important', $mapping);
        $this->assertIsArray($mapping['is_important']);
    }

    /**
     * Tests that mapper configuration is correct for all fields
     *
     * Verifies that the mapper includes all expected fields in the
     * correct order matching the database schema.
     *
     * @return void
     */
    public function testMapperFieldOrder(): void {

        $mapping = PhlagEnvironmentMapper::MAPPING;
        $expected_fields = [
            'phlag_environment_id',
            'name',
            'sort_order',
            'is_important',
            'create_datetime',
            'update_datetime',
        ];

        $this->assertEquals(
            $expected_fields,
            array_keys($mapping),
            'Mapper fields should match expected order'
        );
    }

    /**
     * Tests that is_important field is not read-only
     *
     * Verifies that is_important can be written to the database
     * (not marked as read_only in mapper configuration).
     *
     * @return void
     */
    public function testIsImportantIsWritable(): void {

        $mapping = PhlagEnvironmentMapper::MAPPING;

        $this->assertArrayNotHasKey(
            'read_only',
            $mapping['is_important'],
            'is_important should not be read-only'
        );
    }
}
