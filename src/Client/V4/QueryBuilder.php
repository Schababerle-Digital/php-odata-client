<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Client\V4;

use SchababerleDigital\OData\Client\Common\AbstractQueryBuilder;

/**
 * Client V4 specific query builder.
 * The AbstractQueryBuilder is largely V4 compliant for common operations.
 * This class can be extended for more advanced V4-only query features if needed.
 */
class QueryBuilder extends AbstractQueryBuilder
{
    // The AbstractQueryBuilder's count() method already produces $count=true/false
    // which is V4 compliant. The search() method is also V4 compliant.
    // No specific overrides are immediately necessary for basic V4 query options
    // unless advanced V4 features (e.g., specific lambda operators not easily
    // representable as strings, or $apply) are to be supported with dedicated methods.

    // For example, if you wanted to add a specific V4 $apply method:
    /*
    public function apply(string $applyExpression): self
    {
        $this->params['$apply'] = $applyExpression;
        return $this;
    }
    */
}