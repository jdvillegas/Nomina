<?php

class Servicio {
    private $horaInicio;
    private $horaFin;
    private $dia;
    private $horas_ordinarias_param = 8; // Parámetro para horas ordinarias
    private $maxHorasPorTurno = 12; // Máximo de horas por turno

    public function __construct($horaInicio, $horaFin, $dia) {
        $this->horaInicio = $horaInicio;
        $this->horaFin = $horaFin;
        $this->dia = $dia;
    }

    public function getRangosHorarios() {
        $inicio = new DateTime($this->horaInicio);
        $fin = new DateTime($this->horaFin);

        $rangos = [];

        if ($inicio < $fin) {
            // Caso 1: El horario no cruza la medianoche
            $rangos[] = [
                'inicio' => $inicio->format('H:i:s'),
                'fin' => $fin->format('H:i:s')
            ];
        } else {
            // Caso 2: El horario cruza la medianoche
            $rangos[] = [
                'inicio' => $inicio->format('H:i:s'),
                'fin' => '23:59:59'
            ];
            $rangos[] = [
                'inicio' => '00:00:00',
                'fin' => $fin->format('H:i:s')
            ];
        }

        return $rangos;
    }

    public function getAsignacionHoras() {
        $asignacionHoras = [];

        $inicio = new DateTime($this->horaInicio);
        $fin = new DateTime($this->horaFin);
        $franja_minutos = 60;
        $interval = new DateInterval('PT'.$franja_minutos.'M');
        $period = new DatePeriod($inicio, $interval, $fin > $inicio ? $fin : $fin->modify('+1 day'));

        $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $diaIndex = array_search($this->dia, $diasSemana);
        $diaActual = $diasSemana[$diaIndex];
        $diaSiguiente = $diasSemana[($diaIndex + 1) % 7];

        foreach ($period as $hora) {
            $horaStr = $hora->format('H:i:s');
            $asignacion = 'dia_actual';

            if ((int)$hora->format('H') < (int)$inicio->format('H')) {
                $asignacion = 'dia_siguiente';
            }

            $asignacionHoras[] = [
                'hora' => $horaStr,
                'minutos' => $franja_minutos,
                'asignacion' => $asignacion,
                'asignacion_turno' => [
                    'turno' => '',
                ],
                'hora_diurna' => $this->es_hora_diurna($horaStr),
                'hora_nocturna' => $this->es_hora_nocturna($horaStr),
                'dia' => $asignacion === 'dia_actual' ? $diaActual : $diaSiguiente
            ];
        }

        return [
            'servicio' => [
                'hora_inicio' => $this->horaInicio,
                'hora_fin' => $this->horaFin,
                'dia' => $diaActual,
                'asignacion' => $asignacionHoras
            ]
        ];
    }

    public function es_hora_diurna($hora) {
        $hora = new DateTime($hora);
        $inicioDiurno = new DateTime('06:00:00');
        $finDiurno = new DateTime('22:00:00');

        return ($hora >= $inicioDiurno && $hora < $finDiurno) ? 1 : 0;
    }

    public function es_hora_nocturna($hora) {
        return $this->es_hora_diurna($hora) ? 0 : 1;
    }

    public function asignarTurnos($asignacionHoras) {
        $turnoIndex = 1;
        $turnosPorDia = [];
        $minutosAsignados = 0;
        $diaInicioTurno = $asignacionHoras['servicio']['dia'];

        foreach ($asignacionHoras['servicio']['asignacion'] as &$franja) {
            $dia = $franja['dia'];
            if (!isset($turnosPorDia[$diaInicioTurno])) {
                $turnosPorDia[$diaInicioTurno] = 1;
            }

            if ($minutosAsignados >= $this->maxHorasPorTurno * 60) {
                $turnosPorDia[$diaInicioTurno]++;
                $minutosAsignados = 0;
                $diaInicioTurno = $dia; // Update the day when a new shift starts
            }

            $franja['asignacion_turno']['turno'] = 'T' . $diaInicioTurno . $turnosPorDia[$diaInicioTurno];
            $minutosAsignados += $franja['minutos'];
        }

        return $asignacionHoras;
    }

