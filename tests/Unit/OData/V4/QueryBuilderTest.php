<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Tests\Unit\OData\V4;

use PHPUnit\Framework\TestCase;
use SchababerleDigital\OData\Client\V4\QueryBuilder as V4QueryBuilder;

/**
 * @covers \SchababerleDigital\OData\Client\V4\QueryBuilder
 * @covers \SchababerleDigital\OData\Client\Common\AbstractQueryBuilder
 */
class QueryBuilderTest extends TestCase
{
    private V4QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryBuilder = new V4QueryBuilder();
    }

    /**
     * @test
     */
    public function countTrueSetsCountTrueForV4(): void
    {
        $this->queryBuilder->count(true);
        $params = $this->queryBuilder->getQueryParams();

        $this->assertArrayHasKey(V4QueryBuilder::PARAM_COUNT, $params);
        $this->assertEquals('true', $params[V4QueryBuilder::PARAM_COUNT]);
        $this->assertArrayNotHasKey('$inlinecount', $params); // Ensure V2 $inlinecount is not present
    }

    /**
     * @test
     */
    public function countFalseSetsCountFalseForV4(): void
    {
        $this->queryBuilder->count(false);
        $params = $this->queryBuilder->getQueryParams();

        $this->assertArrayHasKey(V4QueryBuilder::PARAM_COUNT, $params);
        $this->assertEquals('false', $params[V4QueryBuilder::PARAM_COUNT]);
    }

    /**
     * @test
     */
    public function searchIsAvailableAndSetsParamForV4(): void
    {
        $searchTerm = 'test electronics';
        $this->queryBuilder->search($searchTerm);
        $params = $this->queryBuilder->getQueryParams();

        $this->assertArrayHasKey(V4QueryBuilder::PARAM_SEARCH, $params);
        $this->assertEquals($searchTerm, $params[V4QueryBuilder::PARAM_SEARCH]);
    }

    /**
     * @test
     */
    public function basicQueryBuildingStillWorksFromAbstract(): void
    {
        $this->queryBuilder
            ->select(['Name', 'Price'])
            ->filter("Price gt 100")
            ->top(20)
            ->skip(10)
            ->orderBy('Price', 'desc');

        $expectedQueryString = '?%24select=Name%2CPrice&%24filter=Price%20gt%20100&%24top=20&%24skip=10&%24orderby=Price%20desc';
        $this->assertEquals($expectedQueryString, $this->queryBuilder->getQueryString());
    }
}