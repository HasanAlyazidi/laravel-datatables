<?php

namespace HasanAlyazidi\DataTables;

use InvalidArgumentException;

/**
 * One entry of a table's default order: which column, which direction.
 *
 * Using ColumnOrder::desc('created_at') instead of ['created_at', 'desc']
 * makes the direction impossible to misspell — a bare 'dsc' would quietly
 * become ascending. The array form still works and is validated the same.
 */
final class ColumnOrder
{
    const ASC = 'asc';

    const DESC = 'desc';

    /**
     * @var string
     */
    private $column;

    /**
     * @var string
     */
    private $direction;

    public function __construct(string $column, string $direction)
    {
        if ($column === '') {
            throw new InvalidArgumentException('A default order needs a column name.');
        }

        $direction = strtolower($direction);

        if ($direction !== self::ASC && $direction !== self::DESC) {
            throw new InvalidArgumentException(
                'Unknown order direction ['.$direction.']. Use ColumnOrder::ASC or ColumnOrder::DESC.'
            );
        }

        $this->column = $column;
        $this->direction = $direction;
    }

    /**
     * @return static
     */
    public static function asc(string $column): self
    {
        return new self($column, self::ASC);
    }

    /**
     * @return static
     */
    public static function desc(string $column): self
    {
        return new self($column, self::DESC);
    }

    /**
     * Accept either a ColumnOrder or a [column, direction] pair, so a table
     * can mix both forms in defaultOrder().
     *
     * @param  mixed  $entry
     * @return static
     */
    public static function make($entry): self
    {
        if ($entry instanceof self) {
            return $entry;
        }

        if (is_array($entry) && isset($entry[0], $entry[1]) && is_string($entry[0]) && is_string($entry[1])) {
            return new self($entry[0], $entry[1]);
        }

        throw new InvalidArgumentException(
            'A default order entry must be a ColumnOrder or a [column, direction] pair.'
        );
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }
}
