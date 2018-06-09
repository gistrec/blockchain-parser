<?php

namespace Block;


use Utils\BlockUtils;
use Exception;


class BlockHeader {

    public $version;
    public $hashPrevBlock;
    public $hashMerkleRoot;
    public $time;
    public $bits;
    public $nonce;

    public function fromRawData($data) {
        // Заголовок должен быть размером 80 байт
        if (strlen($data) != 80) {
            throw new Exception("Invalid header data");
        }

        // Version (4 байта)
        // Номер версии блока (всегда 1)
        $this->version = BlockUtils::hexToNumber(substr($data, 0, 4));

        // hashPrevBlock (32 байта)
        // 256-битный хэш предыдущего блока
        $this->hashPrevBlock = substr($data, 4, 32);

        // hashMerkleRoot (32 байта)
        // 256-битный хэш, основанный на транзакциях
        $this->hashMerkleRoot = substr($data, 36, 32);

        // Time (4 байта)
        // Время создания блока, UNIX time
        $this->time = BlockUtils::hexToNumber(substr($data, 68, 4));

        // Bits (4 байта)
        // Что-то вроде сложности
        $this->bits = BlockUtils::hexToNumber(substr($data, 72, 4));

        // Nonce (4 байта)
        // 32-битное число (случайно перебираемое число)
        $this->nonce = BlockUtils::hexToNumber(substr($data, 76, 4));
    }
}