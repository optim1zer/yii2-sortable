<?php

namespace arogachev\sortable\behaviors;

use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;

/**
 * @property \yii\db\ActiveRecord $model
 * @property \yii\db\ActiveQuery $query
 */
abstract class BaseSortableBehavior extends Behavior
{
    /**
     * @var callable
     */
    public $scope;

    /**
     * @var array
     */
    public $sortableCondition = [];

    /**
     * @var boolean
     */
    public $prependAdded = false;

    /**
     * @var callable
     */
    public $access;

    /**
     * @var \yii\db\ActiveRecord
     */
    protected $_oldModel;


    /**
     * @return integer
     */
    abstract public function getSortablePosition();

    /**
     * @param mixed $pk
     */
    abstract public function moveBefore($pk);

    /**
     * @param mixed $pk
     */
    abstract public function moveAfter($pk);

    /**
     * @param $position
     * @return boolean
     * @throws InvalidParamException
     */
    public function moveToPosition($position)
    {
        $position = (int) $position;

        if ($position < 1 || $position > $this->getSortableCount()) {
            throw new InvalidParamException("Position must be a number between 1 and {$this->getSortableCount()}.");
        }

        // The model is in the same position
        if ($position == $this->getSortablePosition()) {
            return true;
        }

        return false;
    }

    public function moveBack()
    {
        $this->moveToPosition($this->getSortablePosition() + 1);
    }

    public function moveForward()
    {
        $this->moveToPosition($this->getSortablePosition() - 1);
    }

    public function moveAsFirst()
    {
        $this->moveToPosition(1);
    }

    public function moveAsLast()
    {
        $this->moveToPosition($this->getSortableCount());
    }

    /**
     * @param boolean $useOldAttributes
     * @return boolean
     */
    public function isSortable($useOldAttributes = false)
    {
        if (!$this->sortableCondition) {
            return true;
        }

        $values = $useOldAttributes ? $this->_oldModel->attributes : $this->model->attributes;
        $sortableValues = array_intersect_key($values, $this->sortableCondition);

        foreach ($this->sortableCondition as $name => $value) {
            if ($sortableValues[$name] != $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return boolean
     */
    public function isSortableByCurrentUser()
    {
        return !$this->access ? true : call_user_func($this->access);
    }

    /**
     * @return integer
     */
    public function getSortableCount()
    {
        return $this->query->orderBy([])->count();
    }

    /**
     * @return array
     * @throws InvalidConfigException
     */
    public function getSortableScopeCondition()
    {
        if (!$this->scope) {
            return [];
        }

        /* @var $scopeQuery \yii\db\ActiveQuery */
        $scopeQuery = call_user_func($this->scope, $this->model);

        if (!is_array($scopeQuery->where)) {
            throw new InvalidConfigException('"where" part of $scope query must be specified as array.');
        }

        return $scopeQuery->where;
    }

    /**
     * @return \yii\db\ActiveRecord
     */
    protected function getModel()
    {
        return $this->owner;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    protected function getQuery()
    {
        $model = $this->model;

        return $model::find()
            ->select($model->primaryKey())
            ->andFilterWhere($this->getSortableScopeCondition())
            ->andFilterWhere($this->sortableCondition);
    }

    /**
     * @return array|\yii\db\ActiveRecord[]
     */
    protected function getAllModels()
    {
        return $this->query->all();
    }

    /**
     * @return null|boolean
     */
    protected function getSortableDiff()
    {
        $isSortableBefore = $this->isSortable(true);
        $isSortableAfter = $this->isSortable();

        if (!$isSortableBefore && $isSortableAfter) {
            return true;
        } elseif ($isSortableBefore && !$isSortableAfter) {
            return false;
        } else {
            return null;
        }
    }
}
