<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\QueryBuilder;

use Closure;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A fluent builder for creating Client $filter query expressions.
 */
class FilterBuilder
{
    /** @var array<int, string> */
    protected array $parts = [];
    protected ?string $currentField = null;
    protected bool $negateNextCondition = false;
    protected bool $applyNotToNextUnit = false;

    /**
     * Private constructor, use new() static method.
     */
    private function __construct()
    {
    }

    /**
     * Creates a new FilterBuilder instance.
     * @return self
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * Specifies the field for the next condition.
     * @param string $field The name of the field.
     * @return self
     */
    public function where(string $field): self
    {
        $this->currentField = $field;
        return $this;
    }

    /**
     * Adds an 'equals' (eq) condition.
     * @param mixed $value The value to compare against.
     * @return self
     */
    public function equals(mixed $value): self
    {
        return $this->addCondition('eq', $value);
    }

    /**
     * Adds a 'not equals' (ne) condition.
     * @param mixed $value The value to compare against.
     * @return self
     */
    public function notEquals(mixed $value): self
    {
        return $this->addCondition('ne', $value);
    }

    /**
     * Adds a 'greater than' (gt) condition.
     * @param mixed $value The value to compare against.
     * @return self
     */
    public function greaterThan(mixed $value): self
    {
        return $this->addCondition('gt', $value);
    }

    /**
     * Adds a 'greater than or equals' (ge) condition.
     * @param mixed $value The value to compare against.
     * @return self
     */
    public function greaterThanOrEquals(mixed $value): self
    {
        return $this->addCondition('ge', $value);
    }

    /**
     * Adds a 'less than' (lt) condition.
     * @param mixed $value The value to compare against.
     * @return self
     */
    public function lessThan(mixed $value): self
    {
        return $this->addCondition('lt', $value);
    }

    /**
     * Adds a 'less than or equals' (le) condition.
     * @param mixed $value The value to compare against.
     * @return self
     */
    public function lessThanOrEquals(mixed $value): self
    {
        return $this->addCondition('le', $value);
    }

    /**
     * Adds a 'startswith' function call.
     * Requires a field to be set via where().
     * @param string $value The string value the field should start with.
     * @return self
     */
    public function startsWith(string $value): self
    {
        $this->ensureFieldIsSet();
        return $this->addFunctionCondition('startswith', [$this->currentField, $value]);
    }

    /**
     * Adds an 'endswith' function call.
     * Requires a field to be set via where().
     * @param string $value The string value the field should end with.
     * @return self
     */
    public function endsWith(string $value): self
    {
        $this->ensureFieldIsSet();
        return $this->addFunctionCondition('endswith', [$this->currentField, $value]);
    }

    /**
     * Adds a 'contains' function call (Client V4).
     * For Client V2, use substringof().
     * Requires a field to be set via where().
     * @param string $value The string value the field should contain.
     * @return self
     */
    public function contains(string $value): self
    {
        $this->ensureFieldIsSet();
        // Note: Client V2 uses substringof(PropertyValue, 'substring')
        // Client V4 uses contains(PropertyValue, 'substring')
        // This builder aims for V4 `contains` by default.
        // For a V2 specific builder, this might be overridden or a specific substringof method added.
        return $this->addFunctionCondition('contains', [$this->currentField, $value]);
    }

    /**
     * Adds a 'substringof' function call (Client V2).
     * Requires a field to be set via where().
     * @param string $value The string value that should be a substring of the field.
     * @return self
     */
    public function substringOf(string $value): self
    {
        $this->ensureFieldIsSet();
        return $this->addFunctionCondition('substringof', [$value, $this->currentField]);
    }


    /**
     * Adds a generic function call to the filter.
     * Example: $filterBuilder->fn('length', $this->currentField)->equals(5);
     * Example: $filterBuilder->fn('date', $this->currentField)->equals('2023-01-01');
     *
     * @param string $functionName The name of the Client function.
     * @param mixed ...$args Arguments for the function. Can be field names (strings) or literal values.
     * Field names passed here will not be auto-quoted if they are property paths.
     * @return self
     */
    public function func(string $functionName, ...$args): self
    {
        $formattedFuncArgs = [];
        foreach ($args as $arg) {
            // Unterscheide zwischen Feldnamen (nicht quotieren) und Literalen (quotieren)
            // Einfache Heuristik: Wenn es wie ein einfaches Wort aussieht, ist es ein Feld.
            if (is_string($arg) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $arg) && !in_array(strtolower($arg), ['true', 'false', 'null'])) {
                $formattedFuncArgs[] = $arg;
            } else {
                $formattedFuncArgs[] = $this->formatValue($arg);
            }
        }

