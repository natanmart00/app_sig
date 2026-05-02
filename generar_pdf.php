<?php

require_once __DIR__ . '/vendor/autoload.php';

use Mpdf\Mpdf;

$fecha_generacion = date('d/m/Y H:i:s');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    // si el dato no existe, se detiene la ejecución
    http_response_code(400);
    echo json_encode(['error' => 'No se recibieron datos válidos.']);
    exit;
}

// validamos y ordenamos las canciones por rango de mayor a menor
if (isset($data['top_canciones'])) {
    usort($data['top_canciones'], function($a, $b) {
        return ($b['rank'] ?? 0) <=> ($a['rank'] ?? 0);
    });
}

// validamos y ordenamos los artistas por cantidad de fans de mayor a menor
if (isset($data['top_artistas'])) {
    usort($data['top_artistas'], function($a, $b) {
        return ($b['fans'] ?? 0) <=> ($a['fans'] ?? 0);
    });
}

// validamos y ordenamos los generos por cantidad de canciones de mayor a menor
if (isset($data['generos'])) {
    arsort($data['generos']);
}

// validamos y ordenamos los podcasts por puntuacion de mayor a menor
if (isset($data['top_podcasts'])) {
    usort($data['top_podcasts'], function($a, $b) {
        return ($b['puntuacion'] ?? 0) <=> ($a['puntuacion'] ?? 0);
    });
}

function construir_tabla_canciones($lista_canciones) {

    if (!$lista_canciones || count($lista_canciones) === 0) {
        return '<p style="color: #999;">No hay datos disponibles.</p>';
    }

    $html_tabla = '
    <table class="tabla-datos">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 25%;">Canción</th>
                <th style="width: 20%;">Artista</th>
                <th style="width: 18%;">Álbum</th>
                <th style="width: 10%;">Duración</th>
                <th style="width: 10%;">Explícita</th>
                <th style="width: 12%;">Rank</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($lista_canciones as $indice => $cancion) {
        $numero_fila = $indice + 1;
        // calculamos la duracion en formato minutos:segundos
        $minutos = floor($cancion['duracion_seg'] / 60);
        $segundos = str_pad($cancion['duracion_seg'] % 60, 2, '0', STR_PAD_LEFT);
        $duracion_formateada = $minutos . ':' . $segundos;
        
        // formateamos el rank con separador de miles
        $rank_formateado = number_format($cancion['rank'], 0, '.', ',');

        $html_tabla .= '
            <tr>
                <td style="text-align: center;">' . $numero_fila . '</td>
                <td>' . htmlspecialchars($cancion['titulo']) . '</td>
                <td>' . htmlspecialchars($cancion['artista']) . '</td>
                <td>' . htmlspecialchars($cancion['album']) . '</td>
                <td style="text-align: center;">' . $duracion_formateada . '</td>
                <td style="text-align: center;">' . htmlspecialchars($cancion['explicita']) . '</td>
                <td style="text-align: right;">' . $rank_formateado . '</td>
            </tr>';
    }

    $html_tabla .= '
        </tbody>
    </table>';

    return $html_tabla;
}

function construir_tabla_artistas($lista_artistas) {
    if (!$lista_artistas || count($lista_artistas) === 0) {
        return '<p style="color: #999;">No hay datos disponibles.</p>';
    }

    $html_tabla = '
    <table class="tabla-datos">
        <thead>
            <tr>
                <th style="width: 10%;">#</th>
                <th style="width: 55%;">Artista</th>
                <th style="width: 35%;">Fans Estimados</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($lista_artistas as $indice => $artista) {
        $numero_fila = $indice + 1;
        $fans_formateados = number_format($artista['fans'], 0, '.', ',');

        $html_tabla .= '
            <tr>
                <td style="text-align: center;">' . $numero_fila . '</td>
                <td>' . htmlspecialchars($artista['nombre']) . '</td>
                <td style="text-align: right;">' . $fans_formateados . '</td>
            </tr>';
    }

    $html_tabla .= '
        </tbody>
    </table>';

    return $html_tabla;
}

function construir_tabla_generos($datos_generos) {

    if (!$datos_generos || count($datos_generos) === 0) {
        return '<p style="color: #999;">No hay datos disponibles.</p>';
    }

    $html_tabla = '
    <table class="tabla-datos">
        <thead>
            <tr>
                <th style="width: 60%;">Género</th>
                <th style="width: 40%;">Canciones en Top 100</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($datos_generos as $nombre_genero => $cantidad) {
        $html_tabla .= '
            <tr>
                <td>' . htmlspecialchars($nombre_genero) . '</td>
                <td style="text-align: center;">' . $cantidad . '</td>
            </tr>';
    }

    $html_tabla .= '
        </tbody>
    </table>';

    return $html_tabla;
}

