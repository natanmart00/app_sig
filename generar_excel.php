<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

$fecha_generacion = date('d/m/Y H:i:s');

$COLOR_ENCABEZADO_FONDO = '2C3E50';
$COLOR_ENCABEZADO_FUENTE = 'FFFFFF';
$COLOR_TITULO_FONDO = '1ABC9C';
$COLOR_TITULO_FUENTE = 'FFFFFF';
$COLOR_FILA_ALTERNA = 'F8F9FA';
$COLOR_BORDE = 'DEE2E6';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'No se recibieron datos válidos.']);
    exit;
}

function aplicar_estilo_encabezado($hoja, $rango, $color_fondo, $color_fuente) {
    if (!$hoja || !$rango) {
        return;
    }

    // configuramos el estilo del encabezado
    $hoja->getStyle($rango)->applyFromArray([
        'font' => [
            'bold'  => true,
            'color' => ['rgb' => $color_fuente],
            'size'  => 11
        ],
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $color_fondo]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => '999999']
            ]
        ]
    ]);
}

// aplicamos estilos de datos a un rango de celdas del cuerpo
function aplicar_estilo_datos($hoja, $rango, $color_borde) {

    if (!$hoja || !$rango) {
        return;
    }

    // configuramos bordes y alineacion
    $hoja->getStyle($rango)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => $color_borde]
            ]
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ]);
}


// aplicamos color alterno a las filas pares para mejorar la legibilidad
function aplicar_filas_alternas($hoja, $fila_inicio, $fila_fin, $columna_inicio, $columna_fin, $color_alterno) {
   
    if (!$hoja || $fila_inicio >= $fila_fin) {
        return;
    }

    // recorremos las filas aplicando color a las pares
    for ($fila = $fila_inicio; $fila <= $fila_fin; $fila++) {
        // si la fila es par, aplicamos el color de fondo alterno
        if (($fila - $fila_inicio) % 2 === 1) {
            $rango_fila = $columna_inicio . $fila . ':' . $columna_fin . $fila;
            $hoja->getStyle($rango_fila)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($color_alterno);
        }
    }
}

// escribimos un titulo de seccion en una celda con estilo destacado
function escribir_titulo_seccion($hoja, $celda, $texto, $rango_merge, $color_fondo, $color_fuente) {

    if (!$hoja || !$celda || !$texto) {
        return;
    }

    // escribimos el valor del titulo
    $hoja->setCellValue($celda, $texto);

    // fusionamos las celdas del rango indicado
    $hoja->mergeCells($rango_merge);

    // aplicamos estilo del titulo de seccion
    $hoja->getStyle($rango_merge)->applyFromArray([
        'font' => [
            'bold'  => true,
            'color' => ['rgb' => $color_fuente],
            'size'  => 13
        ],
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $color_fondo]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER
        ]
    ]);

    // ajustamos la altura de la fila para el titulo
    $numero_fila = (int) filter_var($celda, FILTER_SANITIZE_NUMBER_INT);
    $hoja->getRowDimension(abs($numero_fila))->setRowHeight(30);
}


