<?php

class Servicio {
    private $horaInicio1;
    private $horaFin1;
    private $horaInicio2;
    private $horaFin2;
    private $dia;

    public function __construct($horaInicio1, $horaFin1, $horaInicio2, $horaFin2, $dia) {
        $this->horaInicio1 = $horaInicio1;
        $this->horaFin1 = $horaFin1;
        $this->horaInicio2 = $horaInicio2;
        $this->horaFin2 = $horaFin2;
        $this->dia = $dia;
    }

    public function getRangosHorarios() {
        // Implementación del método para obtener rangos horarios
        // Ejemplo de retorno
        return [
            'inicio1' => $this->horaInicio1,
            'fin1' => $this->horaFin1,
            'inicio2' => $this->horaInicio2,
            'fin2' => $this->horaFin2,
            'dia' => $this->dia
        ];
    }

    public function getAsignacionHoras() {
        // Implementación del método para asignar horas
        // Ejemplo de retorno con turnos partidos
        return [
            'Lunes' => [
                ['hora_inicio' => '08:00', 'hora_fin' => '12:00'],
                ['hora_inicio' => '14:00', 'hora_fin' => '18:00']
            ],
            'Martes' => [
                ['hora_inicio' => '08:00', 'hora_fin' => '12:00'],
                ['hora_inicio' => '14:00', 'hora_fin' => '18:00']
            ],
            // Otros días...
        ];
    }

    public function asignarTurnos($asignacionHoras) {
        // Implementación del método para asignar turnos
        // Ejemplo de retorno
        $turnos = [];
        foreach ($asignacionHoras as $dia => $franjas) {
            foreach ($franjas as $franja) {
                $turnos[] = [
                    'dia' => $dia,
                    'hora_inicio' => $franja['hora_inicio'],
                    'hora_fin' => $franja['hora_fin']
                ];
            }
        }
        return $turnos;
    }

    public function calcularHorasPorTurno($asignacionConTurnos) {
        // Implementación del método para calcular horas por turno
        // Ejemplo de retorno
        $horasPorTurno = [];
        foreach ($asignacionConTurnos as $turno) {
            $dia = $turno['dia'];
            $horaInicio = $turno['hora_inicio'];
            $horaFin = $turno['hora_fin'];
            $horasPorTurno[$dia][] = [
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'total_horas' => (strtotime($horaFin) - strtotime($horaInicio)) / 3600
            ];
        }
        return $horasPorTurno;
    }
}

// Ejemplo de uso
$servicio = new Servicio('08:00', '12:00', '14:00', '18:00', 'Lunes');

// Imprimir resultados de los métodos
echo "Rangos Horarios:\n";
print_r($servicio->getRangosHorarios());

echo "\nAsignacion de Horas:\n";
$asignacionHoras = $servicio->getAsignacionHoras();
print_r($asignacionHoras);

echo "\nAsignacion de Turnos:\n";
$asignacionConTurnos = $servicio->asignarTurnos($asignacionHoras);
print_r($asignacionConTurnos);

echo "\nCalculo de Horas por Turno:\n";
$horasPorTurno = $servicio->calcularHorasPorTurno($asignacionConTurnos);
print_r($horasPorTurno);

?>