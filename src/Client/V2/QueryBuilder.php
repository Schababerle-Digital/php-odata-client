<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Client\V2;

use SchababerleDigital\OData\Client\Common\AbstractQueryBuilder;
use SchababerleDigital\OData\Exception\ODataRequestException;

/**
 * Client V2 specific query builder.
 * Handles V2-specific query parameters like $inlinecount.
 */
class QueryBuilder extends AbstractQueryBuilder
{
    public const PARAM_INLINE_COUNT = '$inlinecount'; // V2 specific

    /**
     * {@inheritDoc}
     * For Client V2, this sets the $inlinecount parameter.
     */
    public function count(bool $includeCount = true): self
    {
        if ($includeCount) {
            $this->params[self::PARAM_INLINE_COUNT] = 'allpages';
        } else {
            unset($this->params[self::PARAM_INLINE_COUNT]);
        }
        // Unset V4 $count if it was somehow set by a generic call
        unset($this->params[parent::PARAM_COUNT]);
        return $this;
    }

    /**
     * {@inheritDoc}
     * The $search parameter is not standard in Client V2.
     * @throws ODataRequestException if $search is attempted for V2.
     */
    public function search(string $searchTerm): self
    {
        throw new ODataRequestException('$search query option is not supported in Client V2.');
    }
}