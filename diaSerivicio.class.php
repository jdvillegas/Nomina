<?php

class DiaServicio {
    private $dia;
    private $ordinarios;
    private $festivos;

    public function __construct($dia, $ordinarios, $festivos) {
        $this->dia = $dia;
        $this->ordinarios = $ordinarios;
        $this->festivos = $festivos;
    }

    private function calcularHoras($hora_inicio, $hora_fin) {
        $inicio = new DateTime($hora_inicio);
        $fin = new DateTime($hora_fin);
        $interval = $inicio->diff($fin);

        // Check if minutes are 59 and adjust to 60
        if ($interval->i == 59) {
            $interval->i = 60;
        }

        $total_horas = $interval->h + ($interval->i / 60);

        // Assuming diurnal hours are from 6:00 to 21:00
        $diurnal_start = new DateTime('06:00');
        $diurnal_end = new DateTime('21:00');

        $diurnal_hours = 0;
        $nocturnal_hours = 0;

        $current = clone $inicio;
        while ($current < $fin) {
            if ($current >= $diurnal_start && $current < $diurnal_end) {
                $diurnal_hours++;
            } else {
                $nocturnal_hours++;
            }
            $current->modify('+1 hour');
        }

        return [
            'total_horas' => $total_horas,
            'diurnal_hours' => $diurnal_hours,
            'nocturnal_hours' => $nocturnal_hours
        ];
    }

    private function esTurnoPartido($horarios) {
        return !empty($horarios[2]) && !empty($horarios[3]);
    }

    private function horarios_franjas($horarios)
    {
        if($this->esTurnoPartido($horarios))
        {
            $franjas = array();
            $franjas[] = [
                'hora_inicio' => $horarios[0],
                'hora_finalizacion' => $horarios[1]
            ];
            $franjas[] = [
                'hora_inicio' => $horarios[2],
                'hora_finalizacion' => $horarios[3]
            ];
            return $franjas;
           
        }else
        {
           $franjas = array();
           $franjas[] = [
                'hora_inicio' => $horarios[0],
                'hora_finalizacion' => $horarios[1]
            ];
            return $franjas;
        }
    }

    private function esDiaSiguiente($hora_inicio, $hora_fin) {
        $inicio = new DateTime($hora_inicio);
        $fin = new DateTime($hora_fin);
        return $fin <= $inicio;
    }

    public function getRangosHorarios($horaInicio, $horaFin) {
        $inicio = new DateTime($horaInicio);
        $fin = new DateTime($horaFin);

        $rangos = [];

        if ($inicio < $fin) {
            // Caso 1: El horario no cruza la medianoche
            $rangos[] = [
                'es_dia_actual'=>true,
                'es_dia_siguiente'=>false,
                'inicio' => $inicio->format('H:i:s'),
                'fin' => $fin->format('H:i:s')
            ];
        } else {
            // Caso 2: El horario cruza la medianoche
            $rangos[] = [
                'es_dia_actual'=>true,
                'es_dia_siguiente'=>false,
                'inicio' => $inicio->format('H:i:s'),
                'fin' => '23:59:59'
            ];
            $rangos[] = [
                'es_dia_actual'=>false,
                'es_dia_siguiente'=>true,
                'inicio' => '00:00:00',
                'fin' => $fin->format('H:i:s')
            ];
        }

        return $rangos;
    }

