<?php

$supportedOperators = ['+', '-', '*', '/'];
$callsHistory = [];
function calculateOperation( &$history, int $a, int $b, string $operation = '+'): int|false
{
    $history[] = $a . ' ' . $operation . ' ' . $b;

    if ($operation == '+') {
        return $a + $b;
    } else if ($operation == '-') {
        return $a - $b;
    } else if ($operation == '*') {
        return $a * $b;
    } else if ($operation == '/') {
        if ($b === 0) {
            throw new InvalidArgumentException('Деление на ноль невозможно.');
        }
        return $a / $b;
    }
    return false;
}

function parseOperator($userInput, $operator): false|array
{
    $parseResult = explode($operator, $userInput);
    if ($parseResult && count($parseResult) == 2) {
        return ['operators' => $parseResult, 'operator' => $operator];
    }
    return false;
}

do {
    $userInput = readline('Введите выражение');

    if ($userInput == 'exit') {
         print_r($callsHistory);
    }

    foreach ($supportedOperators as $operator) {
        $parseResult = parseOperator($userInput, $operator);
        if ($parseResult) {
             echo 'Результат = ' . calculateOperation( $callsHistory, intval ($parseResult['operators'][0]), intval  ($parseResult['operators'][1]), $parseResult['operator']) . PHP_EOL;

        }
    }
} while (true);