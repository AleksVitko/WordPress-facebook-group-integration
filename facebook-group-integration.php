<?php
/**
 * Plugin Name: Facebook Group Integration
 * Description: Интеграция с группой Facebook для отправки и получения объявлений.
 * Version: 1.0
 * Author: Alexandr Vitko
 */

// Запрет прямого вызова файла
if (!defined('ABSPATH')) {
    exit;
}

// Подключение необходимых классов и функций
require_once plugin_dir_path(__FILE__) . 'includes/functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/facebook-api.php';

// Инициализация плагина
function fgi_init() {
    // Регистрация настроек плагина
    register_setting('fgi_settings', 'fgi_api_token');
    register_setting('fgi_settings', 'fgi_group_id');
    register_setting('fgi_settings', 'fgi_enable_export');
    register_setting('fgi_settings', 'fgi_enable_import');

    // Добавление страницы настроек
    add_action('admin_menu', 'fgi_add_admin_page');
}
add_action('init', 'fgi_init');

// Добавление страницы настроек в админке
function fgi_add_admin_page() {
    add_options_page(
        'Настройки Facebook Group Integration',
        'Facebook Group Integration',
        'manage_options',
        'facebook-group-integration',
        'fgi_render_admin_page'
    );
}

// Вывод страницы настроек
function fgi_render_admin_page() {
    ?>
    <div class="wrap">
        <h1>Настройки Facebook Group Integration</h1>
        <form method="post" action="options.php">
            <?php settings_fields('fgi_settings'); ?>
            <?php do_settings_sections('facebook-group-integration'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">Access Token:</th>
                    <td><input type="text" name="fgi_api_token" value="<?php echo esc_attr(get_option('fgi_api_token')); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">Group ID:</th>
                    <td><input type="text" name="fgi_group_id" value="<?php echo esc_attr(get_option('fgi_group_id')); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">Enable Export:</th>
                    <td><input type="checkbox" name="fgi_enable_export" value="1" <?php checked(get_option('fgi_enable_export'), '1'); ?> /></td>
                </tr>
                <tr>
                    <th scope="row">Enable Import:</th>
                    <td><input type="checkbox" name="fgi_enable_import" value="1" <?php checked(get_option('fgi_enable_import'), '1'); ?> /></td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}