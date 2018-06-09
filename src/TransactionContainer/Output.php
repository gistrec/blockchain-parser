<?php

namespace TransactionContainer;


use Utils\Script;
use Block\Transaction\TransactionOutput;


class Output {

	// Получилось ли извлечь адрес
	public $is_parse;

	// Кол-во сатоши для перевода
	public $value;

	// Хэш адреса, куда переводим
	public $hash_160;

	/*
	 * В конструторе парсим выход
	 *
	 * Если не удасться извлечь hash_160 адреса
	 * то is_parse = false, иначе is_parse = true
	 */
	public function __construct(TransactionOutput $output) {
		if (self::parseHash160($output->txoutScript)) {
			$this->is_parse = true;
		}else {
			$this->is_parse = false;
		}
		$this->value = $output->value;
	}

	public function parseHash160($txoutScript) {
		$script = new Script();
		$script->parse($txoutScript);
		$stack = $script->stack;

		if (count($stack) == 6 &&
			$stack[0] == "OP_DUP" &&
			$stack[1] == "OP_HASH160" &&
			$stack[2] == "OP_PUSHDATAX_20" &&
			$stack[4] == "OP_EQUALVERIFY" &&
			$stack[5] == "OP_CHECKSIG") {
			// Получаем из hash160 адрес
			// echo "   Address: " . BlockUtils::ripemd160ToAddress(bin2hex($stack[3])) . PHP_EOL;
			$this->hash_160 = bin2hex($stack[3]);
			return true;
		}elseif (count($stack) == 3 &&
				 $stack[0] == "OP_PUSHDATAX_65" &&
				 strlen($stack[1]) == 65 &&
				 $stack[2] == "OP_CHECKSIG") {
			// echo "   Adress: " . BlockUtils::pubkeyToAddress($stack[1]) . PHP_EOL;
			//echo "   Hash test ???: " . bin2hex($stack[1]) . PHP_EOL;
			// Получаем из публичного ключа hash160
			$this->hash_160 = hash('ripemd160', hash('sha256', $stack[1], true));
			return true;
		}else {
			//echo bin2hex($stack[1]) . PHP_EOL;
			//var_dump($stack);
			//throw new Exception("Critical: Unexpected transaction");
			return false;
		}
		return false;
	}

}