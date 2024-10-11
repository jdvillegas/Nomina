<?php

class Servicio {
    private $horaInicio;
    private $horaFin;
    private $dia;
    private $horas_ordinarias_param = 8; // Par�metro para horas ordinarias
    private $maxHorasPorTurno = 12; // M�ximo de horas por turno

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

        $diasSemana = ['Lunes', 'Martes', 'Mi�rcoles', 'Jueves', 'Viernes', 'S�bado', 'Domingo'];
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
        $finDiurno = new DateTime('21:00:00');

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
                    'recargos_diurnos' => 0,
                    'dias' => []
                ];
            }

            if (!isset($horasPorTurno[$turno]['dias'][$dia])) {
                $horasPorTurno[$turno]['dias'][$dia] = [
                    'ordinarias' => 0,
                    'extras_diurnas' => 0,
                    'extras_nocturnas' => 0,
                    'recargos_nocturnos' => 0,
                    'recargos_diurnos' => 0,
                    'hora_inicio' => $franja['hora'],
                    'hora_fin' => $franja['hora']
                ];
            }

            $horasPorTurno[$turno]['total'] += $franja['minutos'] / 60;

            if ($franja['hora_diurna']) {
                if ($horasPorTurno[$turno]['ordinarias'] < $this->horas_ordinarias_param) {
                    $horasPorTurno[$turno]['ordinarias'] += $franja['minutos'] / 60;
                    $horasPorTurno[$turno]['recargos_diurnos'] += $franja['minutos'] / 60;
                    $horasPorTurno[$turno]['dias'][$dia]['ordinarias'] += $franja['minutos'] / 60;                    
                    $horasPorTurno[$turno]['dias'][$dia]['recargos_diurnos'] += $franja['minutos'] / 60;                    
                } else {
                    $horasPorTurno[$turno]['extras_diurnas'] += $franja['minutos'] / 60;
                    $horasPorTurno[$turno]['dias'][$dia]['extras_diurnas'] += $franja['minutos'] / 60;
                }
            } else if ($franja['hora_nocturna']) {
                if ($horasPorTurno[$turno]['ordinarias'] < $this->horas_ordinarias_param) {
                    $horasPorTurno[$turno]['ordinarias'] += $franja['minutos'] / 60;
                    $horasPorTurno[$turno]['dias'][$dia]['ordinarias'] += $franja['minutos'] / 60;
                    $horasPorTurno[$turno]['recargos_nocturnos'] += $franja['minutos'] / 60;
                    $horasPorTurno[$turno]['dias'][$dia]['recargos_nocturnos'] += $franja['minutos'] / 60;
                } else {
                    $horasPorTurno[$turno]['extras_nocturnas'] += $franja['minutos'] / 60;
                    $horasPorTurno[$turno]['dias'][$dia]['extras_nocturnas'] += $franja['minutos'] / 60;
                }

            }

            // Update the end time of the shift
            $horasPorTurno[$turno]['dias'][$dia]['hora_fin'] = $franja['hora'];
        }

        return $horasPorTurno;
    }
}

// Ejemplo de uso
$servicio = new Servicio('06:00', '06:00', 'Domingo');

// Imprimir resultados de los m�todos
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