<?php

namespace Stitch\Queries;

use Stitch\Model;
use Stitch\DBAL\Builders\Query as Builder;
use Stitch\DBAL\Builders\Column;
use Stitch\Queries\Paths\Factory as PathFactory;
use Stitch\Queries\Paths\Path;
use Stitch\DBAL\Dispatcher;
use Stitch\Result\Set as ResultSet;
use Closure;

class Query
{
    protected $model;

    protected $builder;

    protected $relations = [];

    /**
     * Query constructor.
     * @param Model $model
     * @param Builder $builder
     */
    public function __construct(Model $model, Builder $builder)
    {
        $this->model = $model;
        $this->builder = $builder;
    }

    /**
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @return Builder
     */
    public function getBuilder()
    {
        return $this->builder;
    }

    /**
     * @param array ...$relations
     * @return $this
     */
    public function with(...$relations)
    {
        foreach ($relations as $relation) {
            $this->makeRelation(PathFactory::split($relation));
        }

        return $this;
    }

    /**
     * @param array ...$paths
     * @return $this
     */
    public function select(...$paths)
    {
        foreach ($paths as $path) {
            $this->apply($path, function (Query $query, string $column)
            {
                $query->addColumn($column);
            });
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function selectAll()
    {
        foreach ($this->model->getTable()->getColumns() as $column) {
            $this->addColumn($column->getName());
        }

        foreach ($this->relations as $relation) {
            $relation->selectAll();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function selectPrimaryKey()
    {
        $primaryKeyName = $this->model->getTable()->getPrimaryKey()->getName();

        if (!$this->builder->getSelection()->has($primaryKeyName)) {
            $this->addColumn($primaryKeyName);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function forceSelection()
    {
        count($this->builder->getSelection()->getColumns()) ? $this->selectPrimaryKey() : $this->selectAll();

        foreach ($this->relations as $relation) {
            $relation->forceSelection();
        }

        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function addColumn(string $name)
    {
        $table = $this->model->getTable()->getName();

        $this->builder->select($name, function (Column $column) use ($table, $name)
        {
            $column->alias("{$table}_{$name}");
        });

        return $this;
    }

    /**
     * @param string $type
     * @param string $column
     * @param string $operator
     * @param $value
     * @return $this
     */
    public function addCondition(string $type, string $column, string $operator, $value)
    {
        $this->builder->{$type}(
            "{$this->model->getTable()->getName()}.$column",
            $operator,
            $value
        );

        return $this;
    }

    /**
     * @param $type
     * @param array $argumants
     * @return $this
     */
    protected function applyCondition($type, array $argumants)
    {
        $path = array_shift($argumants);

        if (count($argumants) == 1) {
            $operator = '=';
            $value = $argumants[0];
        } else {
            list($operator, $value) = $argumants;
        }

        $this->apply($path, function (Query $query, string $column) use ($type, $operator, $value)
        {
            $query->addCondition(
                $type,
                $column,
                $operator,
                $value
            );
        });

        return $this;
    }

    /**
     * @param array ...$argumants
     * @return Query
     */
    public function where(...$argumants)
    {
        return $this->applyCondition('where', $argumants);
    }

    /**
     * @param array ...$argumants
     * @return Query
     */
    public function on(...$argumants)
    {
        return $this->applyCondition('on', $argumants);
    }


    /**
     * @param string $path
     * @param string $direction
     * @return $this
     */
    public function orderBy(string $path, string $direction = 'ASC')
    {
        $this->apply($path, function (Query $query, string $column) use ($direction)
        {
            $query->addOrderBy($column, $direction);
        });

        return $this;
    }

    /**
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function addOrderBy(string $column, string $direction = 'ASC')
    {
        $this->builder->orderBy("{$this->model->getTable()->getName()}.$column", $direction);

        return $this;
    }

    /**
     * @param array ...$arguments
     * @return $this
     */
    public function limit(...$arguments)
    {
        if (count($arguments) > 1) {
            $this->getRelation(PathFactory::split($arguments[0]))->setLimit($arguments[1]);
        } else {
            $this->setLimit($arguments[0]);
        }

        return $this;
    }

    /**
     * @param $number
     * @return $this
     */
    public function setLimit($number)
    {
        $this->builder->limit($number);

        return $this;
    }

    /**
     * @return \App\Orm\Collection
     */
    public function get()
    {
        $this->forceSelection();

        return $this->model->hydrateMany(
            new ResultSet(
                $this,
                Dispatcher::select($this->builder)
            )
        );
    }

    /**
     * @param string $path
     * @param Closure $callback
     */
    protected function apply(string $path, Closure $callback)
    {
        if (strstr($path, '.')) {
            $path = PathFactory::divide($this->model, $path);

            $callback($this->getRelation($path['relation']), $path['column']->implode());
        } else {
            $callback($this, $path);
        }
    }

    /**
     * @return array
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * @param string $path
     * @return Query
     */
    public function getRelation(Path $path)
    {
        if ( ! array_key_exists($path->first(), $this->relations)) {
            return $this->makeRelation($path);
        }

        $relation = $this->relations[$path->first()];

        if ($path->count() > 1) {
            return $relation->getRelation($path->after(0));
        }

        return $relation;
    }

    /**
     * @param string $path
     * @return Query
     */
    public function makeRelation(Path $path)
    {
        $name = $path->first();

        if ( ! array_key_exists($name, $this->relations)) {
            $this->relations[$name] = $this->model->getRelation($name)->query()->join($this);
        }

        if ($path->count() > 1) {
            return $this->relations[$name]->makeRelation($path->after(0));
        }

        return $this->relations[$name];
    }
}