// poblamos la hoja de top 10 canciones
function hoja_canciones($hoja, $lista_canciones, $colores) {
    
    if (!$lista_canciones || count($lista_canciones) === 0) {
        $hoja->setCellValue('A1', 'No hay datos disponibles');
        return;
    }

    // escribimos el titulo de la seccion
    escribir_titulo_seccion($hoja, 'A1', 'Top 10 Canciones por Popularidad', 'A1:G1', $colores['titulo_fondo'], $colores['titulo_fuente']);

    // definimos los encabezados de columna
    $encabezados_cancion = ['#', 'Canción', 'Artista', 'Álbum', 'Duración', 'Explícita', 'Rank'];
    $columnas = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

    // recorremos los encabezados para colocarlos en la fila 2
    foreach ($encabezados_cancion as $indice => $encabezado) {
        $hoja->setCellValue($columnas[$indice] . '2', $encabezado);
    }

    // aplicamos estilo de encabezado
    aplicar_estilo_encabezado($hoja, 'A2:G2', $colores['encabezado_fondo'], $colores['encabezado_fuente']);

    // recorremos las canciones para poblar las filas desde la 3
    $fila_actual = 3;
    foreach ($lista_canciones as $indice => $cancion) {
        // calculamos la duracion formateada
        $minutos = floor($cancion['duracion_seg'] / 60);
        $segundos = str_pad($cancion['duracion_seg'] % 60, 2, '0', STR_PAD_LEFT);

        $hoja->setCellValue('A' . $fila_actual, $indice + 1);
        $hoja->setCellValue('B' . $fila_actual, $cancion['titulo']);
        $hoja->setCellValue('C' . $fila_actual, $cancion['artista']);
        $hoja->setCellValue('D' . $fila_actual, $cancion['album']);
        $hoja->setCellValue('E' . $fila_actual, $minutos . ':' . $segundos);
        $hoja->setCellValue('F' . $fila_actual, $cancion['explicita']);
        $hoja->setCellValue('G' . $fila_actual, $cancion['rank']);

        $fila_actual++;
    }

    // aplicamos estilos al cuerpo de datos
    $fila_fin = $fila_actual - 1;
    aplicar_estilo_datos($hoja, 'A3:G' . $fila_fin, $colores['borde']);
    aplicar_filas_alternas($hoja, 3, $fila_fin, 'A', 'G', $colores['fila_alterna']);

    // ajustamos el ancho de las columnas automaticamente
    foreach ($columnas as $col) {
        $hoja->getColumnDimension($col)->setAutoSize(true);
    }

    // centramos las columnas numericas
    $hoja->getStyle('A3:A' . $fila_fin)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $hoja->getStyle('E3:F' . $fila_fin)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $hoja->getStyle('G3:G' . $fila_fin)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}


// poblamos la hoja de top 15 artistas
function hoja_artistas($hoja, $lista_artistas, $colores) {
    
    if (!$lista_artistas || count($lista_artistas) === 0) {
        $hoja->setCellValue('A1', 'No hay datos disponibles');
        return;
    }

    // escribimos el titulo de la seccion
    escribir_titulo_seccion($hoja, 'A1', 'Top 15 Artistas por Fans', 'A1:C1', $colores['titulo_fondo'], $colores['titulo_fuente']);

    // definimos los encabezados
    $hoja->setCellValue('A2', '#');
    $hoja->setCellValue('B2', 'Artista');
    $hoja->setCellValue('C2', 'Fans Estimados');

    // aplicamos estilo de encabezado
    aplicar_estilo_encabezado($hoja, 'A2:C2', $colores['encabezado_fondo'], $colores['encabezado_fuente']);

    // recorremos los artistas para poblar desde la fila 3
    $fila_actual = 3;
    foreach ($lista_artistas as $indice => $artista) {
        $hoja->setCellValue('A' . $fila_actual, $indice + 1);
        $hoja->setCellValue('B' . $fila_actual, $artista['nombre']);
        $hoja->setCellValue('C' . $fila_actual, $artista['fans']);

        // formateamos los fans con separador de miles
        $hoja->getStyle('C' . $fila_actual)->getNumberFormat()->setFormatCode('#,##0');

        $fila_actual++;
    }

    // aplicamos estilos al cuerpo
    $fila_fin = $fila_actual - 1;
    aplicar_estilo_datos($hoja, 'A3:C' . $fila_fin, $colores['borde']);
    aplicar_filas_alternas($hoja, 3, $fila_fin, 'A', 'C', $colores['fila_alterna']);

    // ajustamos las columnas
    $hoja->getColumnDimension('A')->setAutoSize(true);
    $hoja->getColumnDimension('B')->setAutoSize(true);
    $hoja->getColumnDimension('C')->setAutoSize(true);

    // centramos la columna numerica
    $hoja->getStyle('A3:A' . $fila_fin)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $hoja->getStyle('C3:C' . $fila_fin)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}