function construir_tabla_podcasts($lista_podcasts) {

    if (!$lista_podcasts || count($lista_podcasts) === 0) {
        return '<p style="color: #999;">No hay datos disponibles.</p>';
    }

    $html_tabla = '
    <table class="tabla-datos">
        <thead>
            <tr>
                <th style="width: 10%;">#</th>
                <th style="width: 65%;">Podcast</th>
                <th style="width: 25%;">Puntuación</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($lista_podcasts as $indice => $podcast) {
        $numero_fila = $indice + 1;

        $html_tabla .= '
            <tr>
                <td style="text-align: center;">' . $numero_fila . '</td>
                <td>' . htmlspecialchars($podcast['titulo']) . '</td>
                <td style="text-align: center;">' . $podcast['puntuacion'] . '</td>
            </tr>';
    }

    $html_tabla .= '
        </tbody>
    </table>';

    return $html_tabla;
}

function generar_documento_pdf($datos, $fecha) {
    // inicializamos mpdf con configuracion de pagina carta
    $mpdf = new Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'Letter',
        'margin_left'   => 15,
        'margin_right'  => 15,
        'margin_top'    => 15,
        'margin_bottom' => 15,
        'default_font'  => 'dejavusans'
    ]);

    $estilos_css = '
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #333;
            font-size: 11px;
        }
        .encabezado-reporte {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #fff;
            padding: 20px 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
        }
        .encabezado-reporte h1 {
            margin: 0 0 5px 0;
            font-size: 22px;
            letter-spacing: 1px;
        }
        .encabezado-reporte p {
            margin: 0;
            font-size: 11px;
            color: #adb5bd;
        }
        .seccion-titulo {
            background-color: #2c3e50;
            color: #fff;
            padding: 8px 15px;
            border-radius: 5px;
            margin: 20px 0 10px 0;
            font-size: 14px;
            font-weight: bold;
        }
        .tabla-datos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .tabla-datos thead th {
            background-color: #34495e;
            color: #ecf0f1;
            padding: 8px 6px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            border-bottom: 2px solid #1abc9c;
        }
        .tabla-datos tbody td {
            padding: 6px;
            border-bottom: 1px solid #dee2e6;
            font-size: 10px;
        }
        .tabla-datos tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .resumen-card {
            background-color: #f0f4f8;
            border-left: 4px solid #3498db;
            padding: 12px 15px;
            margin: 10px 0;
            border-radius: 0 5px 5px 0;
        }
        .resumen-card strong {
            color: #2c3e50;
        }
        .pie-pagina {
            text-align: center;
            font-size: 9px;
            color: #999;
            margin-top: 30px;
            border-top: 1px solid #dee2e6;
            padding-top: 10px;
        }
    </style>';

    $html_contenido = $estilos_css . '
    <div class="encabezado-reporte">
        <h1>Reporte Dashboard Musical</h1>
        <p>Catálogo MusicPro &mdash; Generado el ' . htmlspecialchars($fecha) . '</p>
    </div>';

    // construimos la seccion de top 10 canciones
    $html_contenido .= '
    <div class="seccion-titulo">Top 10 Canciones por Popularidad</div>';
    $html_contenido .= construir_tabla_canciones($datos['top_canciones'] ?? []);

    // construimos la seccion de contenido explicito
    $explicito = $datos['contenido_explicito'] ?? ['explicito' => 0, 'apto' => 0];
    $total_contenido = $explicito['explicito'] + $explicito['apto'];
    $porcentaje_explicito = $total_contenido > 0 ? round(($explicito['explicito'] / $total_contenido) * 100, 1) : 0;
    $porcentaje_apto = $total_contenido > 0 ? round(($explicito['apto'] / $total_contenido) * 100, 1) : 0;

    $html_contenido .= '
    <div class="seccion-titulo">Contenido Explícito vs Apto</div>
    <div class="resumen-card">
        <strong>Contenido Explícito:</strong> ' . $explicito['explicito'] . ' canciones (' . $porcentaje_explicito . '%)
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Apto Todo Público:</strong> ' . $explicito['apto'] . ' canciones (' . $porcentaje_apto . '%)
    </div>';

    // construimos la seccion de generos musicales
    $html_contenido .= '
    <div class="seccion-titulo">Distribución de Géneros en Top 10</div>';
    $html_contenido .= construir_tabla_generos($datos['generos'] ?? []);

    // construimos la seccion de top 15 artistas
    $html_contenido .= '
    <div class="seccion-titulo">Top 15 Artistas por Fans</div>';
    $html_contenido .= construir_tabla_artistas($datos['top_artistas'] ?? []);

    // construimos la seccion de top 10 podcasts
    $html_contenido .= '
    <div class="seccion-titulo">Top 10 Podcasts</div>';
    $html_contenido .= construir_tabla_podcasts($datos['top_podcasts'] ?? []);

    // agregamos pie de pagina
    $html_contenido .= '
    <div class="pie-pagina">
        Reporte generado automáticamente desde el Dashboard &mdash; API Deezer
    </div>';

    // escribimos el html en el documento pdf
    $mpdf->WriteHTML($html_contenido);

    // retornamos el pdf como descarga directa al navegador
    $mpdf->Output('reporte_dashboard_musical.pdf', \Mpdf\Output\Destination::DOWNLOAD);
}

// ejecutamos la generacion del pdf
generar_documento_pdf($data, $fecha_generacion);
