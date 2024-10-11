<?php

class TurnoScheduler {
    private $turnosGuardas = [];

    public function __construct() {
        // Secuencias de turnos de los guardas
        $this->turnosGuardas = [
            'Guarda1' => ['Día', 'Día', 'Día', 'Día', 'Descanso', 'Descanso', 'Noche', 'Noche', 'Noche', 'Noche', 'Descanso', 'Descanso'],
            'Guarda2' => ['Descanso', 'Descanso', 'Noche', 'Noche', 'Noche', 'Noche', 'Descanso', 'Descanso', 'Día', 'Día', 'Día', 'Día'],
            'Guarda3' => ['Noche', 'Noche', 'Descanso', 'Descanso', 'Día', 'Día', 'Día', 'Día', 'Descanso', 'Descanso', 'Noche', 'Noche']
        ];
    }

    /**
     * Asigna los turnos a los días del año.
     * 
     * @param DateTime $startDate Fecha de inicio
     * @param int $numDays Número de días a programar
     * @return array Programación de turnos
     */
    public function assignShifts(DateTime $startDate, int $numDays): array {
        $schedule = [];

        foreach ($this->turnosGuardas as $guarda => $secuencia) {
            $numTurnos = count($secuencia);

            for ($day = 0; $day < $numDays; $day++) {
                // Calcular la fecha
                $currentDate = (clone $startDate)->modify("+$day days");

                // Obtener el turno correspondiente en la secuencia cíclica
                $turnoIndex = $day % $numTurnos;  // Repite la secuencia en ciclo
                $turno = $secuencia[$turnoIndex];

                // Guardar la programación del día para el guarda
                $schedule[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'guarda' => $guarda,
                    'turno' => $turno
                ];
            }
        }

        // Ordenar la programación por fecha
        usort($schedule, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $schedule;
    }
}

// Ejemplo de uso

// Fecha de inicio (1 de enero)
$startDate = new DateTime('2024-01-01');

// Crear instancia del programador de turnos
$scheduler = new TurnoScheduler();

// Asignar turnos a todos los días del año (365 días para este ejemplo)
$numDays = 365;
$schedule = $scheduler->assignShifts($startDate, $numDays);

// Imprimir la programación ordenada por fecha
foreach ($schedule as $entry) {
    echo "Fecha: {$entry['date']} - {$entry['guarda']} - Turno: {$entry['turno']}\n";
}

?>