    private function getDiaNombre($dia) {
        $dias = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
            8 => 'Lunes'
        ];
        return $dias[$dia] ?? 'Desconocido';
    }

    public function generarServicio() {
        $servicio = [
            'dia_inicio_turno' => $this->getDiaNombre($this->dia),
            'servicio' => [
                'con_feriados' => empty(array_filter($this->festivos)) ? 'no' : 'si',
                'feriados' => empty(array_filter($this->festivos)) ? [] : $this->procesarHorarios($this->festivos),
                'ordinarios' => $this->procesarHorarios($this->ordinarios)
            ]
        ];

        return $servicio;
    }

    private function procesarHorarios($horarios) {
        if (empty(array_filter($horarios))) {
            return [
                'hora_inicio' => '',
                'hora_finalizacion' => '',
                'cantidad_total_horas' => 0,
                'cantidad_total_horas_diurnas' => 0,
                'cantidad_total_horas_nocturnas' => 0,
                'cantidad_franjas' => 0,
                'horarios_franjas' => [],
                'dia_actual' => [
                    'nombre_dia' => '',
                    'cantidad_horas' => [],
                    'cantidad_diurnas' => [],
                    'cantidad_nocturnas' => []
                ],
                'dia_siguiente' => [
                    'nombre_dia' => '',
                    'cantidad_horas' => [],
                    'cantidad_diurnas' => [],
                    'cantidad_nocturnas' => []
                ]
            ];
        }

        $result = [
            'hora_inicio' => $horarios[0],
            'hora_finalizacion' => $horarios[1],
            'cantidad_total_horas' => 0,
            'cantidad_total_horas_diurnas' => 0,
            'cantidad_total_horas_nocturnas' => 0,
            'cantidad_franjas' => $this->esTurnoPartido($horarios) ? 2 : 1,
            'horarios_franjas' => $this->horarios_franjas($horarios),
            'dia_actual' => [
                'nombre_dia' => $this->getDiaNombre($this->dia),
                'cantidad_horas' => [],
                'cantidad_diurnas' => [],
                'cantidad_nocturnas' => []
            ],
            'dia_siguiente' => [
                'nombre_dia' => $this->getDiaNombre($this->dia+1),
                'cantidad_horas' => [],
                'cantidad_diurnas' => [],
                'cantidad_nocturnas' => []
            ]
        ];

        $rangos = $this->getRangosHorarios($horarios[0], $horarios[1]);
  
        foreach ($rangos as $rango) {
            $horas = $this->calcularHoras($rango['inicio'], $rango['fin']);
            $result['cantidad_total_horas'] += $horas['total_horas'];
            $result['cantidad_total_horas_diurnas'] += $horas['diurnal_hours'];
            $result['cantidad_total_horas_nocturnas'] += $horas['nocturnal_hours'];


            if ($rango['es_dia_actual'])
            {
                $horas_actual = $this->calcularHoras($rango['inicio'], '23:59');
                $result['dia_actual']['cantidad_horas'][] = $horas_actual['total_horas'];
                $result['dia_actual']['cantidad_diurnas'][] = $horas_actual['diurnal_hours'];
                $result['dia_actual']['cantidad_nocturnas'][] = $horas_actual['nocturnal_hours'];
                
            }
            
            if ($rango['es_dia_siguiente']) {
                
                $horas_siguiente = $this->calcularHoras('00:00', $rango['fin']);
                $result['dia_siguiente']['cantidad_horas'][] = $horas_siguiente['total_horas'];
                $result['dia_siguiente']['cantidad_diurnas'][] = $horas_siguiente['diurnal_hours'];
                $result['dia_siguiente']['cantidad_nocturnas'][] = $horas_siguiente['nocturnal_hours'];
            } 
        }

        if ($this->esTurnoPartido($horarios)) {
            $rangos_partido = $this->getRangosHorarios($horarios[2], $horarios[3]);
            foreach ($rangos_partido as $rango) {
                $horas_partido = $this->calcularHoras($rango['inicio'], $rango['fin']);
                $result['cantidad_total_horas'] += $horas_partido['total_horas'];
                $result['cantidad_total_horas_diurnas'] += $horas_partido['diurnal_hours'];
                $result['cantidad_total_horas_nocturnas'] += $horas_partido['nocturnal_hours'];

                if ($rango['es_dia_actual']) {
                    $horas_actual = $this->calcularHoras($rango['inicio'], '23:59');
                    $horas_siguiente = $this->calcularHoras('00:00', $rango['fin']);
                    $result['dia_actual']['cantidad_horas'][] = $horas_actual['total_horas'];
                    $result['dia_actual']['cantidad_diurnas'][] = $horas_actual['diurnal_hours'];
                    $result['dia_actual']['cantidad_nocturnas'][] = $horas_actual['nocturnal_hours'];
                }
                
                if ($rango['es_dia_siguiente']) {
                    $horas_actual = $this->calcularHoras($rango['inicio'], '23:59');
                    $horas_siguiente = $this->calcularHoras('00:00', $rango['fin']);
                    $result['dia_siguiente']['cantidad_horas'][] = $horas_siguiente['total_horas'];
                    $result['dia_siguiente']['cantidad_diurnas'][] = $horas_siguiente['diurnal_hours'];
                    $result['dia_siguiente']['cantidad_nocturnas'][] = $horas_siguiente['nocturnal_hours'];
                } 
            }
        }

        return $result;
    }
}

// Ejemplo de uso
$dia = 7;
$ordinarios = ['06:00', '06:00','',''];
$festivos = ['06:00', '06:00', '', ''];

$diaServicio = new DiaServicio($dia, $ordinarios, $festivos);
print_r($diaServicio->generarServicio());

?>