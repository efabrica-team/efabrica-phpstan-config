<?php declare(strict_types=1);

namespace NetteDatabaseRepository\Repository;

use Closure;
use Nette\Database\Context;
use Nette\Database\Table\Selection;

abstract class BaseRepository
{
    /** @var Context */
    protected Context $connection;

    public function getTable(): Selection
    {
        return $this->connection->table('');
    }

    final public function findBy(): Selection
    {
        return $this->getTable();
    }

    /**
     * @param Closure $callback
     *
     * @return mixed
     */
    final public function ensure(Closure $callback)
    {

    }
}
