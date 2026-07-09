<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Data;

use Illuminate\Support\Carbon;

final readonly class NinIdentity
{
    public function __construct(
        public string $nin,
        public string $firstName,
        public string $lastName,
        public ?string $middleName,
        public ?Carbon $dateOfBirth,
        public ?string $phone,
    ) {}

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->firstName, $this->middleName, $this->lastName])));
    }
}
