<?php
// cd C:\Users\Alex\Downloads\php-blockchain-parser-master\php-blockchain-parser-master\src
//
// C:\Users\Alex\Desktop\Разработка\bin\php-7.0\php.exe C:\Users\Alex\Downloads\php-blockchain-parser-master\php-blockchain-parser-master\src\main.php

ini_set("memory_limit", -1);

// Загружаем все классы
require_once "autoload.php";

use TransactionContainer\TransactionContainer as Container;
use Block\Block;
use Errors\EndOfBlock;
use Exception;

/*
 * Парер блоков
 * Алгоритм:
 *     1) Создаем контейнер для транзакций
 *     2) Для всех файлов *.blk
 *
 *
 *
 */

// Создаем контейнер для хранения транзакций
$container = new Container();

// Место хранения блоков
$blockPath = 'C:\\Users\\Alex\\Downloads\\WarCraft\\blockchain\\blocks\\';

// Колв-во блоков
$blockCount = 0;
$blockIndex = 0;

// Указатель на блок
$filePointer;

function loadResult() {
	global $container;
	global $blockPath;
	global $blockCount;
	global $blockIndex;
	global $filePointer;

	$finalBlock = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'Result' . DIRECTORY_SEPARATOR . 'blockCount.dump');

	for ($blockIndex = 0;; ++$blockIndex) {
		$blockName = 'blk'.str_repeat('0', 5 - strlen((string) $blockIndex)).$blockIndex.'.dat';
		$blockFile = $blockPath . $blockName;
		$filePointer = fopen($blockFile, 'r');
		if ($filePointer === FALSE) {
			echo "Block $blockFile not found" . PHP_EOL;
			exit (-1);
		}
		while (true) {
			try {
				$block = new Block($filePointer);
				fread($filePointer, $block->blockSize);
			} catch (EndOfBlock $error) {
				break;
			}

			$blockCount += 1;
			if ($blockCount % 10000 == 0) echo "Checing $blockCount block" . PHP_EOL;
			if ($blockCount == $finalBlock) break;
		}
		if ($blockCount == $finalBlock) break;
		fclose($filePointer);
	}
	echo "Loading utxo and deleted_utxo" . PHP_EOL;
	// Загружаем utxo
	$utxo = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'Result' . DIRECTORY_SEPARATOR . 'utxo.dump', "r");
	while (($line = fgets($utxo)) !== false) {
        $container->utxo[] = unserialize($line);
    }
    fclose($utxo);

    // Загружаем deleted_utxo
    $deleted_utxo = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'Result' . DIRECTORY_SEPARATOR . 'deleted_utxo.dump', "r");
	while (($line = fgets($deleted_utxo)) !== false) {
        $container->deleted_utxo[] = unserialize($line);
    }
    fclose($deleted_utxo);

    echo "Continue parse from block: " . $blockCount . PHP_EOL;
    echo "BlockIndex: " . $blockIndex . PHP_EOL;
    echo "UTXO count: " . count($container->utxo) . PHP_EOL;
    echo "deleted_utxo count:" . count($container->deleted_utxo) . PHP_EOL;
}

function parse() {
	global $container;
	global $blockPath;
	global $blockCount;
	global $blockIndex;
	global $filePointer;

	// Для всех network blok'ов, вида blk?????.dat
	for (;; ++$blockIndex) {
		$blockName = 'blk'.str_repeat('0', 5 - strlen((string) $blockIndex)).$blockIndex.'.dat';

		$blockFile = $blockPath.$blockName;

		// Указатель на начало нового блока
		// Который будет смещаться, после прочтения очередного блока
		$filePointer = fopen($blockFile, 'r');

		// Для всех блоков
		while (true) {
			try {
				$block = new Block($filePointer);
				$block->parse($filePointer);
			} catch (EndOfBlock $error) {
				break;
			}

			foreach ($block->transactions as $transaction) {
				$container->addTransaction($transaction);
			}

			$blockCount += 1;
			// if ($blockCount > 200000) break;
			if ($blockCount % 1000 == 0) var_dump($blockCount);
			if (($blockCount % 10000) == 0) {
				$saveDir = __DIR__ . DIRECTORY_SEPARATOR . 'Result' . DIRECTORY_SEPARATOR;
				$container->saveResult($blockCount, $saveDir);
			}
		}
		fclose($filePointer);
		//if ($blockCount > 200000) break;
	}
}

loadResult();
parse();