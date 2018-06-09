<?php

namespace Block\Transaction;


use Utils\BlockUtils;
use Block\Transaction\TransactionInput;
use Block\Transaction\TransactionOutput;


class Transaction {

    public $version;
    public $inCounter;
    public $inputs;
    public $outCounter;
    public $outputs;
    public $lockTime;

    public $data;

    public function __construct($data, &$position) {

        $start = $position;

        // Version (4 байта)
        $this->version = BlockUtils::hexToNumber(substr($data, $position, 4));
        $position += 4;

        // Получаем кол-во входящий транзакций (1|3|5|9 байт)
        // $position уже смещено на после кол-ва транзакций
        $this->inCounter = BlockUtils::varInt($data, $position);

        $this->inputs = array();
        for ($i = 0; $i < $this->inCounter; ++$i) {
            $this->inputs[] = new TransactionInput($data, $position);
        }


        // Получаем кол-во выходящих транзакций (1|3|5|9 байт)
        // $position уже смещено на после кол-ва транзакций
        $this->outCounter = BlockUtils::varInt($data, $position);

        $this->outputs = array();
        for ($i = 0; $i < $this->outCounter; ++$i) {
            $this->outputs[] = new TransactionOutput($data, $position);
        }

        // LockTime (4 байта)
        // Время транзакций, UNIX time
        $this->lockTime = BlockUtils::hexToNumber(substr($data, $position, 4));
        $position += 4;

        $this->data = substr($data, $start, $position - $start);
    }

    public function getHash() {
        $hash = hash("sha256", hash("sha256", $this->data, true));
        // Это можно убрать
        for ($i = 0; $i < 32; $i += 2) {
            $temp = array($hash[$i], $hash[$i+1]);
            $hash[$i] = $hash[63 - ($i + 1)];
            $hash[$i + 1] = $hash[63 - $i];
            $hash[63 - $i] = $temp[1];
            $hash[63 - ($i + 1)] = $temp[0];
        }
        return $hash;
    }
}