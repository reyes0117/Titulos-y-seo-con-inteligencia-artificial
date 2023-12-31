<?php
/*
Plugin Name: titulosyseoconinteligenciaartificial
Description: Un plugin que genera títulos llamativos y palabras clave para SEO y permite reemplazar títulos en notas en borrador.
Version: 1.0
Author: Tu Nombre
*/

require_once(plugin_dir_path(__FILE__) . 'config.php');


// Función para cargar el archivo CSS del plugin
function load_plugin_styles() {
    // Ruta del archivo CSS relativa al directorio del plugin
    $css_file = 'css/plugin-styles.css';

    // Obtén la URL del directorio del plugin
    $plugin_url = plugin_dir_url(__FILE__);

    // Enlaza el archivo CSS al encabezado de la página
    wp_enqueue_style('plugin-styles', $plugin_url . $css_file);
}

// Hook para cargar el CSS en el panel de administración
add_action('admin_enqueue_scripts', 'load_plugin_styles');

// Función para verificar si la última nota está en borrador y generar títulos llamativos y palabras clave para SEO
function check_last_post_draft_status() {
    $args = array(
        'post_type' => 'post',
        'post_status' => 'draft',
        'posts_per_page' => 1,
    );

    $last_draft_post = new WP_Query($args);

    if ($last_draft_post->have_posts()) {
        echo 'La última nota está en estado de borrador.';

        // Llamada a la API de OpenAI para generar títulos
        $api_key = CHATGPT_API_KEY; // Obtener la clave de API del archivo de configuración

        $post_title = $last_draft_post->posts[0]->post_title;

        // Llamada a la API de OpenAI para generar títulos llamativos
        $response = wp_remote_post('https://api.openai.com/v1/engines/davinci/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'prompt' => "Genera 3 títulos llamativos para una nota sobre '{$post_title}' que atraigan mucho tráfico.",
                'max_tokens' => 60,
                'n' => 3,
            )),
        );

        if (!is_wp_error($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($result['choices'])) {
                echo '<br><strong>Títulos llamativos generados:</strong><br>';
                echo '<div class="titles-generated">'; // Agrega una clase CSS
                foreach ($result['choices'] as $index => $choice) {
                    $title = $choice['text'];
                    echo '<input type="radio" name="selected_title" value="' . esc_attr($title) . '"> ' . esc_html($title) . '<br>';
                }
                echo '</div>'; // Cierra el div
            }
        } else {
            echo 'Error al llamar a la API de OpenAI para generar títulos llamativos.';
        }

        // Llamada a la API de OpenAI para generar palabras clave
        $response_keywords = wp_remote_post('https://api.openai.com/v1/engines/davinci/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'prompt' => "Genera palabras clave para una nota sobre '{$post_title}' que sean relevantes para el SEO.",
                'max_tokens' => 30,
                'n' => 1,
            )),
        });

        if (!is_wp_error($response_keywords)) {
            $result_keywords = json_decode(wp_remote_retrieve_body($response_keywords), true);
            if (isset($result_keywords['choices'])) {
                $keywords = $result_keywords['choices'][0]['text'];
                echo '<br><strong>Palabras clave generadas:</strong><br>';
                echo '<div class="keywords-generated">'; // Agrega una clase CSS
                echo esc_html($keywords);
                echo '</div>'; // Cierra el div
            }
        } else {
            echo 'Error al llamar a la API de OpenAI para generar palabras clave.';
        }

        if (isset($_POST['replace_title'])) {
            $selected_title = sanitize_text_field($_POST['selected_title']);
            $post_id = $last_draft_post->posts[0]->ID;
            wp_update_post(array('ID' => $post_id, 'post_title' => $selected_title));
            echo '<br>Título actualizado.';
        }
    } else {
        echo 'La última nota no está en estado de borrador.';
    }
}

// Hook para ejecutar la función en el panel de administración
add_action('admin_notices', 'check_last_post_draft_status');
