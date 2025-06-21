<?php
/**
 * Plugin Name: Theme Exporter
 * Description: Allows you to choose an installed theme and download it as a ZIP file.
 * Version: 1.2.1
 * Author: Gabi
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: theme-exporter
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', function () {
    load_plugin_textdomain('theme-exporter', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

add_action('admin_menu', function () {
    add_menu_page(
        __('Export Theme', 'theme-exporter'),
        __('Export Theme', 'theme-exporter'),
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

    if (isset($_POST['theme_to_export'], $_POST['te_export_nonce']) && wp_verify_nonce($_POST['te_export_nonce'], 'te_export_action')) {
        $theme_slug = sanitize_key($_POST['theme_to_export']);
        $result = theme_exporter_generate_zip($theme_slug);

        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        } else {
            theme_exporter_force_download($result);
        }
    }

    $themes = wp_get_themes();

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Export Theme', 'theme-exporter') . '</h1>';
    echo '<form method="post">';
    wp_nonce_field('te_export_action', 'te_export_nonce');

    echo '<label for="theme_to_export">' . esc_html__('Choose a theme:', 'theme-exporter') . '</label>';
    echo '<select id="theme_to_export" name="theme_to_export">';
    foreach ($themes as $slug => $theme) {
        echo '<option value="' . esc_attr($slug) . '">' . esc_html($theme->get('Name')) . '</option>';
    }
    echo '</select>';
    echo '<button type="submit" class="button button-primary">' . esc_html__('Export', 'theme-exporter') . '</button>';
    echo '</form>';
    echo '</div>';
}

function theme_exporter_generate_zip($theme_slug) {
    if (!class_exists('ZipArchive')) {
        return new WP_Error('zip_not_available', __('ZIP support is not enabled on this server.', 'theme-exporter'));
    }

    $theme_dir = get_theme_root() . '/' . $theme_slug;
    if (!is_dir($theme_dir)) {
        return new WP_Error('theme_not_found', __('Theme not found.', 'theme-exporter'));
    }

    $upload_dir = wp_upload_dir();
    $temp_dir = trailingslashit($upload_dir['basedir']) . 'theme-exporter-temp/';
    wp_mkdir_p($temp_dir);

    $zip_file_path = $temp_dir . $theme_slug . '-' . time() . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($theme_dir, FilesystemIterator::SKIP_DOTS),
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
        return $zip_file_path;
    }

    return new WP_Error('zip_creation_failed', __('Error creating the ZIP file.', 'theme-exporter'));
}

function theme_exporter_force_download($zip_file_path) {
    if (!file_exists($zip_file_path)) {
        wp_die(__('ZIP file not found.', 'theme-exporter'));
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zip_file_path) . '"');
    header('Content-Length: ' . filesize($zip_file_path));
    flush();
    readfile($zip_file_path);
    unlink($zip_file_path);
    exit;
}
