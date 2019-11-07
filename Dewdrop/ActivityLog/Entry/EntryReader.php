<?php

namespace Dewdrop\ActivityLog\Entry;

use Countable;
use Dewdrop\ActivityLog\DbGateway;
use Dewdrop\ActivityLog\Entity;
use Dewdrop\ActivityLog\Exception\HandlerNotFound;
use Dewdrop\ActivityLog\Exception\InvalidEntity;
use Dewdrop\ActivityLog\Exception\InvalidEntryReaderOrder;
use Dewdrop\ActivityLog\Handler\HandlerInterface;
use Dewdrop\ActivityLog\HandlerResolver;
use Dewdrop\SetOptionsTrait;
use IteratorAggregate;

class EntryReader implements IteratorAggregate, Countable
{
    use SetOptionsTrait;

    const ORDER_DESC = 'desc';

    const ORDER_ASC = 'asc';

    /**
     * @var DbGateway
     */
    protected $dbGateway;

    /**
     * @var HandlerResolver
     */
    protected $handlerResolver;

    /**
     * @var array
     */
    protected $handlers = [];

    /**
     * @var array
     */
    protected $entities = [];

    /**
     * @var int
     */
    protected $limit = null;

    /**
     * @var int
     */
    protected $offset = null;

    /**
     * @var string
     */
    protected $order = 'desc';

    /**
     * @var array
     */
    protected $entries = null;

    /**
     * @var int
     */
    protected $totalCount = 0;

    public function __construct(DbGateway $dbGateway, HandlerResolver $handlerResolver)
    {
        $this->dbGateway       = $dbGateway;
        $this->handlerResolver = $handlerResolver;
    }

    public function setHandlers($handlers)
    {
        if (!is_array($handlers)) {
            $handlers = [$handlers];
        }

        $fullyQualifiedHandlerNames = [];

        foreach ($handlers as $handler) {
            if (is_string($handler)) {
                $handler = $this->handlerResolver->resolve($handler);
            }

            if (!$handler instanceof HandlerInterface) {
                throw new HandlerNotFound('Must provide either a handler name or handler object.');
            }

            $fullyQualifiedHandlerNames[] = $handler->getFullyQualifiedName();
        }

        $this->handlers = $fullyQualifiedHandlerNames;

        return $this;
    }

    public function setEntities($entityInput)
    {
        if (!is_array($entityInput)) {
            $entityInput = [$entityInput];
        }

        $entities = [];

        foreach ($entityInput as $entity) {
            if (is_string($entity)) {
                $entity = Entity::fromShortcode($entity, $this->handlerResolver);
            }

            if (!$entity instanceof Entity) {
                throw new InvalidEntity('Only Entity objects can be provided to setEntities().');
            }

            $entities[] = $entity;
        }

        $this->entities = $entities;

        return $this;
    }

    public function getIterator()
    {
        $this->fetchEntries();
        return new Collection($this->dbGateway, $this->entries);
    }

    public function count()
    {
        $this->fetchEntries();
        return count($this->entries);
    }

    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    public function setOffset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    public function setOrder($order)
    {
        if (self::ORDER_ASC !== $order && self::ORDER_DESC !== $order) {
            throw new InvalidEntryReaderOrder('Entry reader results can only be ordered in "asc" or "desc".');
        }

        $this->order = $order;

        return $this;
    }

    public function select()
    {
        return $this->dbGateway->selectEntries(
            $this->handlers,
            $this->entities,
            $this->limit,
            $this->offset,
            $this->order
        );
    }

    public function getTotalCount()
    {
        $this->fetchEntries();
        return $this->totalCount;
    }

    protected function fetchEntries()
    {
        if (!is_array($this->entries)) {
            $select = $this->select();
            $driver = $select->getAdapter()->getDriver();

            $driver->prepareSelectForTotalRowCalculation($select);

            $this->entries    = $this->dbGateway->getAdapter()->fetchAll($select);
            $this->totalCount = $driver->fetchTotalRowCount($this->entries);
        }

        return $this->entries;
    }
}
