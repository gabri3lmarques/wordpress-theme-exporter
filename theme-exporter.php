<?php
/**
 * Plugin Name: Theme Exporter
 * Description: Allows you to choose an installed theme and download it as a ZIP file.
 * Version: 1.2
 * Author: Gabi
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_menu_page(
        'Export Theme',
        'Export Theme',
        'manage_options',
        'theme-exporter',
        'theme_exporter_page',
        'dashicons-download',
        100
    );
});

function theme_exporter_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['theme_to_export'])) {
        $theme_slug = sanitize_text_field($_POST['theme_to_export']);
        $result = theme_exporter_generate_zip($theme_slug);

        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>The theme was exported successfully. <a href="' . esc_url($result) . '" target="_blank">Click here to download</a>.</p></div>';
        }
    }

    $themes = wp_get_themes();

    echo '<div class="wrap">';
    echo '<h1>Exportar Tema</h1>';
    echo '<form method="post">';
    echo '<label for="theme_to_export">Escolha um tema:</label>'; 
    echo '<select id="theme_to_export" name="theme_to_export">';

    foreach ($themes as $slug => $theme) {
        echo '<option value="' . esc_attr($slug) . '">' . esc_html($theme->get('Name')) . '</option>';
    }

    echo '</select>';
    echo '<button type="submit" class="button button-primary">Export</button>';
    echo '</form>';
    echo '</div>';
}

function theme_exporter_generate_zip($theme_slug) {
    $theme_dir = get_theme_root() . '/' . $theme_slug;

    if (!is_dir($theme_dir)) {
        return new WP_Error('theme_not_found', 'Theme not found.');
    }

    $upload_dir = wp_upload_dir();
    $zip_file = $upload_dir['basedir'] . '/' . $theme_slug . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme_dir), RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($theme_dir) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }

        $zip->close();

        return $upload_dir['baseurl'] . '/' . $theme_slug . '.zip';
    } else {
        return new WP_Error('zip_creation_failed', 'Error creating the ZIP file.');
    }
}