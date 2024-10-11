<?php

function calcular_duracion_turno($fini, $ffin) {
    $inicio = strtotime($fini);
    $fin = strtotime($ffin);

    // Si el fin es anterior al inicio, significa que cruzamos a la medianoche
    if ($fin <= $inicio) {
        // Sumar 24 horas para reflejar que es el siguiente día
        $fin += 24 * 3600;
    }
    
    // Calcular la duración en horas
    $duracion = ($fin - $inicio) / 3600;

    // Determinar si es un turno que abarca dos días
    $cruza_dia = ($fini >= $ffin) ? true : false;

    return [
        'duracion' => $duracion,
        'cruza_dia' => $cruza_dia
    ];
}

// Ejemplo de uso
$fini1 = '06:00';
$ffin1 = '06:00';
$result1 = calcular_duracion_turno($fini1, $ffin1);
echo "Duración del turno 1: " . $result1['duracion'] . " horas, Cruza día: " . ($result1['cruza_dia'] ? "Sí" : "No") . "\n"; 

$fini2 = '14:00';
$ffin2 = '06:00';
$result2 = calcular_duracion_turno($fini2, $ffin2);
echo "Duración del turno 2: " . $result2['duracion'] . " horas, Cruza día: " . ($result2['cruza_dia'] ? "Sí" : "No") . "\n"; 
?>
