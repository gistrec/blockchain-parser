<?php
ini_set("memory_limit", -1);

// Загружаем все классы
require_once "autoload.php";

use TransactionContainer\TransactionContainer as Container;
use Block\Block;
use Errors\EndOfBlock;


// Создаем контейнер для хранения транзакций
$container = new Container();

$blockIndex = 0; // Номер блока в блокчейн цепочке
$fileIndex = 0; // Индекс файла


$filePointer; // Указатель на блок в файле



$options = getopt("d:c");
// Проверяем чтобы
//     1) Была указана папка с блоками
//     2) Это была папка
//     3) В папке есть нулевой блок (blk00000.dat)
if (!isset($options['d'])) {
	echo 'Укажите расположение блоков: -d <path>' . PHP_EOL;
	return;
}elseif (!is_dir($options['d'])) {
	echo 'Папка с блоками не найдена' . PHP_EOL;
	return;
}elseif (!file_exists($options['d'] . 'blk00000.dat')) {
	echo 'Блок blk00000.dat не найден' . PHP_EOL;
	return;
}
$blockPath = $options['d']; // Директория в которой хранятся блоки

// Если есть ключ -c, который отвечает за продолжение перебора блоков
// То проверяем наличие сохраненных файлов и выполняем функцию loadResult()
if (isset($options['c'])) {
	$resultDir = __DIR__ . DIRECTORY_SEPARATOR . 'Result' . DIRECTORY_SEPARATOR;
	if (!file_exists($resultDir . 'blockCount.dump')) {
		echo 'Нет файла blockCount.txt с кол-вом блоков' . PHP_EOL;
		return;
	}elseif (!file_exists($resultDir . 'deleted_utxo.dump')) {
		echo 'Нет файа с удаленными транзакциями deleted_utxo.dump' . PHP_EOL;
		return;
	}elseif (!file_exists($resultDir . 'utxo.dump')) {
		echo 'Нет файла с utxo' . PHP_EOL;
		return;
	}
	loadResult();
	// Выводим информацию
	echo "Continue parse from block: " . $blockIndex . PHP_EOL;
    echo "fileIndex: " . $fileIndex . PHP_EOL;
    echo "UTXO count: " . count($container->utxo) . PHP_EOL;
    echo "deleted_utxo count:" . count($container->deleted_utxo) . PHP_EOL;
}
parse();


/**
 * Функция нужна для поиска блока, с которого следует продолжить перебор блоков
 * Для этого нам нужны сохраненные файлы с предыдущего перебора, а именно
 *    1) utxo - массив из непотраченных транзакций
 *    2) blockCount - количество блоков, которые были обработаны в прошлый раз
 *    3) deleted_utxo - транзакции, которые уже были удалены, но еще не были добавлены
 *       Такое может быть, т.к. блоки в файлах не обязательно идут последовательно
 * Алгоритм:
 *    Пока индекс перебираемого блока не стал равен blockCount
 *    Переходим к следующему блоку
 */
function loadResult() {
	global $container;
	global $blockPath;
	global $blockIndex;
	global $fileIndex;
	global $filePointer;

	$finalBlock = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'Result' . DIRECTORY_SEPARATOR . 'blockCount.dump');

	// Цикл по файлам
	for ($fileIndex = 0;; ++$fileIndex) {
		$blockName = 'blk'.str_repeat('0', 5 - strlen((string) $fileIndex)).$fileIndex.'.dat';
		$filePointer = fopen( $blockPath . $blockName, 'r');

		// Если вдруг файла нет - выходим из скрипта
		if ($filePointer === FALSE) {
			echo "File $blockFile not found!" . PHP_EOL;
			exit (-1);
		}

		// Цикл по блокам в файле
		while (true) {
			// TODO: Сделать проверку на magic number
			//       Чтобы не использовать try/catch
			try {
				$block = new Block($filePointer);
				fread($filePointer, $block->blockSize);
			} catch (EndOfBlock $error) {
				// Если magic numer не совпадает, то значит что файл закончился.
				// Нужно перходить к следующему файлу.
				break;
			}

			$blockIndex += 1;
			// Каждые 10к блоков выводим информацию
			if ($blockIndex % 10000 == 0) echo "Processing $blockIndex block" . PHP_EOL;
			// Если мы достигли блока, индекс которого указан в blockCount.dump
			// То выходим из функции
			if ($blockIndex == $finalBlock) break; 
		}
		if ($blockIndex == $finalBlock) break;
		fclose($filePointer);
	}
	// Загружаем сохраненные utxo и deleted_utxo.dump
	echo "Loading utxo and deleted_utxo" . PHP_EOL;

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
}

function parse() {
	global $container;
	global $blockPath;
	global $blockIndex;
	global $fileIndex;
	global $filePointer;

	// Для всех network blok'ов, вида blk?????.dat
	for (;; ++$fileIndex) {
		$blockName = 'blk'.str_repeat('0', 5 - strlen((string) $fileIndex)).$fileIndex.'.dat';

		// Указатель на начало нового блока
		// Который будет смещаться, после прочтения очередного блока
		$filePointer = fopen($blockPath . $blockName , 'r');

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

			$blockIndex += 1;
			// if ($blockIndex > 200000) break;
			if ($blockIndex % 1000 == 0) {
				echo "Parsing $blockIndex block" . PHP_EOL;
			}
			// Сохраняем результаты каждые 10к блоков
			if (($blockIndex % 10000) == 0) {
				$saveDir = __DIR__ . DIRECTORY_SEPARATOR . 'Result' . DIRECTORY_SEPARATOR;
				$container->saveResult($blockIndex, $saveDir);
			}
		}
		fclose($filePointer);
		//if ($blockIndex > 200000) break;
	}
}