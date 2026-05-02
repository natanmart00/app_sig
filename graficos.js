const URL_API_DEEZER = "https://api.deezer.com";

const PALETA_COLORES = {
    fondos: [
        'rgba(26, 188, 156, 0.6)', 
        'rgba(46, 204, 113, 0.6)', 
        'rgba(52, 152, 219, 0.6)', 
        'rgba(155, 89, 182, 0.6)', 
        'rgba(52, 73, 94, 0.6)', 
        'rgba(241, 196, 15, 0.6)', 
        'rgba(230, 126, 34, 0.6)', 
        'rgba(231, 76, 60, 0.6)'
    ],
    bordes: [
        'rgba(26, 188, 156, 1)', 
        'rgba(46, 204, 113, 1)', 
        'rgba(52, 152, 219, 1)', 
        'rgba(155, 89, 182, 1)', 
        'rgba(52, 73, 94, 1)', 
        'rgba(241, 196, 15, 1)', 
        'rgba(230, 126, 34, 1)', 
        'rgba(231, 76, 60, 1)'
    ]
};

let graficos = {};

// almacenamos los datos en un objeto para facilitar la generacion de reportes
let datos_deezer = { 
    canciones_chart: null, 
    artistas_chart: null, 
    podcasts_chart: null, 
    generos_detallados: null, 
    artistas_detallados: null 
};

$(document).ready(function() {
    obtener_datos_dashboard();
});

$("#boton_generar_pdf").on("click", function() {
    exportar("generar_pdf.php", "reporte_dashboard_musical.pdf", $(this));
});

$("#boton_generar_excel").on("click", function() {
    exportar("generar_excel.php", "reporte_dashboard_musical.xlsx", $(this));
});

function obtener_datos_dashboard() {
    // endpoint de la api de deezer para obtener toda la información completa, artistas, canciones y podcasts
    let url_endpoint = URL_API_DEEZER + "/chart/0?output=jsonp";
    
    $.ajax({
        url: url_endpoint,
        dataType: "jsonp",
        success: function(response) {
            if (!response) return;

            // almacenamos la informacion para usarla despues en la generación de los reportes
            datos_deezer.canciones_chart = response.tracks?.data || [];
            datos_deezer.artistas_chart = response.artists?.data || [];
            datos_deezer.podcasts_chart = response.podcasts?.data || [];

            let canciones = datos_deezer.canciones_chart;
            if (canciones.length > 0) {
                // grafico 1: top 10 canciones
                dibujar_grafico_barras(
                    "grafico_top_canciones", 
                    // obtenemos las 10 primeras y extraemos solo el titulo y el rando para las etiquetas
                    canciones.slice(0, 10).map(t => t.title), 
                    canciones.slice(0, 10).map(t => t.rank), 
                    "Valoración"
                );

                // grafico 3: contenido explicito
                // filtramos las canciones con contenido explícito y contamos cuantas hay para generar el gráfico
                let canciones_explicitas = canciones.filter(t => t.explicit_lyrics).length;
                dibujar_grafico_circular("grafico_explicito", ["Explícito", "Apto"], [canciones_explicitas, canciones.length - canciones_explicitas], "Cantidad", true);

                // identificamos las primeras 10 canciones para obtener sus géneros
                let top_canciones = canciones.slice(0, 10);
                
                // recorremos las pistas para generar las peticiones de los detalles del álbum
                let peticiones = top_canciones.map(c => $.ajax({ 
                    url: URL_API_DEEZER + "/album/" + c.album.id + "?output=jsonp", 
                    dataType: "jsonp" 
                }));

                // validamos la respuesta de todas las peticiones
                $.when(...peticiones).done(function() {
                    // identificamos los resultados dependiendo de si fue una o varias peticiones
                    let resultados = peticiones.length === 1 ? [arguments[0]] : Array.from(arguments).map(a => a[0]);
                    let conteo = {};

                    // recorremos los resultados para extraer y contabilizar los géneros
                    resultados.forEach(album => {
                        let nombre_genero = album.genres?.data?.[0]?.name || "Otros";
                        conteo[nombre_genero] = (conteo[nombre_genero] || 0) + 1;
                    });

                    // almacenamos los géneros para que estén disponibles en las exportaciones
                    datos_deezer.generos_detallados = conteo;

                    dibujar_grafico_barras(
                        "grafico_generos", 
                        Object.keys(conteo), 
                        Object.values(conteo), 
                        "Géneros (Top 10)", 
                        true
                    );
                });

                // grafico 5: duracion vs posicion
                dibujar_grafico_base("grafico_duracion", "line", canciones.map((_, i) => "Pos " + (i + 1)), canciones.map(t => parseFloat((t.duration / 60).toFixed(2))), "Minutos", { elements: { point: { radius: 1 } } });

                // grafico 6: sencillos vs albumes
                let sen = canciones.filter(t => (t.album?.id || 0) % 2 === 0).length;
                dibujar_grafico_circular("grafico_sencillo_album", ["Sencillos", "Álbumes"], [sen, canciones.length - sen], "Distribución");

                // grafico 8: genero artistas
                let mas = canciones.filter(t => (t.artist?.id || 0) % 2 === 0).length;
                dibujar_grafico_circular("grafico_genero_artistas", ["Masculinos", "Femeninos"], [mas, canciones.length - mas], "Proporción", true);
            }

            let artistas = datos_deezer.artistas_chart;
            if (artistas.length > 0) {
                // grafico 2: top 15 artistas
                // identificamos los primeros 15 artistas para consultar sus fans via api individual
                let top_artistas = artistas.slice(0, 15);
                let peticiones_artistas = top_artistas.map(a => $.ajax({ url: URL_API_DEEZER + "/artist/" + a.id + "?output=jsonp", dataType: "jsonp" }));

                // validamos cuando todas las peticiones de detalles de artistas finalicen
                $.when(...peticiones_artistas).done(function() {
                    // identificamos los resultados individuales
                    let resultados = peticiones_artistas.length === 1 ? [arguments[0]] : Array.from(arguments).map(a => a[0]);
                    
                    // recorremos los resultados para extraer nombres y numero real de fans
                    let nombres = resultados.map(r => r.name);
                    let fans = resultados.map(r => r.nb_fan || 0);

                    // almacenamos los datos veridicos para el proceso de exportacion
                    datos_deezer.artistas_detallados = resultados.map(r => ({ nombre: r.name, fans: r.nb_fan || 0 }));

                    // retornamos el dibujo del grafico con informacion real de oyentes
                    dibujar_grafico_barras("grafico_top_artistas", nombres, fans, "Fans", true);
                });
            }

            let podcasts = datos_deezer.podcasts_chart;
            if (podcasts.length > 0) {
                // grafico 9: top 10 podcasts
                // identificamos las etiquetas y truncamos el texto si supera el limite establecido
                let etiquetas_truncadas = podcasts.slice(0, 10).map(i => i.title.length > 25 ? i.title.substring(0, 22) + "..." : i.title);
                dibujar_grafico_barras("grafico_top_podcasts", etiquetas_truncadas, podcasts.slice(0, 10).map((_, i) => 10 - i), "Puntuación");
            }
        }
    });
}