        // Setze currentField auf den Funktionsausdruck.
        // addCondition wird dies als linke Seite des Vergleichs verwenden.
        $this->currentField = $functionName . '(' . implode(',', $formattedFuncArgs) . ')';
        // Hier KEIN appendPart() für den Funktionsaufruf selbst.
        $this->negateNextCondition = false; // Zurücksetzen für den Fall, dass not() vorher aufgerufen wurde
        return $this;
    }

    /**
     * Adds an 'and' logical operator.
     * @return self
     */
    public function and(): self
    {
        $this->appendPart('and');
        return $this;
    }

    /**
     * Adds an 'or' logical operator.
     * @return self
     */
    public function or(): self
    {
        $this->appendPart('or');
        return $this;
    }

    /**
     * Adds a 'not' logical operator to negate the next condition or group.
     * @return self
     */
    public function not(): self
    {
        $this->applyNotToNextUnit = true;
        return $this;
    }

    /**
     * Adds a group of conditions.
     * @param Closure $callback A closure that receives a new FilterBuilder instance for the group.
     * Example: fn(FilterBuilder $group) => $group->where('Field')->equals('Value')
     * @return self
     */
    public function group(Closure $callback): self
    {
        $groupBuilder = new self();
        $callback($groupBuilder);
        $groupContent = $groupBuilder->build();

        if (!empty($groupContent)) {
            if ($this->applyNotToNextUnit) {
                $this->appendPart('not (' . $groupContent . ')');
                $this->applyNotToNextUnit = false;
            } else {
                $this->appendPart('(' . $groupContent . ')');
            }
        }
        return $this;
    }


    /**
     * Builds the Client $filter query string.
     * @return string The constructed filter string.
     */
    public function build(): string
    {
        return implode(' ', $this->parts);
    }

    /**
     * Ensures that a field context has been set using where().
     * @throws \LogicException
     */
    protected function ensureFieldIsSet(): void
    {
        if ($this->currentField === null) {
            throw new \LogicException('A field must be specified using where() before adding a condition or function.');
        }
    }

    /**
     * Appends a part to the query string.
     * @param string $part
     */
    protected function appendPart(string $part): void
    {
        if ($this->negateNextCondition && !in_array($part, ['and', 'or', '(', ')'])) {
            // This logic needs refinement. Negation should ideally wrap the condition.
            // For now, this is a simplified approach. A proper AST would be better.
        }
        $this->parts[] = $part;
    }

    /**
     * Adds a standard condition (field operator value).
     * @param string $operator The Client comparison operator (e.g., 'eq', 'lt').
     * @param mixed $value The value to compare against.
     * @return self
     */
    protected function addCondition(string $operator, mixed $value): self
    {
        $this->ensureFieldIsSet();
        $condition = $this->currentField . ' ' . $operator . ' ' . $this->formatValue($value);
        if ($this->applyNotToNextUnit) {
            // Korrektes Klammern für not
            $this->appendPart('not (' . $condition . ')');
            $this->applyNotToNextUnit = false;
        } else {
            $this->appendPart($condition);
        }
        $this->currentField = null;
        return $this;
    }

    /**
     * Adds a function-based condition.
     * @param string $functionName The Client function name.
     * @param array<int, mixed> $args Arguments for the function. Field names should be passed as is, values will be formatted.
     * @return self
     */
    protected function addFunctionCondition(string $functionName, array $args): self
    {
        $formattedArgs = [];
        foreach ($args as $index => $arg) {
            if (is_string($arg) && (($functionName === 'substringof' && $index === 1) || ($arg === $this->currentField && $index === 0 && $functionName !== 'substringof'))) {
                // Argument ist ein Feldname für substringof(literal, field) oder das Hauptfeld für andere Funktionen
                $formattedArgs[] = $arg;
            } else {
                $formattedArgs[] = $this->formatValue($arg);
            }
        }
        $condition = $functionName . '(' . implode(',', $formattedArgs) . ')';

        if ($this->applyNotToNextUnit) {
            $this->appendPart('not (' . $condition . ')');
            $this->applyNotToNextUnit = false;
        } else {
            $this->appendPart($condition);
        }
        $this->currentField = null;
        return $this;
    }

    /**
     * Formats a PHP value into its Client literal representation.
     * @param mixed $value The value to format.
     * @return string The Client literal string.
     * @throws InvalidArgumentException For unsupported types.
     */
    protected function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            // Client strings are single-quoted, with internal single quotes doubled.
            return "'" . str_replace("'", "''", $value) . "'";
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if ($value instanceof DateTimeInterface) {
            // Client V4 recommends ISO 8601 format for datetimeoffset.
            // Example: datetimeoffset'2000-12-12T12:00:00Z' or just the literal for V4
            // For simplicity, outputting ISO 8601. V4 clients usually handle this.
            // V2 might require datetime'...' or guid'...' prefixes.
            return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'); // UTC Zulu time for compatibility
        }
        // GUIDs would need specific formatting if not passed as strings: guid'...'
        // Enums would be Namespace.EnumType'MemberName'

        throw new InvalidArgumentException('Unsupported value type for Client filter: ' . gettype($value));
    }
}