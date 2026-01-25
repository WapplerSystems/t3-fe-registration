<?php
namespace WapplerSystems\FeRegistration\Event;

class FeUserDatabaseDataEvent
{
    /**
     * @var array
     */
    protected $databaseData;

    public function __construct(array $databaseData)
    {
        $this->databaseData = $databaseData;
    }

    public function getDatabaseData(): array
    {
        return $this->databaseData;
    }

    public function setDatabaseData(array $databaseData): void
    {
        $this->databaseData = $databaseData;
    }
}
