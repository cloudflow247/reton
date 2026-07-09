<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Contracts;

use App\Domain\Kyc\Data\BvnIdentity;
use App\Domain\Kyc\Data\NinIdentity;
use App\Domain\Kyc\Exceptions\KycVerificationException;

interface KycVerificationGateway
{
    /**
     * @throws KycVerificationException
     */
    public function verifyBvn(string $bvn): BvnIdentity;

    /**
     * @throws KycVerificationException
     */
    public function verifyNin(string $nin): NinIdentity;
}
