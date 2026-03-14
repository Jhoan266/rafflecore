<?php
// Prueba local de generación de boletos de 3 dígitos para RaffleCore
// Ejecutar: php tests/local-test-tickets.php

require_once __DIR__ . '/../modules/ticket/class-ticket-service.php';


function test_generate_tickets($digits, $quantity) {
    $min_ticket = 1;
    $max_ticket = (int)str_repeat('9', $digits);
    $used_set = [];
    $tickets = [];
    $pool = [];
    for ($i = $min_ticket; $i <= $max_ticket; $i++) {
        if (!isset($used_set[$i])) {
            $pool[] = $i;
        }
    }
    if (count($pool) < $quantity) {
        return false;
    }
    for ($i = count($pool) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        $tmp = $pool[$i];
        $pool[$i] = $pool[$j];
        $pool[$j] = $tmp;
    }
    $tickets = array_slice($pool, 0, $quantity);
    sort($tickets);
    $formatted = RaffleCore_Ticket_Service::format_numbers($tickets, ['digits'=>$digits]);
    // Validar formato y rango
    foreach ($formatted as $f) {
        if (strlen($f) != $digits) return false;
        if ((int)$f < $min_ticket || (int)$f > $max_ticket) return false;
    }
    return true;
}

$total_tests = 40;
$success = 0;
$fail = 0;
$cases = [2, 3, 4, 5];
echo "\nPruebas automáticas de generación de boletos:\n";
flush();
foreach ($cases as $digits) {
    for ($i = 1; $i <= $total_tests/4; $i++) {
        $quantity = random_int(1, 10); // Pruebas con 1 a 10 boletos
        $ok = test_generate_tickets($digits, $quantity);
        if ($ok) {
            echo "[ÉXITO] $digits dígitos, $quantity boletos\n";
            $success++;
        } else {
            echo "[ERROR]  $digits dígitos, $quantity boletos\n";
            $fail++;
        }
        flush();
    }
}
echo "\nResumen: $success éxitos, $fail errores\n";
