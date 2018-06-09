<?php

namespace Block\Transaction;


use Utils\BlockUtils;


class TransactionInput {

    public $previousTransactionHash;
    public $previousTxoutIndex;
    public $txinScriptLength;
    public $txinScript;
    public $sequenceNumber;

    public function __construct($data, &$position) {
        // previous_output (32 байта)
        // Хэш предыдущей транзакции (откуда будем брать все биткоины)
        $this->previousTransactionHash = substr($data, $position, 32);
        $position += 32;

        // TransactionIndex (4 байта)
        // Номер выхода в списке предыдущий транзакций
        $this->previousTxoutIndex = BlockUtils::hexToNumber(substr($data, $position, 4));
        $position += 4;

        // ScriptLength (1|3|5|9 байт)
        // Длина скрипта
        $this->txinScriptLength = BlockUtils::varInt($data, $position);

        // Если есть скрипт - копируем его
        // Вообще скрипт во входящих транзакциях можно игнорировать
        if ($this->txinScriptLength > 0) {
            $this->txinScript = substr($data, $position, $this->txinScriptLength);
            $position += $this->txinScriptLength;
        }

        // sequence (4 байта)
        // Стандартно FFFFFFFF
        $this->sequenceNumber = BlockUtils::hexToNumber(substr($data, (int)$position, 4));
        $position += 4;
    }
}