    public function calcularHorasPorTurno($asignacionConTurnos) {
        $horasPorTurno = [];

        foreach ($asignacionConTurnos['servicio']['asignacion'] as $franja) {
            $turno = $franja['asignacion_turno']['turno'];
            $dia = $franja['dia'];
            if (!isset($horasPorTurno[$turno])) {
                $horasPorTurno[$turno] = [
                    'total' => 0,
                    'ordinarias' => 0,
                    'extras_diurnas' => 0,
                    'extras_nocturnas' => 0,
                    'recargos_nocturnos' => 0,
                    'dias' => [],
                    'hora_inicio_turno' => $franja['hora'],
                    'hora_fin_turno' => $franja['hora']
                ];
            }

            if (!isset($horasPorTurno[$turno]['dias'][$dia])) {
                $horasPorTurno[$turno]['dias'][$dia] = [
                    'ordinarias' => 0,
                    'extras_diurnas' => 0,
                    'extras_nocturnas' => 0,
                    'recargos_nocturnos' => 0,
                    'hora_inicio' => $franja['hora'],
                    'hora_fin' => $franja['hora']
                ];
            }

            $horasPorTurno[$turno]['total'] += $franja['minutos'] / 60;

            if ($franja['hora_diurna']) {
                if ($horasPorTurno[$turno]['ordinarias'] < $this->horas_ordinarias_param) {
                    $horasPorTurno[$turno]['ordinarias'] += $franja['minutos'] / 60;
                    $horasPorTurno[$turno]['dias'][$dia]['ordinarias'] += $franja['minutos'] / 60;
                } else {
                    $horasPorTurno[$turno]['extras_diurnas'] += $franja['minutos'] / 60;
                    $horasPorTurno[$turno]['dias'][$dia]['extras_diurnas'] += $franja['minutos'] / 60;
                }
            } else if ($franja['hora_nocturna']) {
                if ($horasPorTurno[$turno]['ordinarias'] <= $this->horas_ordinarias_param) {
                    $horasPorTurno[$turno]['ordinarias'] += $franja['minutos'] / 60;
                    $horasPorTurno[$turno]['dias'][$dia]['ordinarias'] += $franja['minutos'] / 60;
                } else {
                    $horasPorTurno[$turno]['extras_nocturnas'] += $franja['minutos'] / 60;
                    $horasPorTurno[$turno]['dias'][$dia]['extras_nocturnas'] += $franja['minutos'] / 60;
                }
                $horasPorTurno[$turno]['recargos_nocturnos'] += $franja['minutos'] / 60;
                $horasPorTurno[$turno]['dias'][$dia]['recargos_nocturnos'] += $franja['minutos'] / 60;
            }

            // Update the end time of the shift
            $horasPorTurno[$turno]['dias'][$dia]['hora_fin'] = $franja['hora'];
            $horasPorTurno[$turno]['hora_fin_turno'] = $franja['hora'];
        }

        return $horasPorTurno;
    }
}

class TurnosServicios {
    private $servicios;

    public function __construct($servicios) {
        $this->servicios = $servicios;
    }

    public function calcularHorasPorTurno() {
        $turnos = [];

        foreach ($this->servicios as $servicio) {
            $asignacionHoras = $servicio->getAsignacionHoras();
            $asignacionConTurnos = $servicio->asignarTurnos($asignacionHoras);
            $horasPorTurno = $servicio->calcularHorasPorTurno($asignacionConTurnos);
            $turnos = array_merge($turnos, $horasPorTurno);
        }

        return $turnos;
    }

    public function calcularTotalHoras() {
        $totalHoras = 0;

        foreach ($this->servicios as $servicio) {
            $asignacionHoras = $servicio->getAsignacionHoras();
            $asignacionConTurnos = $servicio->asignarTurnos($asignacionHoras);
            $horasPorTurno = $servicio->calcularHorasPorTurno($asignacionConTurnos);

            foreach ($horasPorTurno as $turno) {
                $totalHoras += $turno['total'];
            }
        }

        return $totalHoras;
    }

