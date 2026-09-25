<?php

namespace App\Modules\Customers\Contracts\Data;

/**
 * A customer, as it goes in and out of the repository.
 *
 * Immutable and inert, unlike the record it stands for. A module handed an
 * Eloquent model can save it, delete it, or lazily load relations across a
 * boundary it should not be reaching over; handed this, it can read it, and
 * change it only by giving a new one to the repository's `save()`.
 *
 * `id` is null for a customer not stored yet. `userId` is the login they sign
 * in to the portal with, when the shop has given them one.
 */
final readonly class CustomerData
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $notes = null,
        public ?int $userId = null,
    ) {}

    /**
     * Whether this customer can sign in to the portal.
     */
    public function hasPortalAccess(): bool
    {
        return $this->userId !== null;
    }

    /**
     * The same customer, linked to the given login.
     */
    public function withLogin(int $userId): self
    {
        return new self($this->id, $this->name, $this->phone, $this->address, $this->notes, $userId);
    }
}
