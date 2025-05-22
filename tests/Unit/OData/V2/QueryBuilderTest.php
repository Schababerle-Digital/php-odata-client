<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Tests\Unit\OData\V2;

use PHPUnit\Framework\TestCase;
use SchababerleDigital\OData\Client\V2\QueryBuilder as V2QueryBuilder;
use SchababerleDigital\OData\Exception\ODataRequestException;

/**
 * @covers \SchababerleDigital\OData\Client\V2\QueryBuilder
 * @covers \SchababerleDigital\OData\Client\Common\AbstractQueryBuilder
 */
class QueryBuilderTest extends TestCase
{
    private V2QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryBuilder = new V2QueryBuilder();
    }

    /**
     * @test
     */
    public function countTrueSetsInlineCountAllPagesForV2(): void
    {
        $this->queryBuilder->count(true);
        $params = $this->queryBuilder->getQueryParams();

        $this->assertArrayHasKey(V2QueryBuilder::PARAM_INLINE_COUNT, $params);
        $this->assertEquals('allpages', $params[V2QueryBuilder::PARAM_INLINE_COUNT]);
        $this->assertArrayNotHasKey(V2QueryBuilder::PARAM_COUNT, $params); // Ensure V4 $count is not present
    }

    /**
     * @test
     */
    public function countFalseRemovesInlineCountForV2(): void
    {
        // First set it to true, then to false
        $this->queryBuilder->count(true);
        $this->queryBuilder->count(false);
        $params = $this->queryBuilder->getQueryParams();

        $this->assertArrayNotHasKey(V2QueryBuilder::PARAM_INLINE_COUNT, $params);
        $this->assertArrayNotHasKey(V2QueryBuilder::PARAM_COUNT, $params);
    }

    /**
     * @test
     */
    public function searchThrowsODataRequestExceptionForV2(): void
    {
        $this->expectException(ODataRequestException::class);
        $this->expectExceptionMessage('$search query option is not supported in Client V2.');

        $this->queryBuilder->search('test term');
    }

    /**
     * @test
     */
    public function basicQueryBuildingStillWorksFromAbstract(): void
    {
        $this->queryBuilder
            ->select(['Name', 'Category'])
            ->filter("Price lt 100")
            ->top(10)
            ->skip(5)
            ->orderBy('Name', 'asc');

        $expectedQueryString = '?%24select=Name%2CCategory&%24filter=Price%20lt%20100&%24top=10&%24skip=5&%24orderby=Name%20asc';
        $this->assertEquals($expectedQueryString, $this->queryBuilder->getQueryString());
    }

    /**
     * @test
     */
    public function settingBothV2CountAndOtherParamsWorks(): void
    {
        $this->queryBuilder
            ->select('ID')
            ->count(true) // V2 $inlinecount=allpages
            ->top(5);

        $params = $this->queryBuilder->getQueryParams();
        $this->assertEquals(['$select' => 'ID', '$inlinecount' => 'allpages', '$top' => 5], $params);

        $queryString = $this->queryBuilder->getQueryString();
        // Order of http_build_query might vary, so check for parts
        $this->assertStringContainsString('%24select=ID', $queryString);
        $this->assertStringContainsString('%24inlinecount=allpages', $queryString);
        $this->assertStringContainsString('%24top=5', $queryString);
    }
}