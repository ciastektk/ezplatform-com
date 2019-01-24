<?php

/**
 * PackageCategoryIdConstraintValidatoTest - Test Cases for Custom Form Constraint Validator Class
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace AppBundle\Tests\Validator\Constraints;

use AppBundle\Validator\Constraints\PackageCategoryIdConstraint;
use AppBundle\Validator\Constraints\PackageCategoryIdConstraintValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Class PackageCategoryIdConstraintValidatorTest
 *
 * @package AppBundle\Tests\Validator\Constraints
 */
class PackageCategoryIdConstraintValidatorTest extends TestCase
{
    /**
     * @var \AppBundle\Validator\Constraints\PackageCategoryIdConstraintValidator
     */
    private $packageCategoryIdConstraintValidator;

    /**
     * @var array
     */
    private $categories;

    /**
     * @var array
     */
    private $invalidateCategories;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $executionContextMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $constraintMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $constraintViolationBuilderMock;

    protected function setUp()
    {
        parent::setUp();

        $this->packageCategoryIdConstraintValidator = new PackageCategoryIdConstraintValidator();
        $this->executionContextMock = $this->getMockBuilder(ExecutionContextInterface::class)
            ->getMock();
        $this->constraintViolationBuilderMock = $this->getMockBuilder(ConstraintViolationBuilderInterface::class)->getMock();
        $this->constraintMock = $this->getMockBuilder(PackageCategoryIdConstraint::class)
            ->disableOriginalConstructor()
            ->setConstructorArgs([['categories' => $this->categories]])
            ->getMock();

        $this->categories = [2,4,6];
        $this->invalidateCategories = [5, 10, 15];
    }

    public function testBuildViolationWhenPackageCategoryIdIsInvalid()
    {
        $this->constraintMock
            ->expects($this->any())
            ->method('getPackageCategoryIds')
            ->willReturn([2,4,6]);

        $this->executionContextMock
            ->expects($this->once())
            ->method('buildViolation')
            ->with('Following category ids does not exists: {{ categories }}')
            ->willReturn($this->constraintViolationBuilderMock);

        $this->constraintViolationBuilderMock
            ->expects($this->once())
            ->method('setParameter')
            ->with('{{ categories }}', implode(', ', $this->invalidateCategories))
            ->willReturn($this->constraintViolationBuilderMock);

        $this->constraintViolationBuilderMock
            ->expects($this->once())
            ->method('addViolation')
            ->willReturn(null);

        $this->packageCategoryIdConstraintValidator->initialize($this->executionContextMock);
        $this->packageCategoryIdConstraintValidator->validate(array_merge($this->categories, $this->invalidateCategories), $this->constraintMock);
    }
}
