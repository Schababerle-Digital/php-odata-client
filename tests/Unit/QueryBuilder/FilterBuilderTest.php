<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Tests\Unit\QueryBuilder;

use PHPUnit\Framework\TestCase;
use SchababerleDigital\OData\QueryBuilder\FilterBuilder;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use InvalidArgumentException;

/**
 * @covers \SchababerleDigital\OData\QueryBuilder\FilterBuilder
 */
class FilterBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function newCreatesInstance(): void
    {
        $this->assertInstanceOf(FilterBuilder::class, FilterBuilder::new());
    }

    /**
     * @test
     */
    public function buildEmptyReturnsEmptyString(): void
    {
        $this->assertEquals('', FilterBuilder::new()->build());
    }

    /**
     * @test
     * @dataProvider simpleComparisonConditionProvider
     */
    public function simpleComparisonConditionsAreBuiltCorrectly(string $method, mixed $value, string $operator, string $formattedValue): void
    {
        $expected = 'FieldName ' . $operator . ' ' . $formattedValue;
        $actual = FilterBuilder::new()->where('FieldName')->{$method}($value)->build();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Data provider for simple comparison conditions.
     * @return array<string, array<mixed>>
     */
    public static function simpleComparisonConditionProvider(): array
    {
        return [
            'equals string' => ['equals', 'Test', 'eq', "'Test'"],
            'equals integer' => ['equals', 123, 'eq', '123'],
            'notEquals string' => ['notEquals', 'Abc', 'ne', "'Abc'"],
            'greaterThan number' => ['greaterThan', 99.9, 'gt', '99.9'],
            'greaterThanOrEquals number' => ['greaterThanOrEquals', 100, 'ge', '100'],
            'lessThan number' => ['lessThan', 5, 'lt', '5'],
            'lessThanOrEquals number' => ['lessThanOrEquals', 4.5, 'le', '4.5'],
            'equals boolean true' => ['equals', true, 'eq', 'true'],
            'equals boolean false' => ['equals', false, 'eq', 'false'],
            'equals null' => ['equals', null, 'eq', 'null'],
        ];
    }

    /**
     * @test
     */
    public function dateTimeValueIsFormattedCorrectly(): void
    {
        $date = new DateTimeImmutable('2023-05-15T10:30:00', new DateTimeZone('UTC'));
        $expected = "CreationDate eq 2023-05-15T10:30:00Z";
        $actual = FilterBuilder::new()->where('CreationDate')->equals($date)->build();
        $this->assertEquals($expected, $actual);

        $dateNonUtc = new DateTime('2024-01-20T14:00:00', new DateTimeZone('Europe/Berlin'));
        // It should be converted to UTC 'Z' format by our formatter
        $expectedNonUtc = "EventDate eq 2024-01-20T13:00:00Z"; // 14:00 Berlin is 13:00 UTC in Jan
        $actualNonUtc = FilterBuilder::new()->where('EventDate')->equals($dateNonUtc)->build();
        $this->assertEquals($expectedNonUtc, $actualNonUtc);
    }

    /**
     * @test
     */
    public function stringValueWithSingleQuoteIsFormattedCorrectly(): void
    {
        $expected = "Name eq 'O''Malley'";
        $actual = FilterBuilder::new()->where('Name')->equals("O'Malley")->build();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * @dataProvider stringFunctionConditionProvider
     */
    public function stringFunctionConditionsAreBuiltCorrectly(string $method, string $value, string $expectedFunctionCall): void
    {
        $expected = $expectedFunctionCall;
        $actual = FilterBuilder::new()->where('TextField')->{$method}($value)->build();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Data provider for string function conditions.
     * @return array<string, array<string>>
     */
    public static function stringFunctionConditionProvider(): array
    {
        return [
            'startsWith' => ['startsWith', 'Prefix', "startswith(TextField,'Prefix')"],
            'endsWith' => ['endsWith', 'Suffix', "endswith(TextField,'Suffix')"],
            'contains (V4)' => ['contains', 'infix', "contains(TextField,'infix')"],
            'substringOf (V2)' => ['substringOf', 'piece', "substringof('piece',TextField)"],
        ];
    }

    /**
     * @test
     */
    public function genericFuncMethodBuildsCorrectlyAndCanBeChained(): void
    {
        // Example: length(ProductName) eq 10
        $expected = "length(ProductName) eq 10";
        $actual = FilterBuilder::new()
            ->func('length', 'ProductName') // func sets the "currentField" to the function call itself
            ->equals(10) // this "equals" applies to the result of length(ProductName)
            ->build();
        $this->assertEquals($expected, $actual);

        // Example: tolower(Name) eq 'test'
        $expectedToLower = "tolower(Name) eq 'test'";
        $actualToLower = FilterBuilder::new()->func('tolower', 'Name')->equals('test')->build();
        $this->assertEquals($expectedToLower, $actualToLower);
    }

    /**
     * @test
     */
    public function andOperatorCombinesConditions(): void
    {
        $expected = "Status eq 1 and Amount gt 100";
        $actual = FilterBuilder::new()
            ->where('Status')->equals(1)
            ->and()
            ->where('Amount')->greaterThan(100)
            ->build();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function orOperatorCombinesConditions(): void
    {
        $expected = "Category eq 'A' or Category eq 'B'";
        $actual = FilterBuilder::new()
            ->where('Category')->equals('A')
            ->or()
            ->where('Category')->equals('B')
            ->build();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function groupCreatesParenthesizedExpression(): void
    {
        $expected = "(Name eq 'Test' and Price lt 10)";
        $actual = FilterBuilder::new()
            ->group(static fn(FilterBuilder $group) => $group
                ->where('Name')->equals('Test')
                ->and()
                ->where('Price')->lessThan(10)
            )
            ->build();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function nestedGroupsAreBuiltCorrectly(): void
    {
        $expected = "Type eq 'X' and (Status eq 1 or (Status eq 2 and IsActive eq true))";
        $actual = FilterBuilder::new()
            ->where('Type')->equals('X')
            ->and()
            ->group(static fn(FilterBuilder $g1) => $g1
                ->where('Status')->equals(1)
                ->or()
                ->group(static fn(FilterBuilder $g2) => $g2
                    ->where('Status')->equals(2)
                    ->and()
                    ->where('IsActive')->equals(true)
                )
            )
            ->build();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function notOperatorNegatesSimpleCondition(): void
    {
        // The `not` implementation in FilterBuilder wraps the immediate condition if negateNextCondition is true.
        $expected = "not (IsArchived eq true)";
        $actual = FilterBuilder::new()->not()->where('IsArchived')->equals(true)->build();
        // Note: This was simplified. A more direct "not IsArchived eq true" is also valid Client.
        // The builder's current simple "not" prepends "not " if not immediately before a where().
        // Let's test the actual behavior of the `not()->where()->equals()` chain.
        // The current implementation of `addCondition` wraps with `not (...)` if `negateNextCondition` is true.
        // `not()` sets `negateNextCondition` if it's followed by `where()`.
        $this->assertEquals($expected, $actual);

        $expectedAndNot = "Status eq 'A' and not (Priority eq 1)";
        $actualAndNot = FilterBuilder::new()
            ->where('Status')->equals('A')
            ->and()
            ->not()
            ->where('Priority')->equals(1)
            ->build();
        $this->assertEquals($expectedAndNot, $actualAndNot);
    }

    /**
     * @test
     */
    public function notOperatorPrependedCorrectly(): void
    {
        // This tests when 'not' is used before a function or a group via notGroup
        $expected = "not (startswith(Name,'A'))";
        $actual = FilterBuilder::new()->not()->where('Name')->startsWith('A')->build();
        $this->assertEquals($expected, $actual); // Assuming addFunctionCondition also checks negateNextCondition
    }

    /**
     * @test
     */
    public function complexChainOfOperationsBuildsCorrectly(): void
    {
        $expected = "((Type eq 'A' and Value gt 10) or (Type eq 'B' and contains(Name,'Draft'))) and not (Status eq 'Archived')";
        $actual = FilterBuilder::new()
            ->group(static fn(FilterBuilder $g1) => $g1
                ->group(static fn(FilterBuilder $g2) => $g2
                    ->where('Type')->equals('A')
                    ->and()
                    ->where('Value')->greaterThan(10)
                )
                ->or()
                ->group(static fn(FilterBuilder $g3) => $g3
                    ->where('Type')->equals('B')
                    ->and()
                    ->where('Name')->contains('Draft')
                )
            )
            ->and()
            ->not()
            ->where('Status')->equals('Archived')
            ->build();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function callingConditionWithoutWhereThrowsLogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A field must be specified using where() before adding a condition or function.');
        FilterBuilder::new()->equals('someValue');
    }

    /**
     * @test
     */
    public function callingFunctionLikeStartsWithWithoutWhereThrowsLogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A field must be specified using where() before adding a condition or function.');
        FilterBuilder::new()->startsWith('someValue');
    }

    /**
     * @test
     */
    public function unsupportedValueTypeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported value type for Client filter: array');
        FilterBuilder::new()->where('Field')->equals(['array', 'is not supported']);
    }
}