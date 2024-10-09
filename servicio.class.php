<?php

class Servicio {
    private $horaInicio;
    private $horaFin;
    private $dia;
    private $horas_ordinarias_param = 7.66; // Par�metro para horas ordinarias
    private $maxHorasPorTurno = 12; // M�ximo de horas por turno
    private $franjaMinutosAsignacion = 1;  // Franja de minutos para la asignaci�n de horas

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
        $franja_minutos = $this->franjaMinutosAsignacion;
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
        $minHorasPorSemana = 46;
        $maxHorasPorSemana = 60;

        $cantidadMinimaPersonas = floatval($totalHoras / $maxHorasPorSemana);
        $cantidadMaximaPersonas = floatval($totalHoras / $minHorasPorSemana);

        return [
            'cantidad_minima_personas' => $cantidadMinimaPersonas,
            'cantidad_maxima_personas' => $cantidadMaximaPersonas
        ];
    }

    public function asignarPersonasATurnos($turnos, $cantidadPersonas) {
        $personas = [];
        $cantidadPersonas =  ($cantidadPersonas<1)?1:$cantidadPersonas; // Al asignar personas puestos es 1
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
                $personaIndex = (($personaIndex + 1) % ceil($cantidadPersonas));
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

    public function imprimirTurnosDetallados($turnosAsignados) {
       // print_r($turnosAsignados);

        echo str_pad('Dia', 15) . str_pad('Persona', 15) . str_pad('Tipo', 15) . str_pad('Horario Inicial', 20) . str_pad('Horario Final', 20) . "\n";
        echo str_repeat('-', 85) . "\n";

        foreach ($turnosAsignados as $turno => $detalles) {
            $dia = array_key_first($detalles['dias']); // Obtener el día de la franja donde inicia el servicio
            echo str_pad($dia, 15);
            echo str_pad($detalles['persona'], 15);
            echo str_pad($turno, 15);
            echo str_pad($detalles['hora_inicio_turno'], 20);
            echo str_pad($detalles['hora_fin_turno'], 20);
            echo "\n";
        }
    }

    public function imprimirResumenTurnos($turnosAsignados) {
    // Print header
    echo str_pad('Turno', 15) . str_pad('Día', 10) . str_pad('Hora Inicio', 15) . str_pad('Hora Fin', 15) . str_pad('Total', 10) . str_pad('Ordinarias', 15) . str_pad('Extras Diurnas', 20) . str_pad('Extras Nocturnas', 20) . str_pad('Recargos Nocturnos', 20) . "\n";
    echo str_repeat('-', 140) . "\n";

    // Iterate through turnosAsignados
    foreach ($turnosAsignados as $turno => $detalles) {
        foreach ($detalles['dias'] as $dia => $franja) {
            $hora_inicio = $franja['hora_inicio'];
            $hora_fin = $franja['hora_fin'];
            $total = $detalles['total'];
            $ordinarias = $franja['ordinarias'];
            $extras_diurnas = $franja['extras_diurnas'];
            $extras_nocturnas = $franja['extras_nocturnas'];
            $recargos_nocturnos = $franja['recargos_nocturnos'];

            // Print details
            echo str_pad($turno, 15) . str_pad($dia, 10) . str_pad($hora_inicio, 15) . str_pad($hora_fin, 15) . str_pad($total, 10) . str_pad($ordinarias, 15) . str_pad($extras_diurnas, 20) . str_pad($extras_nocturnas, 20) . str_pad($recargos_nocturnos, 20) . "\n";
        }
    }
}

public function obtenerResumenTurnos($turnosAsignados) {
    $resumen = [];

    // Iterate through turnosAsignados
    foreach ($turnosAsignados as $turno => $detalles) {
        foreach ($detalles['dias'] as $dia => $franja) {
            $hora_inicio = $franja['hora_inicio'];
            $hora_fin = $franja['hora_fin'];
            $total = $detalles['total'];
            $ordinarias = $franja['ordinarias'];
            $extras_diurnas = $franja['extras_diurnas'];
            $extras_nocturnas = $franja['extras_nocturnas'];
            $recargos_nocturnos = $franja['recargos_nocturnos'];
            $recargos_diurnos = $franja['recargos_diurnos'];

            // Append details to resumen
            $resumen[] = [
                'turno' => $turno,
                'dia' => $dia,
                'hora_inicio' => $hora_inicio,
                'hora_fin' => $hora_fin,
                'total' => $total,
                'ordinarias' => $ordinarias,
                'extras_diurnas' => $extras_diurnas,
                'extras_nocturnas' => $extras_nocturnas,
                'recargos_nocturnos' => $recargos_nocturnos,
                'recargos_diurnos' => $recargos_diurnos,
            ];
        }
    }

    return $resumen;
}

function obtenerFechasAnio($anio) {
    $fechas = [];
    $feriadosColombia = [];

    // Fetch public holidays from the API
    $url = "https://date.nager.at/api/v3/publicholidays/$anio/CO";
    $response = file_get_contents($url);
    $feriados = json_decode($response, true);

    // Parse the API response to get the list of holidays
    foreach ($feriados as $feriado) {
        $feriadosColombia[] = (new DateTime($feriado['date']))->format('Y-m-d');
    }

    $inicio = new DateTime("$anio-01-01");
    $fin = new DateTime("$anio-12-31");

     $diasSemana = [
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sábado',
        'Sunday' => 'Domingo'
    ];

    // Mapping array for months in Spanish
    $meses = [
        'January' => 'Enero',
        'February' => 'Febrero',
        'March' => 'Marzo',
        'April' => 'Abril',
        'May' => 'Mayo',
        'June' => 'Junio',
        'July' => 'Julio',
        'August' => 'Agosto',
        'September' => 'Septiembre',
        'October' => 'Octubre',
        'November' => 'Noviembre',
        'December' => 'Diciembre'
    ];

    while ($inicio <= $fin) {
        $fecha = $inicio->format('Y-m-d');
        $diaSemana = $inicio->format('l'); // Nombre del día en inglés
        $mes = $inicio->format('F'); // Nombre del mes en inglés
        $esFeriado = in_array($fecha, $feriadosColombia) || $diaSemana == 'Sunday';

        $diaSemanaEsp = $diasSemana[$diaSemana];
        $mesEsp = $meses[$mes];

        $fechas[] = [
            'fecha' => $fecha,
            'dia_semana' => $diaSemanaEsp,
            'mes' => $mesEsp,
            'es_feriado' => $esFeriado?1:0
        ];

        $inicio->modify('+1 day');
    }

    return $fechas;
}

function organizarTurnosPorDiaYTurno($turnos) {
    $turnosOrganizados = [];

    // Iterate through the input array
    foreach ($turnos as $turno) {
        $dia = $turno['dia'];
        $turnoNombre = $turno['turno'];
        $turnoDetalles = [
            'hora_inicio' => $turno['hora_inicio'],
            'hora_fin' => $turno['hora_fin'],
            'total' => $turno['total'],
            'ordinarias' => $turno['ordinarias'],
            'extras_diurnas' => $turno['extras_diurnas'],
            'extras_nocturnas' => $turno['extras_nocturnas'],
            'recargos_nocturnos' => $turno['recargos_nocturnos'],
            'recargos_diurnos' => $turno['recargos_diurnos'],
        ];

        // Organize the turno details under the corresponding day and turno
        if (!isset($turnosOrganizados[$dia])) {
            $turnosOrganizados[$dia] = [
                'turnos' => [],
                'totales' => [
                    'ordinarias' => 0,
                    'extras_diurnas' => 0,
                    'extras_nocturnas' => 0,
                    'recargos_nocturnos' => 0,
                    'recargos_diurnos' => 0,
                    'cantidad_turnos' => 0
                ]
            ];
        }

        $turnosOrganizados[$dia]['turnos'][$turnoNombre] = $turnoDetalles;

        // Update the totals
        $turnosOrganizados[$dia]['totales']['ordinarias'] += $turno['ordinarias'];
        $turnosOrganizados[$dia]['totales']['extras_diurnas'] += $turno['extras_diurnas'];
        $turnosOrganizados[$dia]['totales']['extras_nocturnas'] += $turno['extras_nocturnas'];
        $turnosOrganizados[$dia]['totales']['recargos_nocturnos'] += $turno['recargos_nocturnos'];
        $turnosOrganizados[$dia]['totales']['recargos_diurnos'] += $turno['recargos_diurnos'];
        $turnosOrganizados[$dia]['totales']['cantidad_turnos'] += 1;
    }

    return $turnosOrganizados;
}

function adicionarTurnosACalendario($calendario, $turnosOrganizados) {
    // Iterate through the calendario array
    foreach ($calendario as &$dia) {
        $diaSemana = $dia['dia_semana'];

        // Check if there are turnos for this day
        if (isset($turnosOrganizados[$diaSemana])) {
            $dia['turnos'] = $turnosOrganizados[$diaSemana]['turnos'];
            $dia['totales'] = $turnosOrganizados[$diaSemana]['totales'];
        } else {
            $dia['turnos'] = [];
            $dia['totales'] = [
                'ordinarias' => 0,
                'extras_diurnas' => 0,
                'extras_nocturnas' => 0,
                'recargos_nocturnos' => 0,
                'recargos_diurnos' => 0,
                'cantidad_turnos' => 0
            ];
        }
    }

    return $calendario;
}

function resumenMensualYDiario($calendarioConTurnos) {
    $resumen = [];

    // Iterate through the calendarioConTurnos array
    foreach ($calendarioConTurnos as $dia) {
        $mes = $dia['mes'];
        $diaSemana = $dia['dia_semana'];
        $esFeriado = $dia['es_feriado'] ? 'feriado' : 'no_feriado';

        // Initialize the corresponding month and day in the resumen array if not already set
        if (!isset($resumen[$mes])) {
            $resumen[$mes] = [
                'totales' => [
                    'ordinarias' => 0,
                    'extras_diurnas' => 0,
                    'extras_nocturnas' => 0,
                    'recargos_nocturnos' => 0,
                    'recargos_diurnos' => 0,
                    'cantidad_dias' => 0
                ],
                'totales_ordinarios' => [
                    'ordinarias' => 0,
                    'extras_diurnas' => 0,
                    'extras_nocturnas' => 0,
                    'recargos_nocturnos' => 0,
                    'recargos_diurnos' => 0,
                    'cantidad_dias' => 0
                ],
                'totales_feriados' => [
                    'ordinarias' => 0,
                    'extras_diurnas' => 0,
                    'extras_nocturnas' => 0,
                    'recargos_nocturnos' => 0,
                    'recargos_diurnos' => 0,
                    'cantidad_dias' => 0
                ],
                'dias' => []
            ];
        }

        if (!isset($resumen[$mes]['dias'][$diaSemana])) {
            $resumen[$mes]['dias'][$diaSemana] = [
                'no_feriado' => [
                    'ordinarias' => 0,
                    'extras_diurnas' => 0,
                    'extras_nocturnas' => 0,
                    'recargos_nocturnos' => 0,
                    'recargos_diurnos' => 0,
                    'cantidad_dias' => 0,
                    'turnos' => []
                ],
                'feriado' => [
                    'ordinarias' => 0,
                    'extras_diurnas' => 0,
                    'extras_nocturnas' => 0,
                    'recargos_nocturnos' => 0,
                    'recargos_diurnos' => 0,
                    'cantidad_dias' => 0,
                    'turnos' => []
                ]
            ];
        }

        // Sum the values for regular and feriado days
        $resumen[$mes]['totales']['ordinarias'] += $dia['totales']['ordinarias'];
        $resumen[$mes]['totales']['extras_diurnas'] += $dia['totales']['extras_diurnas'];
        $resumen[$mes]['totales']['extras_nocturnas'] += $dia['totales']['extras_nocturnas'];
        $resumen[$mes]['totales']['recargos_nocturnos'] += $dia['totales']['recargos_nocturnos'];
        $resumen[$mes]['totales']['recargos_diurnos'] += (($esFeriado === 'feriado')?$dia['totales']['recargos_diurnos']:0);
        $resumen[$mes]['totales']['cantidad_dias']++;

        if ($esFeriado === 'feriado') {
            $resumen[$mes]['totales_feriados']['ordinarias'] += $dia['totales']['ordinarias'];
            $resumen[$mes]['totales_feriados']['extras_diurnas'] += $dia['totales']['extras_diurnas'];
            $resumen[$mes]['totales_feriados']['extras_nocturnas'] += $dia['totales']['extras_nocturnas'];
            $resumen[$mes]['totales_feriados']['recargos_nocturnos'] += $dia['totales']['recargos_nocturnos'];
            $resumen[$mes]['totales_feriados']['recargos_diurnos'] += $dia['totales']['recargos_diurnos'];
            $resumen[$mes]['totales_feriados']['cantidad_dias']++;
        } else {
            $resumen[$mes]['totales_ordinarios']['ordinarias'] += $dia['totales']['ordinarias'];
            $resumen[$mes]['totales_ordinarios']['extras_diurnas'] += $dia['totales']['extras_diurnas'];
            $resumen[$mes]['totales_ordinarios']['extras_nocturnas'] += $dia['totales']['extras_nocturnas'];
            $resumen[$mes]['totales_ordinarios']['recargos_nocturnos'] += $dia['totales']['recargos_nocturnos'];
            $resumen[$mes]['totales_ordinarios']['recargos_diurnos'] += 0;
            $resumen[$mes]['totales_ordinarios']['cantidad_dias']++;
        }

        $resumen[$mes]['dias'][$diaSemana][$esFeriado]['ordinarias'] += $dia['totales']['ordinarias'];
        $resumen[$mes]['dias'][$diaSemana][$esFeriado]['extras_diurnas'] += $dia['totales']['extras_diurnas'];
        $resumen[$mes]['dias'][$diaSemana][$esFeriado]['extras_nocturnas'] += $dia['totales']['extras_nocturnas'];
        $resumen[$mes]['dias'][$diaSemana][$esFeriado]['recargos_nocturnos'] += $dia['totales']['recargos_nocturnos'];
        $resumen[$mes]['dias'][$diaSemana][$esFeriado]['recargos_diurnos'] += $dia['totales']['recargos_diurnos'];
        $resumen[$mes]['dias'][$diaSemana][$esFeriado]['cantidad_dias']++;

        // Count the appearances of each turno
        foreach ($dia['turnos'] as $turnoNombre => $turnoDetalles) {
            if (!isset($resumen[$mes]['dias'][$diaSemana][$esFeriado]['turnos'][$turnoNombre])) {
                $resumen[$mes]['dias'][$diaSemana][$esFeriado]['turnos'][$turnoNombre] = 0;
            }
            $resumen[$mes]['dias'][$diaSemana][$esFeriado]['turnos'][$turnoNombre]++;
        }
    }

    return $resumen;
}
   
function liquidarTurnos($resumen, $valor_hora, $cantidad_personas) {
    $liquidacion = [];

    // Separate the integer and fractional parts of cantidad_personas
    $cantidad_entera = floor($cantidad_personas);
    $cantidad_fraccionaria = $cantidad_personas - $cantidad_entera;

    // Iterate through the resumen array
    foreach ($resumen as $mes => $datosMes) {
        $liquidacion[$mes] = [
            'cantidad_personas' => $cantidad_personas,
            'valor_hora'=>$valor_hora,
            'QHED' => $datosMes['totales_ordinarios']['extras_diurnas'],
            'HED' =>  1.25 * $valor_hora,
            'VHED' => round($datosMes['totales_ordinarios']['extras_diurnas'] * 1.25 * $valor_hora,0),
            'QHEN' => $datosMes['totales_ordinarios']['extras_nocturnas'],
            'HEN'=> 1.75 * $valor_hora,
            'VHEN' => round($datosMes['totales_ordinarios']['extras_nocturnas'] * 1.75 * $valor_hora,0),
            'QRN' => $datosMes['totales_ordinarios']['recargos_nocturnos'],
            'RN'=> 0.35 * $valor_hora,
            'VRN' => round($datosMes['totales_ordinarios']['recargos_nocturnos'] * 0.35 * $valor_hora,0),
            'QRFD' => $datosMes['totales_feriados']['recargos_diurnos'] ,
            'RFD' => 0.75 * $valor_hora,            
            'VRFD' => round(($datosMes['totales_feriados']['recargos_diurnos']) * 0.75 * $valor_hora,0),
            'HEFN'=> 0.35 * $valor_hora,
            'QHEFN' => $datosMes['totales_feriados']['extras_nocturnas'],
            'VHEFN' => round($datosMes['totales_feriados']['extras_nocturnas'] * 2.50 * $valor_hora,0),
            'HRFN'=>  1.10 * $valor_hora,
            'QRFN' => $datosMes['totales_feriados']['recargos_nocturnos'],
            'VRFN' => round($datosMes['totales_feriados']['recargos_nocturnos'] * 1.10 * $valor_hora,0),
            'HEFD'=> 2 * $valor_hora,
            'QHFD' => $datosMes['totales_feriados']['extras_diurnas'],
            'VHFD' => round($datosMes['totales_feriados']['extras_diurnas'] * 2 * $valor_hora,0)
        ];

        // Calculate the proportion for each person
        $liquidacion[$mes]['proporcion_entera'] = [
            'cantidad_personas' => $cantidad_entera,
            'valor_extras_diurnas' => $liquidacion[$mes]['VHED'] / $cantidad_personas,
            'valor_extras_nocturnas' => $liquidacion[$mes]['VHEN'] / $cantidad_personas,
            'valor_recargos_nocturnos' => $liquidacion[$mes]['VRN'] / $cantidad_personas,
            'valor_recargos_festivo' => $liquidacion[$mes]['VRFD'] / $cantidad_personas,
            'valor_horas_extras_festivas_nocturnas' => $liquidacion[$mes]['VHEFN'] / $cantidad_personas,
            'valor_recargos_festivos_nocturnos' => $liquidacion[$mes]['VRFN'] / $cantidad_personas,
            'valor_hora_extra_festiva_diurna' => $liquidacion[$mes]['VHFD'] / $cantidad_personas
        ];

        $liquidacion[$mes]['proporcion_fraccionaria'] = [
            'cantidad_personas' => $cantidad_fraccionaria,
            'valor_extras_diurnas' => $liquidacion[$mes]['VHED'] * $cantidad_fraccionaria,
            'valor_extras_nocturnas' => $liquidacion[$mes]['VHEN'] * $cantidad_fraccionaria,
            'valor_recargos_nocturnos' => $liquidacion[$mes]['VRN'] * $cantidad_fraccionaria,
            'valor_recargos_festivo' => $liquidacion[$mes]['VRFD'] * $cantidad_fraccionaria,
            'valor_horas_extras_festivas_nocturnas' => $liquidacion[$mes]['VHEFN'] * $cantidad_fraccionaria,
            'valor_recargos_festivos_nocturnos' => $liquidacion[$mes]['VRFN'] * $cantidad_fraccionaria,
            'valor_hora_extra_festiva_diurna' => $liquidacion[$mes]['VHFD'] * $cantidad_fraccionaria
        ];
    }

    return $liquidacion;
}

function imprimirMatrizLiquidacion($liquidacion, $tipo = 'Horas', $proporcionalidad = 3) {
    
    if ($tipo == 'Horas') {
        // Initialize the matrix with rows for each quantity type
        $matriz = [
            'QHED' => [],
            'QHEN' => [],
            'QRN' => [],
            'QRFD' => [],
            'QHEFN' => [],
            'QRFN' => [],
            'QHFD' => []
           

        ];

        // Populate the matrix with values from the liquidacion array
        foreach ($liquidacion as $mes => $datos) {
            $matriz['QHED'][$mes] = $datos['QHED'];
            $matriz['QHEN'][$mes] = $datos['QHEN'];
            $matriz['QRN'][$mes] = $datos['QRN'];
            $matriz['QRFD'][$mes] = $datos['QRFD'];
            $matriz['QHEFN'][$mes] = $datos['QHEFN'];
            $matriz['QRFN'][$mes] = $datos['QRFN'];
            $matriz['QHFD'][$mes] = $datos['QHFD'];
        }

        // Print the matrix in a tabular format
        echo "Mes----\tEnero\tFebrer\tMarzo\tAbril\tMayo \tJunio\tJulio\tAgost\tSeptie\tOctub\tNovie\tDicie\tTotal\tPromedio\n";
        foreach ($matriz as $tipo => $valores) {
            echo str_pad($tipo, 7, ' ', STR_PAD_RIGHT) . "\t";
            $total = 0;
            $count = 0;
            foreach ($valores as $mes => $valorx) {
                $valor = round($valorx,2);
                echo str_pad($valor, 5, ' ', STR_PAD_RIGHT) . "\t";
                $total += $valor;
                $count++;
            }
            $promedio = $count > 0 ? $total / $count : 0;
            echo str_pad($total, 5, ' ', STR_PAD_RIGHT) . "\t";
            echo str_pad(number_format($promedio, 2), 5, ' ', STR_PAD_RIGHT) . "\n";
        }
        echo "\n";
        echo "\n";
    }

    if ($tipo == 'Valor') {
        // Initialize the matrix with rows for each quantity type
        $matriz = [
            'VHED' => [],
            'VHEN' => [],
            'VRN' => [],
            'VRFD' => [],
            'VHEFN' => [],
            'VRFN' => [],
            'VHFD' => [], 
            'TVAR' => [],
            'TPER' => []
        ];

        // Populate the matrix with values from the liquidacion array
        foreach ($liquidacion as $mes => $datos) {
            $matriz['VHED'][$mes] = $datos['VHED'];
            $matriz['VHEN'][$mes] = $datos['VHEN'];
            $matriz['VRN'][$mes] = $datos['VRN'];
            $matriz['VRFD'][$mes] = $datos['VRFD'];
            $matriz['VHEFN'][$mes] = $datos['VHEFN'];
            $matriz['VRFN'][$mes] = $datos['VRFN'];
            $matriz['VHFD'][$mes] = $datos['VHFD'];
            $matriz['TVAR'][$mes] = $datos['VHED']+$datos['VHEN']+$datos['VRN']+$datos['VRFD']+$datos['VHEFN']+$datos['VRFN']+$datos['VHFD'];
            
            $matriz['TPER'][$mes] = ($datos['VHED']+$datos['VHEN']+$datos['VRN']+$datos['VRFD']+$datos['VHEFN']+$datos['VRFN']+$datos['VHFD'])*($proporcionalidad<1?$proporcionalidad:(1/$proporcionalidad));
        }

        // Print the matrix in a tabular format
        echo "Mes----\tEnero    \tFebrer   \tMarzo    \tAbril   \tMayo     \tJunio    \tJulio    \tAgost    \tSeptie    \tOctub    \tNovie    \tDicie   \tTotal     \tPromedio\n";
        foreach ($matriz as $tipo => $valores) {
            echo str_pad($tipo, 7, ' ', STR_PAD_RIGHT) . "\t";
            $total = 0;
            $count = 0;
            foreach ($valores as $mes => $valor) {
                echo str_pad(number_format($valor, 0, '.', ','), 8, ' ', STR_PAD_RIGHT) . "\t";
                $total += $valor;
                $count++;
            }
            $promedio = $count > 0 ? $total / $count : 0;
            echo str_pad(number_format($total, 0, '.', ','), 8, ' ', STR_PAD_RIGHT) . "\t";
            echo str_pad(number_format($promedio, 0, '.', ','), 8, ' ', STR_PAD_RIGHT) . "\n";
        }
        echo "\n";
    }
}

function generarMatrizLiquidacionJSON($liquidacion, $proporcionalidad = 3) {
    $matriz = [
        'Horas' => [
            'QHED' => [],
            'QHEN' => [],
            'QRN' => [],
            'QRFD' => [],
            'QHEFN' => [],
            'QRFN' => [],
            'QHFD' => []
        ],
        'Valor' => [
            'VHED' => [],
            'VHEN' => [],
            'VRN' => [],
            'VRFD' => [],
            'VHEFN' => [],
            'VRFN' => [],
            'VHFD' => [],
            'TVAR' => [],
            'TPER' => []
        ]
    ];

    // Populate the matrix with values from the liquidacion array
    foreach ($liquidacion as $mes => $datos) {
        // Horas
        $matriz['Horas']['QHED'][$mes] = round($datos['QHED'],2);
        $matriz['Horas']['QHEN'][$mes] = round($datos['QHEN'],2);
        $matriz['Horas']['QRN'][$mes] = round($datos['QRN'],2);
        $matriz['Horas']['QRFD'][$mes] = round($datos['QRFD'],2);
        $matriz['Horas']['QHEFN'][$mes] = round($datos['QHEFN'],2);
        $matriz['Horas']['QRFN'][$mes] = round($datos['QRFN'],2);
        $matriz['Horas']['QHFD'][$mes] = round($datos['QHFD'],2);

        // Valor
        $matriz['Valor']['VHED'][$mes] = $datos['VHED'];
        $matriz['Valor']['VHEN'][$mes] = $datos['VHEN'];
        $matriz['Valor']['VRN'][$mes] = $datos['VRN'];
        $matriz['Valor']['VRFD'][$mes] = $datos['VRFD'];
        $matriz['Valor']['VHEFN'][$mes] = $datos['VHEFN'];
        $matriz['Valor']['VRFN'][$mes] = $datos['VRFN'];
        $matriz['Valor']['VHFD'][$mes] = $datos['VHFD'];
        $matriz['Valor']['TVAR'][$mes] = $datos['VHED'] + $datos['VHEN'] + $datos['VRN'] + $datos['VRFD'] + $datos['VHEFN'] + $datos['VRFN'] + $datos['VHFD'];
        $matriz['Valor']['TPER'][$mes] = ($datos['VHED'] + $datos['VHEN'] + $datos['VRN'] + $datos['VRFD'] + $datos['VHEFN'] + $datos['VRFN'] + $datos['VHFD']) * ($proporcionalidad < 1 ? $proporcionalidad : (1 / $proporcionalidad));
    }

    // Calculate total and average for each row in Horas
    foreach ($matriz['Horas'] as $tipo => $valores) {
        $total = array_sum($valores);
        $count = count($valores);
        $promedio = $count > 0 ? $total / $count : 0;
        $matriz['Horas'][$tipo]['Total'] = $total;
        $matriz['Horas'][$tipo]['Promedio'] = $promedio;
    }

    // Calculate total and average for each row in Valor
    foreach ($matriz['Valor'] as $tipo => $valores) {
        $total = array_sum($valores);
        $count = count($valores);
        $promedio = $count > 0 ? $total / $count : 0;
        $matriz['Valor'][$tipo]['Total'] = $total;
        $matriz['Valor'][$tipo]['Promedio'] = $promedio;
    }

    return json_encode($matriz, JSON_PRETTY_PRINT);
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

//$turnosServicios = new TurnosServicios([$servicio7]);

//echo "\nCálculo de Horas por Turno:\n";
$turnos = $turnosServicios->calcularHorasPorTurno();
//print_r($turnos);


//echo "\nTotal de Horas:\n";
$totalHoras = $turnosServicios->calcularTotalHoras();
//echo $totalHoras . " horas\n";

//echo "\nCantidad de Personas:\n";
$cantidadPersonas = $turnosServicios->calcularCantidadPersonas();
//print_r($cantidadPersonas);

//echo "\nAsignar Personas a Turnos (Cantidad Mínima):\n";
$proporcionalidad = 3;

$turnosAsignadosMin = $turnosServicios->asignarPersonasATurnos($turnos, $proporcionalidad);
//print_r($turnosAsignadosMin);

//echo "\nAsignar Personas a Turnos (Cantidad Máxima):\n";
$turnosAsignadosMax = $turnosServicios->asignarPersonasATurnos($turnos, $proporcionalidad);
//print_r($turnosAsignadosMax);

$turnos_por_dia = $turnosServicios->obtenerResumenTurnos($turnosAsignadosMin);
//print_r($turnos_por_dia);

//echo "\nOrganizar Turnos por Día y Turno:\n";
$turnosOrganizados = $turnosServicios->organizarTurnosPorDiaYTurno($turnos_por_dia);
//print_r($turnosOrganizados);

//echo "\nCalendario:\n";
$anio = 2024;
$calendario = $turnosServicios->obtenerFechasAnio($anio);
//print_r($calendario);

//echo "\nAdicionarTurnosCalendario:\n";
$calendarioConTurnos = $turnosServicios->adicionarTurnosACalendario($calendario, $turnosOrganizados);
//print_r($calendarioConTurnos);


$resumenDiario = $turnosServicios->resumenMensualYDiario($calendarioConTurnos);
//print_r($resumenDiario);

$valor_hora = ((1300000/30)/7.6667); // Ejemplo de valor hora
$valor_hora = ((1300000/230)); // Ejemplo de valor hora

//echo $valor_hora;
$liquidacion = $turnosServicios->liquidarTurnos($resumenDiario, round($valor_hora,0), $proporcionalidad );
//print_r($liquidacion);
print_r($cantidadPersonas);


$turnosServicios->imprimirMatrizLiquidacion($liquidacion,'Horas', $proporcionalidad);
$turnosServicios->imprimirMatrizLiquidacion($liquidacion,'Valor', $proporcionalidad);

print_r($turnosServicios->generarMatrizLiquidacionJSON($liquidacion, $proporcionalidad = 3));



?>