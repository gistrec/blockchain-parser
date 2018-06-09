<?php

namespace Block;


use Block\Transaction\Transaction;
use Utils\BlockUtils;
use Errors\EndOfBlock;
use Exception;


class Block {

    public $blockSize;

    public $header;
    public $transactionCounter;
    public $transactions;

    public $data;

    public function __construct(&$filePointer) {
        // Magic (4 байта)
        // Зависит от типы сети, которую используем
        //    Mainnet     f9beb4d9  <-- main
        //    Testnet3    0b110907
        //    Regtest     fabfb5da
        $magic = fread($filePointer, 4);

        if (bin2hex($magic) != 'f9beb4d9') {
            if ($magic == '' || $magic == '00000000') {
                throw new EndOfBlock();
            }else {
                var_dump($magic, bin2hex($magic));
                throw new Exception("Invalid magic number");
            }
        }

        // Blocksize (4 байта)
        // Количество байтов до конца блока
        $this->blockSize = BlockUtils::hexToNumber(fread($filePointer, 4));
    }

    public function parse(&$fp) {
        // Данные блока - Blocksize байтов
        $this->data = fread($fp, $this->blockSize);

        // Blockheader (80 байт)
        // Заголовок блока - содержит 6 элементов
        $this->header = new BlockHeader();
        $this->header->fromRawData(substr($this->data, 0, 80));

        $position = 80;
        // Получаем кол-во транзакций (1|3|5|9 байт)
        // $position уже смещено на после кол-ва транзакций
        $this->transactionCounter = BlockUtils::varInt($this->data, $position);

        // Создаем массив для транзакций
        $this->transactions = array();
        for ($i = 0; $i < $this->transactionCounter; $i++) {
            $this->transactions[] = new Transaction($this->data, $position);
        }
    }
}