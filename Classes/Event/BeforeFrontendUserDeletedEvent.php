<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Event;

/**
 * Dispatched right before a frontend user row is soft-deleted/anonymized via
 * the DeleteAccountFinisher. Listeners may mutate the update payload (e.g.
 * to clear values in related tables, replace anonymized values, or cancel
 * the deletion by throwing an exception).
 */
final class BeforeFrontendUserDeletedEvent
{
    public function __construct(
        private readonly array $userRow,
        private array $updateData,
    ) {}

    public function getUserRow(): array
    {
        return $this->userRow;
    }

    public function getUserId(): int
    {
        return (int)($this->userRow['uid'] ?? 0);
    }

    public function getUpdateData(): array
    {
        return $this->updateData;
    }

    public function setUpdateData(array $updateData): void
    {
        $this->updateData = $updateData;
    }
}