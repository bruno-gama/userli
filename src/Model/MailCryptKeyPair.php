<?php

namespace App\Model;

use App\Traits\PrivateKeyTrait;
use App\Traits\PublicKeyTrait;

/**
 * @author doobry <doobry@systemli.org>
 */
class MailCryptKeyPair
{
    use PrivateKeyTrait, PublicKeyTrait;

    /**
     * MailCryptKeyPair constructor.
     *
     * @param string $privateKey
     * @param string $publicKey
     */
    public function __construct(string $privateKey, string $publicKey)
    {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
    }

    public function erase()
    {
        sodium_memzero($this->privateKey);
        sodium_memzero($this->publicKey);
    }
}
