<?php

namespace Stitch\Relations;

use Stitch\Queries\Joins\Has as Join;
use Stitch\Records\Relations\Collection as RecordCollection;
use Stitch\Records\Relations\BelongsTo;
use Stitch\Schema\ForeignKey;

/**
 * Class Has
 * @package Stitch\Relations
 */
class Has extends Relation
{
    /**
     * @var ForeignKey|null
     */
    protected $foreignKey;

    /**
     * @param string $column
     * @return $this
     */
    public function foreignKey(string $column)
    {
        $foreignTable = $this->getForeignModel()->getTable();

        $this->foreignKey = $foreignTable->getForeignKeyFrom($foreignTable->getColumn($column));

        return $this;
    }

    /**
     * @return $this
     */
    public function pullKeys()
    {
        $this->foreignKey = $this->getForeignModel()->getTable()->getForeignKeyFor(
            $this->localModel->getTable()->getPrimaryKey()
        );

        return $this;
    }

    /**
     * @return mixed
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * @return bool
     */
    public function hasKeys()
    {
        return $this->foreignKey !== null;
    }

    /**
     * @return mixed|Join
     */
    public function join()
    {
        return new Join($this->getForeignModel(), $this->joinBuilder(), $this);
    }

    /**
     * @return mixed|RecordCollection
     */
    public function make()
    {
        return new RecordCollection($this->getForeignModel());
    }

    /**
     * @param array $attributes
     * @return BelongsTo
     */
    public function record(array $attributes = [])
    {
        return (new BelongsTo($this->getForeignModel(), $this))->fill($attributes);
    }
}