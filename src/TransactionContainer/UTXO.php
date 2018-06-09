<?php

namespace TransactionContainer;


use Block\Transaction\Transaction;


class UTXO {

	// Массив с выходами
	public $outputs = array();

	/*
	 * В конструкторе парсим транзакцию и создаем классы выходов
	 * Алгоритм:
	 *     Для всех выходов
	 *         Создаем класс Output
	 *         Добавляем его в массив $this->outputs
	 *     Конец для всех
	 */
	public function __construct(Transaction $transaction) {
		foreach ($transaction->outputs as $index => $output) {
			$output = new Output($output);
			$this->outputs[$index] = $output;
		}
	}

	// Проверка есть ли в массиве выходов нужный номер
	public function haveOutput(int $previousTxoutIndex) {
		return isset($this->outputs[$previousTxoutIndex]);
	}

	// Удаление выхода с хзаданным номером в массиве выходов
	public function deleteOutput(int $previousTxoutIndex) {
		unset($this->outputs[$previousTxoutIndex]);
	}
}