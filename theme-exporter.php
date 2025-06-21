<?php
/**
 * Plugin Name: Theme Exporter
 * Description: Allows you to choose an installed theme and download it as a ZIP file.
 * Version: 1.3.1
 * Author: Gabi
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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

    if (isset($_POST['theme_to_export'], $_POST['theme_export_nonce']) &&
        wp_verify_nonce($_POST['theme_export_nonce'], 'theme_export')) {

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
    echo '<h1>Export Theme</h1>';
    echo '<form method="post">';
    wp_nonce_field('theme_export', 'theme_export_nonce');
    echo '<label for="theme_to_export">Choose a theme:</label>'; 
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
    $zip_name = $theme_slug . '-' . time() . '.zip';
    $zip_file = $upload_dir['basedir'] . '/' . $zip_name;

    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($theme_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($theme_dir) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }

        $zip->close();

        return $upload_dir['baseurl'] . '/' . $zip_name;
    } else {
        return new WP_Error('zip_creation_failed', 'Error creating the ZIP file.');
    }
}