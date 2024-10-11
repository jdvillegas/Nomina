<?php

function calcular_horas_diurnas_nocturnas($fini, $ffin) {
    $horas_diurnas = 0;
    $horas_nocturnas = 0;

    $inicio = strtotime($fini);
    $fin = strtotime($ffin);
    
    // Si el fin es anterior al inicio, significa que cruzamos a la medianoche
    if ($fin <= $inicio) {
        $fin += 24 * 3600; // Aumentar 24 horas
    }

    // Definir las horas diurnas y nocturnas
    $horario_diurno_inicio = strtotime('06:00');
    $horario_diurno_fin = strtotime('22:00');
    $horario_nocturno_inicio = strtotime('22:00');
    $horario_nocturno_fin = strtotime('06:00') + 24 * 3600; // Para comparación

    // Iterar sobre cada hora del turno
    for ($hora = $inicio; $hora < $fin; $hora += 3600) {
        // Hora actual
        $hora_actual = $hora;

        // Comprobar si es hora diurna o nocturna
        if (($hora_actual >= $horario_diurno_inicio && $hora_actual < $horario_diurno_fin) ||
            ($hora_actual >= $horario_nocturno_inicio && $hora_actual < $horario_nocturno_fin)) {
            if ($hora_actual >= $horario_diurno_inicio && $hora_actual < $horario_diurno_fin) {
                $horas_diurnas++;
            } else {
                $horas_nocturnas++;
            }
        }
    }

    return [
        'horas_diurnas' => $horas_diurnas,
        'horas_nocturnas' => $horas_nocturnas
    ];
}

// Ejemplo de uso
$fini1 = '06:00';
$ffin1 = '06:00';
$result1 = calcular_horas_diurnas_nocturnas($fini1, $ffin1);
echo "Turno 1 - Horas Diurnas: " . $result1['horas_diurnas'] . ", Horas Nocturnas: " . $result1['horas_nocturnas'] . "\n";

$fini2 = '18:00';
$ffin2 = '06:00';
$result2 = calcular_horas_diurnas_nocturnas($fini2, $ffin2);
echo "Turno 2 - Horas Diurnas: " . $result2['horas_diurnas'] . ", Horas Nocturnas: " . $result2['horas_nocturnas'] . "\n";
?>
