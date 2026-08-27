<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Questions\Registry;

use PHPUnit\Framework\TestCase;
use WPCBTPro\Questions\Contracts\QuestionCategory;
use WPCBTPro\Questions\Contracts\QuestionType;
use WPCBTPro\Questions\Registry\QuestionTypeRegistry;

final class QuestionTypeRegistryTest extends TestCase
{
    private function typeStub(string $id, QuestionCategory $category = QuestionCategory::Objective): QuestionType
    {
        $stub = $this->createStub(QuestionType::class);
        $stub->method('id')->willReturn($id);
        $stub->method('category')->willReturn($category);

        return $stub;
    }

    public function testRegisterAndGet(): void
    {
        $registry = new QuestionTypeRegistry();
        $type = $this->typeStub('mcq_single');

        $registry->register($type);

        self::assertTrue($registry->has('mcq_single'));
        self::assertSame($type, $registry->get('mcq_single'));
    }

    public function testGetUnknownTypeThrows(): void
    {
        $registry = new QuestionTypeRegistry();

        $this->expectException(\OutOfBoundsException::class);

        $registry->get('does_not_exist');
    }

    public function testRegisterDuplicateIdThrows(): void
    {
        $registry = new QuestionTypeRegistry();
        $registry->register($this->typeStub('mcq_single'));

        $this->expectException(\InvalidArgumentException::class);

        $registry->register($this->typeStub('mcq_single'));
    }

    public function testAllReturnsEveryRegisteredType(): void
    {
        $registry = new QuestionTypeRegistry();
        $registry->register($this->typeStub('mcq_single'));
        $registry->register($this->typeStub('programming'));

        self::assertCount(2, $registry->all());
    }

    public function testGroupedByCategoryBucketsByCategoryValue(): void
    {
        $registry = new QuestionTypeRegistry();
        $registry->register($this->typeStub('mcq_single', QuestionCategory::Objective));
        $registry->register($this->typeStub('true_false', QuestionCategory::Objective));
        $registry->register($this->typeStub('programming', QuestionCategory::Programming));

        $grouped = $registry->groupedByCategory();

        self::assertCount(2, $grouped['objective']);
        self::assertCount(1, $grouped['programming']);
    }
}
