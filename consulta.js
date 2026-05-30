const CLAVE_API = "";
const MODELO_IA = "meta-llama/llama-3-8b-instruct";
const ENDPOINT_IA = "https://openrouter.ai/api/v1/chat/completions";

$(document).ready(function () {
    $("#boton_analizar").on("click", analizar_datos_dashboard);
});

async function analizar_datos_dashboard() {
    // identificamos los datos actuales del dashboard
    const datos = datos_dashboard();

    if (!datos) {
        // si el dato no existe, se detiene la ejecución
        return;
    }

    // mostramos el estado de carga en la interfaz de usuario
    $("#resultado_analisis_ia").text("Cargando y analizando datos con la Inteligencia Artificial...");

    // definimos la consulta para enviar a la inteligencia artificial
    const texto_consulta = `Eres un analista experto en la industria de la música. 
    Analiza los siguientes datos estadísticos obtenidos del dashboard musical de Deezer de la aplicación y proporciona un informe detallado con: 
    1. Tendencias generales de popularidad y géneros destacados.
    2. Artistas líderes y distribución de oyentes.
    3. Proporción de contenido explícito vs apto.
    4. Observación sobre la presencia de podcasts.
    5. Recomendaciones estratégicas prácticas.

    Datos del dashboard:
    - Canciones más valoradas: ${JSON.stringify(datos.top_canciones)}
    - Fans por artista: ${JSON.stringify(datos.top_artistas)}
    - Distribución de contenido explícito: Explícito ${datos.contenido_explicito.explicito} y Apto ${datos.contenido_explicito.apto}
    - Géneros populares: ${JSON.stringify(datos.generos)}
    - Podcasts destacados: ${JSON.stringify(datos.top_podcasts)}

    Responde de forma profesional, clara, objetiva, estructurada en viñetas y completamente en español.`;

    try {
        // realizamos la petición 
        const respuesta = await fetch(ENDPOINT_IA, {
            method: "POST",
            headers: {
                "Authorization": "Bearer " + CLAVE_API,
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                model: MODELO_IA,
                messages: [{
                    role: "user",
                    content: texto_consulta
                }]
            })
        });

        if (!respuesta.ok) {
            // si no hay respuesta, se detiene la ejecución
            $("#resultado_analisis_ia").text("Error en la petición: " + respuesta.statusText);
            return;
        }

        const resultado_servidor = await respuesta.json();

        if (!resultado_servidor) {
            return;
        }

        // validamos las opciones obtenidas de la respuesta de la inteligencia artificial
        if (resultado_servidor.choices && resultado_servidor.choices.length > 0) {
            // mostramos el análisis de la inteligencia artificial en la interfaz
            $("#resultado_analisis_ia").text(resultado_servidor.choices[0].message.content);
        } else {
            // mostramos el error recibido de la api de inteligencia artificial
            $("#resultado_analisis_ia").text("Error en la respuesta: " + (resultado_servidor.error?.message || "No se obtuvo resultado."));
        }

    } catch (error) {
        // mostramos un mensaje explicativo ante un fallo de red o ejecución
        $("#resultado_analisis_ia").text("Error al conectar con la inteligencia artificial: " + error.message);
    }
}
