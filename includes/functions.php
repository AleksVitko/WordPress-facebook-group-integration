<?php
// Добавление пользовательского интервала cron
add_filter('cron_schedules', function ($schedules) {
    $schedules['minutely'] = [
        'interval' => 60,
        'display' => __('Once Minutely'),
    ];
    return $schedules;
});

// Отправка поста в группу Facebook
function fgi_send_post_to_facebook($post_id) {
    if (!get_option('fgi_enable_export')) {
        return;
    }

    $api_token = get_option('fgi_api_token');
    $group_id = get_option('fgi_group_id');

    if (!$api_token || !$group_id) {
        return;
    }

    $post = get_post($post_id);

    // Получаем категории и метки
    $categories = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);
    $tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);

    // Формируем сообщение
    $message = sprintf(
        __("%s\n\n%s\n\nCategories: %s\nTags: %s", 'facebook-group-integration'),
        $post->post_title,
        $post->post_content,
        implode(', ', $categories),
        implode(', ', $tags)
    );

    // Получаем главное изображение
    $image_url = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'full')[0] ?? '';

    // Создаем экземпляр API
    $fb_api = new FacebookAPI($api_token, $group_id);

    // Отправляем пост в группу
    $fb_api->postToGroup($message, $image_url);
}
add_action('publish_post', 'fgi_send_post_to_facebook');

// Мониторинг группы Facebook
function fgi_monitor_facebook_group() {
    if (!get_option('fgi_enable_import')) {
        return;
    }
    $api_token = get_option('fgi_api_token');
    $group_id = get_option('fgi_group_id');
    if (!$api_token || !$group_id) {
        return;
    }

    // Подключаем необходимые файлы для media_sideload_image()
    if (!function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $fb_api = new FacebookAPI($api_token, $group_id);
    $posts = $fb_api->getGroupPosts();

    foreach ($posts as $post) {
        // Проверяем, существует ли ключ 'id'
        if (empty($post['id'])) {
            continue; // Пропускаем записи без идентификатора
        }

        $existing_post = get_posts([
            'meta_key' => 'facebook_post_id',
            'meta_value' => $post['id'],
            'post_type' => 'post',
            'numberposts' => 1,
        ]);

        if (empty($existing_post)) {
            // Создаем новое объявление
            $new_post = [
                'post_title' => !empty($post['message']) ? sanitize_text_field($post['message']) : __('New Post', 'facebook-group-integration'),
                'post_content' => !empty($post['message']) ? wpautop(sanitize_textarea_field($post['message'])) : '',
                'post_status' => 'publish',
                'post_type' => 'post',
            ];
            $post_id = wp_insert_post($new_post);
            update_post_meta($post_id, 'facebook_post_id', $post['id']);

            // Назначаем категорию на основе ключевых слов
            if (!empty($post['message'])) {
                $categories = fgi_determine_category_from_message($post['message']);
                if (!empty($categories)) {
                    wp_set_object_terms($post_id, $categories, 'category');
                }
            }

            // Загружаем главное изображение
            if (!empty($post['image_url'])) {
                $attachment_id = media_sideload_image($post['image_url'], $post_id, '', 'id');
                set_post_thumbnail($post_id, $attachment_id);
            }

            // Загружаем все дополнительные изображения
            if (!empty($post['images'])) {
                foreach ($post['images'] as $image_url) {
                    $attachment_id = media_sideload_image($image_url, $post_id, '', 'id');
                    if ($attachment_id) {
                        // Добавляем изображение в содержимое поста
                        $image_html = sprintf('<img class="wp-image-%d" src="%s" alt="%s">', $attachment_id, $image_url, esc_attr(!empty($post['message']) ? $post['message'] : ''));
                        $current_content = get_post_field('post_content', $post_id);
                        wp_update_post([
                            'ID' => $post_id,
                            'post_content' => $image_html . "\n\n" . $current_content,
                        ]);
                    }
                }
            }
        }
    }
}

// Планирование задачи cron
if (!wp_next_scheduled('fgi_monitor_facebook_group')) {
    wp_schedule_event(time(), 'minutely', 'fgi_monitor_facebook_group');
}
add_action('fgi_monitor_facebook_group', 'fgi_monitor_facebook_group');

// Определение категории на основе ключевых слов
function fgi_determine_category_from_message($message) {
    $categories = [
        'Electronics' => ['phone', 'laptop', 'tv', 'camera'],
        'Vehicles' => ['car', 'bike', 'truck'],
        'Real Estate' => ['house', 'apartment', 'rent'],
        'Books' => ['book', 'novel', 'magazine'],
    ];

    foreach ($categories as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return [$category];
            }
        }
    }

    return [];
}