jQuery(document).ready(function($) {
    'use strict';
    
    // Проверяем, что объект popupNotifications доступен
    if (typeof popupNotifications === 'undefined') {
        console.error('Popup Notifications: Конфигурация не загружена');
        return;
    }
    
    const settings = popupNotifications.settings;
    const ajaxUrl = popupNotifications.ajaxUrl;
    const nonce = popupNotifications.nonce;
    
    // Если уведомления отключены, выходим
    if (!settings.enabled) {
        return;
    }
    
    // Удаляем глобальную константу - теперь используем индивидуальные задержки
    
    // Массив для хранения таймеров уведомлений
    const notificationTimers = [];
    
    // Инициализация уведомлений
    function initNotifications() {
        const notifications = $('.popup-notification');
        
        notifications.each(function() {
            const $notification = $(this);
            const notificationId = $notification.data('id');
            
            // Проверяем, не было ли уведомление уже закрыто
            if (isNotificationDismissed(notificationId)) {
                $notification.remove();
                return;
            }
            
            // Находим настройки для этого уведомления
            const notificationSettings = findNotificationSettings(notificationId);
            if (!notificationSettings || !notificationSettings.enabled) {
                $notification.remove();
                return;
            }
            
            // Добавляем класс для идентификации типа уведомления
            $notification.addClass('notification-' + notificationId.split('_')[1]);
            
            // Получаем индивидуальную задержку для уведомления (по умолчанию 120 секунд)
            const showDelay = notificationSettings.show_delay || 120000;
            
            // Показываем уведомление с индивидуальной задержкой
            const timer = setTimeout(function() {
                showNotification($notification);
            }, showDelay);
            
            notificationTimers.push(timer);
        });
    }
    
    // Показ уведомления
    function showNotification($notification) {
        $notification.show().addClass('show');
    }
    
    // Скрытие уведомления
    function hideNotification($notification, action = 'dismiss') {
        const notificationId = $notification.data('id');
        
        // Анимация скрытия
        $notification.addClass('hide');
        
        setTimeout(function() {
            $notification.remove();
        }, 350);
        
        // Отправляем AJAX запрос о закрытии уведомления
        dismissNotification(notificationId, action);
    }
    
    // Отправка AJAX запроса о закрытии уведомления
    function dismissNotification(notificationId, action) {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'popup_notifications_dismiss',
                nonce: nonce,
                notification_id: notificationId,
                action_type: action
            },
            success: function(response) {
                if (response.success) {
                    // Сохраняем в localStorage, что уведомление было закрыто
                    localStorage.setItem('popup_notification_dismissed_' + notificationId, '1');
                }
            },
            error: function() {
                console.error('Ошибка при отправке запроса о закрытии уведомления');
            }
        });
    }
    
    // Проверка, было ли уведомление уже закрыто
    function isNotificationDismissed(notificationId) {
        // Сначала проверяем localStorage
        if (localStorage.getItem('popup_notification_dismissed_' + notificationId)) {
            return true;
        }
        
        // Затем проверяем cookies
        const cookieName = 'popup_notification_dismissed_' + notificationId;
        return document.cookie.indexOf(cookieName + '=1') !== -1;
    }
    
    // Поиск настроек уведомления
    function findNotificationSettings(notificationId) {
        if (settings.notifications && settings.notifications[notificationId]) {
            return settings.notifications[notificationId];
        }
        return null;
    }
    
    // Обработчики событий
    function bindEvents() {
        // Обработчик клика по кнопке принятия
        $(document).on('click', '.popup-notification-button[data-action="accept"]', function(e) {
            e.preventDefault();
            const $notification = $(this).closest('.popup-notification');
            hideNotification($notification, 'accept');
        });
        
        // Обработчик клика по кнопке закрытия
        $(document).on('click', '.popup-notification-close[data-action="dismiss"]', function(e) {
            e.preventDefault();
            const $notification = $(this).closest('.popup-notification');
            hideNotification($notification, 'dismiss');
        });
        
    }
    
    // Очистка таймеров при уходе со страницы
    function cleanup() {
        notificationTimers.forEach(function(timer) {
            clearTimeout(timer);
        });
        notificationTimers.length = 0;
    }
    
    // Инициализация
    initNotifications();
    bindEvents();
    
    // Очистка при уходе со страницы
    $(window).on('beforeunload', cleanup);
    
    // Дополнительная очистка при скрытии страницы
    $(document).on('visibilitychange', function() {
        if (document.hidden) {
            cleanup();
        }
    });
});