// poblamos la hoja de estadisticas generales (explicito, generos, podcasts)
function hoja_estadisticas($hoja, $datos, $colores) {
    
    if (!$datos) {
        $hoja->setCellValue('A1', 'No hay datos disponibles');
        return;
    }

    $fila_actual = 1;

    // seccion de contenido explicito 
    escribir_titulo_seccion($hoja, 'A' . $fila_actual, 'Contenido Explícito vs Apto', 'A' . $fila_actual . ':C' . $fila_actual, $colores['titulo_fondo'], $colores['titulo_fuente']);
    $fila_actual++;

    // escribimos encabezados de la seccion explicita
    $hoja->setCellValue('A' . $fila_actual, 'Categoría');
    $hoja->setCellValue('B' . $fila_actual, 'Cantidad');
    $hoja->setCellValue('C' . $fila_actual, 'Porcentaje');
    aplicar_estilo_encabezado($hoja, 'A' . $fila_actual . ':C' . $fila_actual, $colores['encabezado_fondo'], $colores['encabezado_fuente']);
    $fila_actual++;

    // calculamos los porcentajes de contenido explicito
    $explicito = $datos['contenido_explicito'] ?? ['explicito' => 0, 'apto' => 0];
    $total = $explicito['explicito'] + $explicito['apto'];
    $pct_explicito = $total > 0 ? round(($explicito['explicito'] / $total) * 100, 1) : 0;
    $pct_apto = $total > 0 ? round(($explicito['apto'] / $total) * 100, 1) : 0;

    // escribimos las filas de datos
    $hoja->setCellValue('A' . $fila_actual, 'Explícito (Adultos)');
    $hoja->setCellValue('B' . $fila_actual, $explicito['explicito']);
    $hoja->setCellValue('C' . $fila_actual, $pct_explicito . '%');
    $fila_actual++;

    $hoja->setCellValue('A' . $fila_actual, 'Apto Todo Público');
    $hoja->setCellValue('B' . $fila_actual, $explicito['apto']);
    $hoja->setCellValue('C' . $fila_actual, $pct_apto . '%');

    // aplicamos estilos a la sub-tabla
    aplicar_estilo_datos($hoja, 'A' . ($fila_actual - 1) . ':C' . $fila_actual, $colores['borde']);
    $fila_actual += 2;

    // --- seccion de generos musicales ---
    escribir_titulo_seccion($hoja, 'A' . $fila_actual, 'Distribución de Géneros en Top 100', 'A' . $fila_actual . ':C' . $fila_actual, $colores['titulo_fondo'], $colores['titulo_fuente']);
    $fila_actual++;

    // escribimos encabezados de generos
    $hoja->setCellValue('A' . $fila_actual, 'Género');
    $hoja->setCellValue('B' . $fila_actual, 'Canciones');
    aplicar_estilo_encabezado($hoja, 'A' . $fila_actual . ':B' . $fila_actual, $colores['encabezado_fondo'], $colores['encabezado_fuente']);
    $fila_actual++;

    // recorremos los generos para poblar la tabla
    $fila_inicio_generos = $fila_actual;
    $generos = $datos['generos'] ?? [];
    foreach ($generos as $nombre_genero => $cantidad) {
        $hoja->setCellValue('A' . $fila_actual, $nombre_genero);
        $hoja->setCellValue('B' . $fila_actual, $cantidad);
        $fila_actual++;
    }

    // aplicamos estilos a la tabla de generos
    if ($fila_actual > $fila_inicio_generos) {
        $fila_fin_generos = $fila_actual - 1;
        aplicar_estilo_datos($hoja, 'A' . $fila_inicio_generos . ':B' . $fila_fin_generos, $colores['borde']);
        aplicar_filas_alternas($hoja, $fila_inicio_generos, $fila_fin_generos, 'A', 'B', $colores['fila_alterna']);
        $hoja->getStyle('B' . $fila_inicio_generos . ':B' . $fila_fin_generos)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    $fila_actual += 1;

    // --- seccion de top 10 podcasts ---
    escribir_titulo_seccion($hoja, 'A' . $fila_actual, 'Top 10 Podcasts', 'A' . $fila_actual . ':C' . $fila_actual, $colores['titulo_fondo'], $colores['titulo_fuente']);
    $fila_actual++;

    // escribimos encabezados de podcasts
    $hoja->setCellValue('A' . $fila_actual, '#');
    $hoja->setCellValue('B' . $fila_actual, 'Podcast');
    $hoja->setCellValue('C' . $fila_actual, 'Puntuación');
    aplicar_estilo_encabezado($hoja, 'A' . $fila_actual . ':C' . $fila_actual, $colores['encabezado_fondo'], $colores['encabezado_fuente']);
    $fila_actual++;

    // recorremos los podcasts para poblar la tabla
    $fila_inicio_podcasts = $fila_actual;
    $podcasts = $datos['top_podcasts'] ?? [];
    foreach ($podcasts as $indice => $podcast) {
        $hoja->setCellValue('A' . $fila_actual, $indice + 1);
        $hoja->setCellValue('B' . $fila_actual, $podcast['titulo']);
        $hoja->setCellValue('C' . $fila_actual, $podcast['puntuacion']);
        $fila_actual++;
    }

    // aplicamos estilos a la tabla de podcasts
    if ($fila_actual > $fila_inicio_podcasts) {
        $fila_fin_podcasts = $fila_actual - 1;
        aplicar_estilo_datos($hoja, 'A' . $fila_inicio_podcasts . ':C' . $fila_fin_podcasts, $colores['borde']);
        aplicar_filas_alternas($hoja, $fila_inicio_podcasts, $fila_fin_podcasts, 'A', 'C', $colores['fila_alterna']);
        $hoja->getStyle('A' . $fila_inicio_podcasts . ':A' . $fila_fin_podcasts)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('C' . $fila_inicio_podcasts . ':C' . $fila_fin_podcasts)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // ajustamos las columnas automaticamente
    $hoja->getColumnDimension('A')->setAutoSize(true);
    $hoja->getColumnDimension('B')->setAutoSize(true);
    $hoja->getColumnDimension('C')->setAutoSize(true);
}

function generar_documento_excel($datos, $fecha, $colores_config) {
    // inicializamos el libro de excel
    $libro = new Spreadsheet();

    // configuramos las propiedades del documento
    $libro->getProperties()
        ->setCreator('Catálogo API-DEEZER')
        ->setTitle('Reporte Dashboard Musical')
        ->setSubject('Estadísticas del Dashboard')
        ->setDescription('Reporte generado el ' . $fecha);

    // --- hoja 1: top canciones ---
    $hoja_canciones = $libro->getActiveSheet();
    $hoja_canciones->setTitle('Top Canciones');
    hoja_canciones($hoja_canciones, $datos['top_canciones'] ?? [], $colores_config);

    // --- hoja 2: top artistas ---
    $hoja_artistas = $libro->createSheet();
    $hoja_artistas->setTitle('Top Artistas');
    hoja_artistas($hoja_artistas, $datos['top_artistas'] ?? [], $colores_config);

    // --- hoja 3: estadisticas generales ---
    $hoja_estadisticas = $libro->createSheet();
    $hoja_estadisticas->setTitle('Estadísticas');
    hoja_estadisticas($hoja_estadisticas, $datos, $colores_config);

    // seleccionamos la primera hoja como activa al abrir
    $libro->setActiveSheetIndex(0);

    // definimos los encabezados http para la descarga del archivo
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="reporte_dashboard_musical.xlsx"');
    header('Cache-Control: max-age=0');

    // escribimos el libro directamente a la salida php
    $escritor = new Xlsx($libro);
    $escritor->save('php://output');

    // retornamos despues de limpiar la memoria del libro
    $libro->disconnectWorksheets();
    unset($libro);
}

// preparamos la estructura de colores para pasar a las funciones
$configuracion_colores = [
    'encabezado_fondo' => $COLOR_ENCABEZADO_FONDO,
    'encabezado_fuente' => $COLOR_ENCABEZADO_FUENTE,
    'titulo_fondo' => $COLOR_TITULO_FONDO,
    'titulo_fuente' => $COLOR_TITULO_FUENTE,
    'fila_alterna' => $COLOR_FILA_ALTERNA,
    'borde' => $COLOR_BORDE
];

generar_documento_excel($data, $fecha_generacion, $configuracion_colores);
