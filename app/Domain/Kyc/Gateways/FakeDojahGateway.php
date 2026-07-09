<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Gateways;

use App\Domain\Kyc\Contracts\KycVerificationGateway;
use App\Domain\Kyc\Data\BvnIdentity;
use App\Domain\Kyc\Data\NinIdentity;
use App\Domain\Kyc\Exceptions\KycVerificationException;
use Illuminate\Support\Carbon;

/**
 * In-memory Dojah stand-in for local dev and tests.
 *
 * @see https://docs.dojah.io/docs/nigeria/lookup-bvn sandbox credentials
 */
class FakeDojahGateway implements KycVerificationGateway
{
    /** @var array<string, array{first: string, last: string, middle?: string, dob: string, phone?: string}> */
    private array $bvns = [
        '22222222222' => ['first' => 'JOHN', 'last' => 'MUSA', 'middle' => 'DOE', 'dob' => '1997-05-16', 'phone' => '08012345678'],
        '22334455667' => ['first' => 'RETON', 'last' => 'TEST', 'middle' => 'USER', 'dob' => '1990-05-15'],
    ];

    /** @var array<string, array{first: string, last: string, middle?: string, dob: string}> */
    private array $nins = [
        '70123456789' => ['first' => 'RETON', 'last' => 'TEST', 'middle' => 'USER', 'dob' => '1990-05-15'],
        '12345678901' => ['first' => 'RETON', 'last' => 'TEST', 'middle' => 'USER', 'dob' => '1990-05-15'],
    ];

    public function verifyBvn(string $bvn): BvnIdentity
    {
        $bvn = preg_replace('/\D/', '', $bvn) ?? '';

        if (strlen($bvn) !== 11) {
            throw KycVerificationException::notFound('BVN');
        }

        $record = $this->bvns[$bvn] ?? null;

        if ($record === null) {
            throw KycVerificationException::notFound('BVN');
        }

        return $this->toBvn($bvn, $record);
    }

    public function verifyNin(string $nin): NinIdentity
    {
        $nin = preg_replace('/\D/', '', $nin) ?? '';

        if (strlen($nin) !== 11) {
            throw KycVerificationException::notFound('NIN');
        }

        $record = $this->nins[$nin] ?? null;

        if ($record === null) {
            throw KycVerificationException::notFound('NIN');
        }

        return $this->toNin($nin, $record);
    }

    /**
     * @param  array{first: string, last: string, middle?: string, dob: string, phone?: string}  $record
     */
    private function toBvn(string $bvn, array $record): BvnIdentity
    {
        return new BvnIdentity(
            bvn: $bvn,
            firstName: $record['first'],
            lastName: $record['last'],
            middleName: $record['middle'] ?? null,
            dateOfBirth: Carbon::parse($record['dob']),
            phone: $record['phone'] ?? null,
        );
    }

    /**
     * @param  array{first: string, last: string, middle?: string, dob: string}  $record
     */
    private function toNin(string $nin, array $record): NinIdentity
    {
        return new NinIdentity(
            nin: $nin,
            firstName: $record['first'],
            lastName: $record['last'],
            middleName: $record['middle'] ?? null,
            dateOfBirth: Carbon::parse($record['dob']),
            phone: null,
        );
    }
}
