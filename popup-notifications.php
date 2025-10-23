<?php
/**
 * Plugin Name: Popup Notifications
 * Plugin URI: https://github.com/arvaistary/popup-notifications
 * Description: Плагин для управления всплывающими уведомлениями с настройками в админ-панели. Уведомления появляются через 2 минуты после загрузки страницы.
 * Version: 2.0.0
 * Author: by ArvaIstary
 * Author URI: https://github.com/arvaistary
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: popup-notifications
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Предотвращаем прямой доступ к файлу
if (!defined('ABSPATH')) {
    exit;
}

// Определяем константы плагина
define('POPUP_NOTIFICATIONS_VERSION', '2.0.0');
define('POPUP_NOTIFICATIONS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('POPUP_NOTIFICATIONS_PLUGIN_PATH', plugin_dir_path(__FILE__));

class PopupNotifications {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'display_notifications'));
        
        // AJAX обработчики
        add_action('wp_ajax_popup_notifications_dismiss', array($this, 'ajax_dismiss_notification'));
        add_action('wp_ajax_nopriv_popup_notifications_dismiss', array($this, 'ajax_dismiss_notification'));
        add_action('wp_ajax_popup_notifications_save_settings', array($this, 'ajax_save_settings'));
        
        // Активация плагина
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Проверяем совместимость с другими плагинами
        add_action('admin_notices', array($this, 'check_plugin_compatibility'));
    }
    
    public function init() {
        // Инициализация плагина
        load_plugin_textdomain('popup-notifications', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Активация плагина
     */
    public function activate() {
        // Создаем настройки по умолчанию
        $default_settings = array(
            'enabled' => true,
            'wpml_enabled' => false, // Новое: переключатель WPML
            'notifications' => array(
                'notification_1' => array(
                    'id' => 'notification_1',
                    'enabled' => true,
                    'title' => 'Добро пожаловать!',
                    'content' => 'Спасибо, что посетили наш сайт. Мы рады видеть вас здесь!',
                    'button_text' => 'Понятно',
                    'show_delay' => 120000 // Новое: задержка в миллисекундах (2 минуты)
                ),
                'notification_2' => array(
                    'id' => 'notification_2',
                    'enabled' => true,
                    'title' => 'Специальное предложение',
                    'content' => 'У нас есть специальное предложение для вас. Не упустите возможность!',
                    'button_text' => 'Узнать больше',
                    'show_delay' => 120000 // Новое: задержка в миллисекундах (2 минуты)
                )
            )
        );
        
        add_option('popup_notifications_settings', $default_settings);
    }
    
    /**
     * Деактивация плагина
     */
    public function deactivate() {
        // Очищаем кэш и временные данные
        wp_cache_flush();
    }
    
    /**
     * Проверка совместимости с другими плагинами
     */
    public function check_plugin_compatibility() {
        // Проверяем, активирован ли Really Simple SSL
        if (is_plugin_active('really-simple-ssl/rlrsssl-really-simple-ssl.php')) {
            // Проверяем, настроен ли SSL правильно
            if (!is_ssl() && !is_admin()) {
                echo '<div class="notice notice-warning"><p>';
                _e('Really Simple SSL активирован, но сайт не работает по HTTPS. Это может вызывать Mixed Content ошибки.', 'popup-notifications');
                echo '</p></div>';
            }
        }
        
        // Проверяем настройки WordPress для HTTPS
        if (is_ssl() && strpos(home_url(), 'https://') !== 0) {
            echo '<div class="notice notice-error"><p>';
            _e('Обнаружена проблема с настройками HTTPS. URL сайта должен начинаться с https://', 'popup-notifications');
            echo '</p></div>';
        }
    }
    
    /**
     * Добавляем пункт меню в админ-панель
     */
    public function add_admin_menu() {
        add_options_page(
            __('Popup Notifications', 'popup-notifications'),
            __('Popup Notifications', 'popup-notifications'),
            'manage_options',
            'popup-notifications',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Подключаем стили и скрипты
     */
    public function enqueue_scripts() {
        // Используем is_ssl() для определения протокола
        $protocol = is_ssl() ? 'https' : 'http';
        $plugin_url = set_url_scheme(POPUP_NOTIFICATIONS_PLUGIN_URL, $protocol);
        
        wp_enqueue_style('popup-notifications-style', $plugin_url . 'assets/style.css', array(), POPUP_NOTIFICATIONS_VERSION);
        wp_enqueue_script('popup-notifications-script', $plugin_url . 'assets/script.js', array('jquery'), POPUP_NOTIFICATIONS_VERSION, true);
        
        // Получаем настройки (с учетом WPML если включено)
        $settings = $this->get_settings();
        
        // Передаем данные в JavaScript
        wp_localize_script('popup-notifications-script', 'popupNotifications', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('popup_notifications_nonce'),
            'settings' => $settings
        ));
    }
    
    /**
     * Отображаем уведомления на фронтенде
     */
    public function display_notifications() {
        $settings = $this->get_settings();
        
        if (!$settings['enabled']) {
            return;
        }
        
        $notifications = $this->get_notifications();
        
        foreach ($notifications as $notification) {
            if ($notification['enabled']) {
                $this->render_notification($notification);
            }
        }
    }
    
    /**
     * Рендерим отдельное уведомление
     */
    private function render_notification($notification) {
        ?>
        <div class="popup-notification" data-id="<?php echo esc_attr($notification['id']); ?>" style="display: none;">
            <div class="popup-notification-content">
                <?php if ($notification['title']): ?>
                    <h5 class="popup-notification-title"><?php echo esc_html($notification['title']); ?></h5>
                <?php endif; ?>
                
                <p class="popup-notification-text"><?php echo wp_kses_post($notification['content']); ?></p>
                
                <div class="popup-notification-actions">
                    <?php if ($notification['button_text']): ?>
                        <button class="popup-notification-button" data-action="accept">
                            <?php echo esc_html($notification['button_text']); ?>
                        </button>
                    <?php endif; ?>
                    
                    <button class="popup-notification-close" data-action="dismiss">
                        <span class="i i-close"></span>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX обработчик для закрытия уведомления
     */
    public function ajax_dismiss_notification() {
        // Проверяем nonce
        if (!wp_verify_nonce($_POST['nonce'], 'popup_notifications_nonce')) {
            wp_send_json_error('Неверный nonce');
        }
        
        $notification_id = sanitize_text_field($_POST['notification_id']);
        $action = sanitize_text_field($_POST['action_type']);
        
        if (empty($notification_id)) {
            wp_send_json_error('ID уведомления не указан');
        }
        
        // Сохраняем в cookies, что уведомление было закрыто
        setcookie("popup_notification_dismissed_{$notification_id}", '1', time() + (30 * DAY_IN_SECONDS), '/');
        
        wp_send_json_success(array('message' => 'Уведомление закрыто'));
    }
    
    /**
     * AJAX обработчик для сохранения настроек
     */
    public function ajax_save_settings() {
        // Проверяем nonce
        if (!wp_verify_nonce($_POST['nonce'], 'popup_notifications_nonce')) {
            wp_send_json_error('Неверный nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав для сохранения настроек');
        }
        
        $settings = array(
            'enabled' => isset($_POST['enabled']) ? (bool)$_POST['enabled'] : false,
            'wpml_enabled' => isset($_POST['wpml_enabled']) ? (bool)$_POST['wpml_enabled'] : false, // Новое
            'notifications' => array()
        );
        
        if (isset($_POST['notifications']) && is_array($_POST['notifications'])) {
            foreach ($_POST['notifications'] as $id => $notification) {
                if (is_array($notification)) {
                    $settings['notifications'][$id] = array(
                        'id' => sanitize_text_field($id),
                        'enabled' => isset($notification['enabled']) ? (bool)$notification['enabled'] : false,
                        'title' => sanitize_text_field($notification['title']),
                        'content' => wp_kses_post($notification['content']),
                        'button_text' => sanitize_text_field($notification['button_text']),
                        'show_delay' => isset($notification['show_delay']) ? absint($notification['show_delay']) : 120000 // Новое
                    );
                }
            }
        }
        
        $result = update_option('popup_notifications_settings', $settings);
        
        // Регистрируем строки в WPML если функция включена
        if ($settings['wpml_enabled'] && $this->check_wpml_availability()) {
            $this->register_wpml_strings($settings);
        }
        
        if ($result) {
            wp_send_json_success(array('message' => 'Настройки успешно сохранены'));
        } else {
            wp_send_json_error('Ошибка при сохранении настроек');
        }
    }
    
    /**
     * Получаем настройки плагина
     */
    private function get_settings() {
        $default_settings = array(
            'enabled' => true,
            'wpml_enabled' => false,
            'notifications' => array(
                'notification_1' => array(
                    'id' => 'notification_1',
                    'enabled' => true,
                    'title' => 'Добро пожаловать!',
                    'content' => 'Спасибо, что посетили наш сайт. Мы рады видеть вас здесь!',
                    'button_text' => 'Понятно',
                    'show_delay' => 120000
                ),
                'notification_2' => array(
                    'id' => 'notification_2',
                    'enabled' => true,
                    'title' => 'Специальное предложение',
                    'content' => 'У нас есть специальное предложение для вас. Не упустите возможность!',
                    'button_text' => 'Узнать больше',
                    'show_delay' => 120000
                )
            )
        );
        
        $settings = get_option('popup_notifications_settings', $default_settings);
        
        // Применяем переводы WPML если функция включена
        if (!empty($settings['wpml_enabled']) && function_exists('icl_t')) {
            $settings = $this->apply_wpml_translations($settings);
        }
        
        return wp_parse_args($settings, $default_settings);
    }
    
    /**
     * Получаем список уведомлений
     */
    private function get_notifications() {
        $settings = $this->get_settings();
        return $settings['notifications'];
    }
    
    /**
     * Применяем переводы WPML к настройкам
     * @param array $settings Настройки уведомлений
     * @return array Настройки с переводами
     */
    private function apply_wpml_translations($settings) {
        if (!isset($settings['notifications']) || !is_array($settings['notifications'])) {
            return $settings;
        }
        
        foreach ($settings['notifications'] as $id => $notification) {
            // Переводим заголовок
            if (!empty($notification['title'])) {
                $settings['notifications'][$id]['title'] = icl_t(
                    'popup-notifications',
                    'title_' . $id,
                    $notification['title']
                );
            }
            
            // Переводим содержимое
            if (!empty($notification['content'])) {
                $settings['notifications'][$id]['content'] = icl_t(
                    'popup-notifications',
                    'content_' . $id,
                    $notification['content']
                );
            }
            
            // Переводим текст кнопки
            if (!empty($notification['button_text'])) {
                $settings['notifications'][$id]['button_text'] = icl_t(
                    'popup-notifications',
                    'button_' . $id,
                    $notification['button_text']
                );
            }
        }
        
        return $settings;
    }
    
    /**
     * Регистрируем строки в WPML String Translation
     * @param array $settings Настройки уведомлений
     */
    private function register_wpml_strings($settings) {
        if (!function_exists('icl_register_string')) {
            return;
        }
        
        if (isset($settings['notifications']) && is_array($settings['notifications'])) {
            foreach ($settings['notifications'] as $id => $notification) {
                if (!empty($notification['title'])) {
                    icl_register_string('popup-notifications', 'title_' . $id, $notification['title']);
                }
                if (!empty($notification['content'])) {
                    icl_register_string('popup-notifications', 'content_' . $id, $notification['content']);
                }
                if (!empty($notification['button_text'])) {
                    icl_register_string('popup-notifications', 'button_' . $id, $notification['button_text']);
                }
            }
        }
    }
    
    /**
     * Проверяем доступность WPML
     * @return bool
     */
    private function check_wpml_availability() {
        return function_exists('icl_register_string') && 
               function_exists('icl_t') && 
               defined('ICL_SITEPRESS_VERSION');
    }
    
    /**
     * Страница настроек в админ-панели
     */
    public function admin_page() {
        $settings = $this->get_settings();
        $wpml_available = $this->check_wpml_availability();
        ?>
        <div class="wrap">
            <h1><?php _e('Popup Notifications', 'popup-notifications'); ?></h1>
            
            <form id="popup-notifications-form">
                <?php wp_nonce_field('popup_notifications_nonce', 'popup_notifications_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Включить уведомления', 'popup-notifications'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?>>
                                <?php _e('Показывать всплывающие уведомления', 'popup-notifications'); ?>
                            </label>
                        </td>
                    </tr>
                    <!-- НОВОЕ: Переключатель WPML -->
                    <tr>
                        <th scope="row"><?php _e('Интеграция с WPML', 'popup-notifications'); ?></th>
                        <td>
                            <?php if ($wpml_available): ?>
                                <label>
                                    <input type="checkbox" name="wpml_enabled" value="1" <?php checked(!empty($settings['wpml_enabled'])); ?>>
                                    <?php _e('Использовать WPML для переводов уведомлений', 'popup-notifications'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('При включении этой опции, все тексты уведомлений будут автоматически переводиться через WPML String Translation', 'popup-notifications'); ?>
                                </p>
                            <?php else: ?>
                                <p class="description" style="color: #d63638;">
                                    <?php _e('WPML не обнаружен или не активен. Установите и активируйте WPML для использования этой функции.', 'popup-notifications'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Уведомления', 'popup-notifications'); ?></h2>
                
                <?php foreach ($settings['notifications'] as $notification): ?>
                    <div class="notification-settings" style="border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px;">
                        <h3><?php printf(__('Уведомление #%s', 'popup-notifications'), esc_html($notification['id'])); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Включено', 'popup-notifications'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="notifications[<?php echo esc_attr($notification['id']); ?>][enabled]" value="1" <?php checked($notification['enabled']); ?>>
                                        <?php _e('Показывать это уведомление', 'popup-notifications'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Заголовок', 'popup-notifications'); ?></th>
                                <td>
                                    <input type="text" name="notifications[<?php echo esc_attr($notification['id']); ?>][title]" value="<?php echo esc_attr($notification['title']); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Содержимое', 'popup-notifications'); ?></th>
                                <td>
                                    <textarea name="notifications[<?php echo esc_attr($notification['id']); ?>][content]" rows="4" class="large-text"><?php echo esc_textarea($notification['content']); ?></textarea>
                                </td>
                            </tr>
                        <tr>
                            <th scope="row"><?php _e('Текст кнопки', 'popup-notifications'); ?></th>
                            <td>
                                <input type="text" name="notifications[<?php echo esc_attr($notification['id']); ?>][button_text]" value="<?php echo esc_attr($notification['button_text']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <!-- НОВОЕ: Настройка времени показа -->
                        <tr>
                            <th scope="row"><?php _e('Задержка показа', 'popup-notifications'); ?></th>
                            <td>
                                <input type="number" 
                                       name="notifications[<?php echo esc_attr($notification['id']); ?>][show_delay]" 
                                       value="<?php echo esc_attr(isset($notification['show_delay']) ? $notification['show_delay'] / 1000 : 120); ?>" 
                                       min="0" 
                                       step="1" 
                                       class="small-text"> 
                                <?php _e('секунд', 'popup-notifications'); ?>
                                <p class="description">
                                    <?php _e('Время ожидания перед показом уведомления после загрузки страницы (0 = показать сразу)', 'popup-notifications'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endforeach; ?>
                
                <p class="submit">
                    <button type="submit" class="button-primary"><?php _e('Сохранить настройки', 'popup-notifications'); ?></button>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#popup-notifications-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submitBtn = $form.find('button[type="submit"]');
                
                // Блокируем кнопку отправки
                $submitBtn.prop('disabled', true).text('<?php _e('Сохранение...', 'popup-notifications'); ?>');
                
                // Собираем данные формы
                var formData = {
                    action: 'popup_notifications_save_settings',
                    nonce: $('#popup_notifications_nonce').val(),
                    enabled: $('input[name="enabled"]').is(':checked') ? 1 : 0,
                    wpml_enabled: $('input[name="wpml_enabled"]').is(':checked') ? 1 : 0, // Новое
                    notifications: {}
                };
                
                // Собираем данные уведомлений
                $('.notification-settings').each(function() {
                    var $notification = $(this);
                    var nameAttr = $notification.find('input[name*="[enabled]"]').attr('name');
                    
                    if (nameAttr) {
                        var notificationId = nameAttr.match(/\[([^\]]+)\]/);
                        if (notificationId && notificationId[1]) {
                            // Получаем значение задержки в секундах и конвертируем в миллисекунды
                            var delayInSeconds = parseInt($notification.find('input[name*="[show_delay]"]').val()) || 120;
                            
                            formData.notifications[notificationId[1]] = {
                                enabled: $notification.find('input[name*="[enabled]"]').is(':checked') ? 1 : 0,
                                title: $notification.find('input[name*="[title]"]').val() || '',
                                content: $notification.find('textarea[name*="[content]"]').val() || '',
                                button_text: $notification.find('input[name*="[button_text]"]').val() || '',
                                show_delay: delayInSeconds * 1000 // Конвертируем в миллисекунды
                            };
                        }
                    }
                });
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    timeout: 10000, // 10 секунд таймаут
                    success: function(response) {
                        if (response.success) {
                            // Показываем уведомление об успехе
                            $('<div class="notice notice-success is-dismissible"><p>' + 
                              (response.data && response.data.message ? response.data.message : '<?php _e('Настройки сохранены!', 'popup-notifications'); ?>') + 
                              '</p></div>').insertAfter('.wrap h1').delay(3000).fadeOut();
                        } else {
                            // Показываем уведомление об ошибке
                            $('<div class="notice notice-error is-dismissible"><p>' + 
                              '<?php _e('Ошибка при сохранении настроек:', 'popup-notifications'); ?> ' + 
                              (response.data || '<?php _e('Неизвестная ошибка', 'popup-notifications'); ?>') + 
                              '</p></div>').insertAfter('.wrap h1');
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = '<?php _e('Ошибка при отправке запроса', 'popup-notifications'); ?>';
                        if (status === 'timeout') {
                            errorMessage = '<?php _e('Превышено время ожидания запроса', 'popup-notifications'); ?>';
                        } else if (xhr.responseText) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if (response.data) {
                                    errorMessage = response.data;
                                }
                            } catch(e) {
                                // Игнорируем ошибки парсинга JSON
                            }
                        }
                        
                        $('<div class="notice notice-error is-dismissible"><p>' + errorMessage + '</p></div>').insertAfter('.wrap h1');
                    },
                    complete: function() {
                        // Разблокируем кнопку отправки
                        $submitBtn.prop('disabled', false).text('<?php _e('Сохранить настройки', 'popup-notifications'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Инициализируем плагин
new PopupNotifications();