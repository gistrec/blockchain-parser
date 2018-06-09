<?php

namespace Block\Transaction;


use Utils\BlockUtils;


class TransactionOutput {

    public $value;
    public $txoutScriptLength;
    public $txoutScript;

    public function __construct($data, &$position) {
        // Value (8 байт)
        // Кол-во сатоши для перевода
        // Разница между всеми вошедшими сатошами и вышедшими - комиссия майнера
        $this->value = BlockUtils::hexToNumber(substr($data, $position, 8));
        $position += 8;

        // Script bytes (1+ байт)
        $this->txoutScriptLength = BlockUtils::varInt($data, $position);

        // Script
        // Условия, при которых этот вывод осуществится
        // Самая важная часть в транзакции :)
        if ($this->txoutScriptLength > 0) {
            $this->txoutScript = substr($data, $position, $this->txoutScriptLength);
            $position += $this->txoutScriptLength;
        }
    }
}