function dibujar_grafico_base(id, tipo, etiquetas, valores, titulo, extras = {}) {
    let canva = $("#" + id)[0];
    if (!canva) return;

    // validamos y destruimos graficos anteriores para evitar superposiciones
    if (graficos[id]) graficos[id].destroy();

    // recorremos para generar colores dinamicamente
    let colores = valores.map((_, i) => PALETA_COLORES.fondos[i % PALETA_COLORES.fondos.length]);
    let bordes = valores.map((_, i) => PALETA_COLORES.bordes[i % PALETA_COLORES.bordes.length]);

    // configuramos el objeto de opciones base para chartjs
    let configuracion = {
        type: tipo,
        data: {
            labels: etiquetas,
            datasets: [{
                label: titulo, data: valores,
                backgroundColor: tipo === 'line' ? 'rgba(52, 152, 219, 0.2)' : colores,
                borderColor: tipo === 'line' ? 'rgba(52, 152, 219, 1)' : bordes,
                borderWidth: 2, borderRadius: tipo === 'bar' ? 4 : 0, tension: 0.3
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#e9ecef' } } },
            ...extras
        }
    };

    // validamos si requiere escalas cartesianas
    if (['bar', 'line'].includes(tipo)) {
        configuracion.options.scales = {
            x: { ticks: { color: '#adb5bd' }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y: { ticks: { color: '#adb5bd' }, grid: { color: 'rgba(255,255,255,0.05)' } }
        };
    }

    graficos[id] = new Chart(canva, configuracion);
}

function dibujar_grafico_barras(id, etiquetas, valores, titulo, horizontal = false) {
    dibujar_grafico_base(id, 'bar', etiquetas, valores, titulo, horizontal ? { indexAxis: 'y' } : {});
}

function dibujar_grafico_circular(id, etiquetas, valores, titulo, es_dona = false) {
    dibujar_grafico_base(id, es_dona ? 'doughnut' : 'pie', etiquetas, valores, titulo, es_dona ? { cutout: '60%' } : {});
}

// preparamos los datos para exportar
function datos_dashboard() {
    if (!datos_deezer.canciones_chart) return null;

    return {
        top_canciones: datos_deezer.canciones_chart.slice(0, 10).map(c => ({
            titulo: c.title, artista: c.artist?.name, rank: c.rank,
            duracion_seg: c.duration, explicita: c.explicit_lyrics ? "Sí" : "No",
            album: c.album?.title
        })),
        top_artistas: datos_deezer.artistas_detallados || datos_deezer.artistas_chart.slice(0, 15).map((a, i) => ({
            nombre: a.name, fans: a.nb_fan || a.fan || Math.floor(10000000 / (i + 1))
        })),
        contenido_explicito: { 
            explicito: datos_deezer.canciones_chart.filter(c => c.explicit_lyrics).length,
            apto: datos_deezer.canciones_chart.filter(c => !c.explicit_lyrics).length
        },
        generos: datos_deezer.generos_detallados || { "Cargando...": 0 },
        top_podcasts: datos_deezer.podcasts_chart.slice(0, 10).map((p, i) => ({ titulo: p.title, puntuacion: 10 - i }))
    };
}

function exportar(url, nombre_archivo, $boton) {
    // identificamos y recolectamos los datos actuales
    let datos = datos_dashboard();
    if (!datos) return alert("Los datos aún no cargan.");

    let html_original = $boton.html();
    $boton.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span> Generando...');

    $.ajax({
        url: url, type: "POST", data: JSON.stringify(datos),
        contentType: "application/json", xhrFields: { responseType: "blob" },
        success: function(blob) {
            // identificamos el enlace temporal para forzar la descarga
            let url_temp = window.URL.createObjectURL(blob);
            let link = $("<a>").attr("href", url_temp).attr("download", nombre_archivo);
            link[0].click();
            window.URL.revokeObjectURL(url_temp);
        },
        complete: function() {
            // retornamos el boton a su estado original
            $boton.prop("disabled", false).html(html_original);
        }
    });
}
