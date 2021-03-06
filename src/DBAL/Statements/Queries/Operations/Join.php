<?php

namespace Stitch\DBAL\Statements\Queries\Operations;

use Stitch\DBAL\Builders\Join as Builder;
use Stitch\DBAL\Statements\Queries\Fragments\Expression;
use Stitch\DBAL\Statements\Statement;

/**
 * Class Join
 * @package Stitch\DBAL\Statements\Queries\Operations
 */
class Join extends Statement
{
    /**
     * @var Builder
     */
    protected $builder;

    /**
     * Join constructor.
     * @param Builder $builder
     */
    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * @return void
     */
    public function evaluate()
    {
        $schema = $this->builder->getSchema();

        $this->push(
            "{$this->builder->getType()} JOIN {$schema->getConnection()->getDatabase()}.{$schema->getName()} ON"
        );

        $this->push(
            new Expression($this->builder->getConditions())
        );

        foreach ($this->builder->getJoins() as $join) {
            $this->push(
                new static($join)
            );
        }
    }
}
