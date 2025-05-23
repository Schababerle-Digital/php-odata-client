<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Client\Common;

use SchababerleDigital\OData\Contract\QueryBuilderInterface;
use InvalidArgumentException;

/**
 * Provides common functionality for Client query builders.
 * Specific Client versions (V2, V4) will extend this class.
 */
abstract class AbstractQueryBuilder implements QueryBuilderInterface
{
    /** @var array<string, string|int|bool> */
    protected array $params = [];
    protected ?string $entitySetContext = null;

    public const PARAM_SELECT = '$select';
    public const PARAM_FILTER = '$filter';
    public const PARAM_ORDERBY = '$orderby';
    public const PARAM_TOP = '$top';
    public const PARAM_SKIP = '$skip';
    public const PARAM_EXPAND = '$expand';
    public const PARAM_COUNT = '$count';
    public const PARAM_SEARCH = '$search'; // Client V4
    public const PARAM_FORMAT = '$format';

    /**
     * {@inheritDoc}
     */
    public function select(string|array $fields): self
    {
        $this->params[self::PARAM_SELECT] = is_array($fields) ? implode(',', $fields) : $fields;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function filter(string $expression): self
    {
        $this->params[self::PARAM_FILTER] = $expression;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'])) {
            throw new InvalidArgumentException('Order by direction must be "asc" or "desc".');
        }
        $this->params[self::PARAM_ORDERBY] = isset($this->params[self::PARAM_ORDERBY])
            ? $this->params[self::PARAM_ORDERBY] . ',' . $field . ' ' . $direction
            : $field . ' ' . $direction;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function top(int $number): self
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Top value cannot be negative.');
        }
        $this->params[self::PARAM_TOP] = $number;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function skip(int $number): self
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Skip value cannot be negative.');
        }
        $this->params[self::PARAM_SKIP] = $number;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function expand(string|array $relations): self
    {
        $this->params[self::PARAM_EXPAND] = is_array($relations) ? implode(',', $relations) : $relations;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function count(bool $includeCount = true): self
    {
        $this->params[self::PARAM_COUNT] = $includeCount ? 'true' : 'false'; // Client V4 expects true/false as string
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $searchTerm): self
    {
        // Client V4 specific, may need adjustment or override in V2 builder
        $this->params[self::PARAM_SEARCH] = $searchTerm;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function format(string $format): self
    {
        $this->params[self::PARAM_FORMAT] = $format;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function custom(string $paramName, string $paramValue): self
    {
        if (str_starts_with('<span class="math-inline">' . $paramName, '</span>')) {
        throw new InvalidArgumentException('Custom parameter name should not start with "$".');
    }
        $this->params[$paramName] = $paramValue;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getQueryString(): string
    {
        if (empty($this->params)) {
            return '';
        }
        return '?' . http_build_query($this->params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * {@inheritDoc}
     */
    public function getQueryParams(): array
    {
        return $this->params;
    }

    /**
     * {@inheritDoc}
     */
    public function setEntitySet(string $entitySet): self
    {
        $this->entitySetContext = $entitySet;
        return $this;
    }

    /**
     * Resets all query parameters.
     * @return self
     */
    public function reset(): self
    {
        $this->params = [];
        return $this;
    }
}