    public function calcularCantidadPersonas() {
        $totalHoras = $this->calcularTotalHoras();
        $minHorasPorSemana = 48;
        $maxHorasPorSemana = 60;

        $cantidadMinimaPersonas = ceil($totalHoras / $maxHorasPorSemana);
        $cantidadMaximaPersonas = ceil($totalHoras / $minHorasPorSemana);

        return [
            'cantidad_minima_personas' => $cantidadMinimaPersonas,
            'cantidad_maxima_personas' => $cantidadMaximaPersonas
        ];
    }

    public function asignarPersonasATurnos($turnos, $cantidadPersonas) {
        $personas = [];
        for ($i = 1; $i <= $cantidadPersonas; $i++) {
            $personas[] = 'Persona' . $i;
        }

        $turnosAsignados = [];
        $personaIndex = 0;
        $turnosConsecutivos = 0;

        foreach ($turnos as $turno => $detalles) {
            $persona = $personas[$personaIndex];

            $turnosAsignados[$turno] = $detalles;
            $turnosAsignados[$turno]['persona'] = $persona;

            $turnosConsecutivos++;
            if ($turnosConsecutivos >= 4) {
                $turnosConsecutivos = 0;
                $personaIndex = ($personaIndex + 1) % $cantidadPersonas;
            }
        }

        return $turnosAsignados;
    }

    public function imprimirTurnosEnMatriz($turnosAsignados) {
        $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $personas = array_unique(array_column($turnosAsignados, 'persona'));
        $matriz = [];

        // Initialize matrix
        foreach ($personas as $persona) {
            $matriz[$persona] = array_fill_keys($diasSemana, '');
        }

        // Populate matrix
        foreach ($turnosAsignados as $turno => $detalles) {
            $persona = $detalles['persona'];
            foreach ($detalles['dias'] as $dia => $info) {
                $matriz[$persona][$dia] = $turno;
            }
        }

        // Print matrix
        echo str_pad('', 15) . implode(' | ', $diasSemana) . "\n";
        echo str_repeat('-', 15 * (count($diasSemana) + 1)) . "\n";
        foreach ($matriz as $persona => $dias) {
            echo str_pad($persona, 15);
            foreach ($diasSemana as $dia) {
                echo ' | ' . str_pad($dias[$dia], 15);
            }
            echo "\n";
        }
    }
}

// Ejemplo de uso
$servicio1 = new Servicio('06:00', '06:00', 'Lunes');
$servicio2 = new Servicio('06:00', '06:00', 'Martes');
$servicio3 = new Servicio('06:00', '06:00', 'Miércoles');
$servicio4 = new Servicio('06:00', '06:00', 'Jueves');
$servicio5 = new Servicio('06:00', '06:00', 'Viernes');
$servicio6 = new Servicio('06:00', '06:00', 'Sábado');
$servicio7 = new Servicio('06:00', '06:00', 'Domingo');

$turnosServicios = new TurnosServicios([$servicio1, $servicio2, $servicio3, $servicio4, $servicio5, $servicio6, $servicio7]);

echo "\nCálculo de Horas por Turno:\n";
$turnos = $turnosServicios->calcularHorasPorTurno();
print_r($turnos);


echo "\nTotal de Horas:\n";
$totalHoras = $turnosServicios->calcularTotalHoras();
echo $totalHoras . " horas\n";

echo "\nCantidad de Personas:\n";
$cantidadPersonas = $turnosServicios->calcularCantidadPersonas();
print_r($cantidadPersonas);

echo "\nAsignar Personas a Turnos (Cantidad Mínima):\n";
$turnosAsignadosMin = $turnosServicios->asignarPersonasATurnos($turnos, $cantidadPersonas['cantidad_minima_personas']);
//print_r($turnosAsignadosMin);

echo "\nAsignar Personas a Turnos (Cantidad Máxima):\n";
$turnosAsignadosMax = $turnosServicios->asignarPersonasATurnos($turnos, $cantidadPersonas['cantidad_maxima_personas']);
//print_r($turnosAsignadosMax);

echo "\nImprimir Turnos en Matriz (Cantidad Mínima):\n";
$turnosServicios->imprimirTurnosEnMatriz($turnosAsignadosMin);

echo "\nImprimir Turnos en Matriz (Cantidad Máxima):\n";
$turnosServicios->imprimirTurnosEnMatriz($turnosAsignadosMax);

?>