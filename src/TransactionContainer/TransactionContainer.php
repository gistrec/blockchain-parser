<?php

namespace TransactionContainer;


use Block\Transaction\Transaction;
use Exception;


class TransactionContainer {

	// Массив с потрачеными выходами транзакций
	// которые еще не пропарисились
	public $deleted_utxo = array();

	// Массив непотреченных транзакций
	// tr_hash => class UXTO
	public $utxo = array();

	/*
	 * Функция добавляет транзакцию в массив непотраченных транзакций
	 * Входные данные: class Transaction
     * Алгоритм:
     *     1. Вычисляем хэш транзакции
	 *     2. Если транзакция с этим хэшем уже существует
	 *                                  кидаем исключение
	 *     3. Генерируем UTXO класс
	 *     4. Добавляем UTXO класс в массив $utxo
	 *     5. Удаляем выходы транзакций, которые
	 *               указаны во входе транзакции
	 */
	public function addTransaction(Transaction $transaction) {
		// Получаем хэш транзакций
		$transaction_hash = $transaction->getHash();

		// Если транзакция с этим хэшем уже есть
		//                     кидаем исключение
		if (isset($this->utxo[$transaction_hash])) {
			// echo 'Transaction already exists: ' . $transaction_hash . PHP_EOL;
			// throw new Exception("Транзакция с этим хэшем уже есть");
		}

		// Генерируем UTX класс
		$utxo = new UTXO($transaction);

		// Добавляем UTXO класс в массив $utxo
		$this->utxo[$transaction_hash] = $utxo;
		/*
		 * Удаляем выходы транзакций, которые указаны во входе транзакций
		 * Алгоритм:
		 *     Для всех входов транзакции
		 *        Если транзакция не за сгенерированный блок
		 *            Вычисляем хэш предыдущей транзакции, откуда взялись деньги
		 *            Получаем номер выхода предыдущей транзакции
		 *            Ищем хэш в $this->utxo
		 *            Если хэш не найден - бросаем исключение
		 *            Ищем выход в UTXO класс
		 *            Если выход не найден - бросаем исключение
		 *            Удаляем выход
		 *            Если у транзакции не осталось выходов - удаляем её
		 *        Конец если
		 *    Конец для всех
		 */
		// Для всех входов
		foreach ($transaction->inputs as $input) {
			// Если транзакция - не сгенерированный блок
			if ($input->previousTransactionHash != str_repeat("\0", 32)) {
				// Вычисляем хэш предыдущей транзакции, откуда взяли денбги
				$previousTransactionHash = $input->previousTransactionHash;
				// Превращаем endian число в обычное
				$previousTransactionHash = join(array_reverse(str_split(bin2hex($previousTransactionHash), 2)));

				// Получаем выход предыдущей транзакции
				$previousTxoutIndex = $input->previousTxoutIndex;

				// Если в масстве не найден хэш предыдущей транзакции,
				// то блок, в котором содержится предыдущая транзакция
				// будет обработан когда-то позже. Поэтому добавляем эту транзакцию
				// в $deleted_utxo
				if (!isset($this->utxo[$previousTransactionHash])) {
					// var_dump('Not found transaction in UTXO: ' . $previousTransactionHash);
					$this->deleted_utxo[$previousTransactionHash][] = $previousTxoutIndex;
					continue;
				}


				// Ищем выход в UTXO классе
				if (!$this->utxo[$previousTransactionHash]->haveOutput($previousTxoutIndex)) {
					throw new Exception("Не найден выход в UTXO классе");
				}

				// Удаляем выход
				$this->utxo[$previousTransactionHash]->deleteOutput($previousTxoutIndex);

				// Если у транзакции не осталось выходов - удаляем её
				if (count($this->utxo[$previousTransactionHash]->outputs) == 0) {
					unset($this->utxo[$previousTransactionHash]);
				}
			}
			// Если у нас есть выходы, которые были удалены из текущей транзакции
			if (isset($this->deleted_utxo[$transaction_hash])) {
				// var_dump("Found transactions in deleted_utxo: " . $transaction_hash);
				foreach ($this->deleted_utxo[$transaction_hash] as $output) {
					$this->utxo[$transaction_hash]->deleteOutput($output);
				}
				unset($this->deleted_utxo[$transaction_hash]);
			}
		}
	}


	// Функция нужна для сохранения результатов
	public function saveResult($blockCount, $saveDir) {
		echo "Save index block $blockCount" . PHP_EOL;
		// var_dump("Memory start saving: " . memory_get_usage(true));
		$start = microtime(true);
		
		// Сохраняем номер блока
		file_put_contents($saveDir . 'blockCount.dump', $blockCount);
		// var_dump("After saving blockCount: " . memory_get_usage(true));
		
		// Сохраняем utxo
		$this->saveUTXO($saveDir);
		// var_dump("After saving utxo: " . memory_get_usage(true));
		
		// Сохраняет deleted_utxo
		$this->saveDeleted_UTXO($saveDir);
		// var_dump("After saving deleted_utxo: " . memory_get_usage(true));

		// Перезаписываем
		file_put_contents($saveDir . 'address.dump', '');


		// Сохраняем адреса (hash_160 от адреса)
		$address = array();
		foreach ($this->utxo as $utxo) {
			foreach ($utxo->outputs as $output) {
				if ($output->is_parse) {
					if (isset($address[$output->hash_160])) {
						$address[$output->hash_160][1] += $output->value;
					}else {
						$address[$output->hash_160] = array($output->hash_160, $output->value);
					}
				}
			}
		}
		// var_dump("After transform address: " . memory_get_usage(true));

		file_put_contents($saveDir . 'address.dump', '');
		$file = fopen($saveDir . 'address.dump', 'w');
		foreach ($address as $value) {
			// Если баланс больше 0
			if ($value[1] > 0) {
				fwrite($file, $value[0] . "\n");
			}
		}
		unset($address);


		// var_dump("End saving: " . memory_get_usage(true));
		echo 'Save time: ' . substr((microtime(true) - $start), 0, 4) . PHP_EOL . PHP_EOL;
	}

	public function saveUTXO($saveDir) {
		// Удаляем файл
		file_put_contents($saveDir . 'utxo.dump', '');
		$file = fopen($saveDir . 'utxo.dump', 'w');
		foreach ($this->utxo as $utxo) {
			fwrite($file, serialize($utxo) . "\n");
		}
		fclose($file);
		//file_put_contents($saveDir . 'utxo.dump', serialize($this->utxo));
	}

	public function saveDeleted_UTXO($saveDir) {
		file_put_contents($saveDir . 'deleted_utxo.dump', '');
		$file = fopen($saveDir . 'deleted_utxo.dump', 'w');
		foreach ($this->deleted_utxo as $deleted_utxo) {
			fwrite($file, serialize($deleted_utxo) . "\n");
		}
		fclose($file);
	}
}