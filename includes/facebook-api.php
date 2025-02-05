<?php
class FacebookAPI {
    private $api_token;
    private $group_id;

    public function __construct($api_token, $group_id) {
        $this->api_token = $api_token;
        $this->group_id = $group_id;
    }

    // Отправка поста в группу
    public function postToGroup($message, $image_url = null) {
        $url = "https://graph.facebook.com/v16.0/{$this->group_id}/feed";
        $params = [
            'access_token' => $this->api_token,
            'message' => $message,
        ];

        if ($image_url) {
            $params['url'] = $image_url;
        }

        $response = wp_remote_post($url, [
            'body' => $params,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    // Получение постов из группы
    public function getGroupPosts() {
        $url = "https://graph.facebook.com/v16.0/{$this->group_id}/feed?access_token={$this->api_token}&fields=id,message,picture,attachments{media},created_time&limit=10";

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true)['data'] ?? [];

        foreach ($data as &$post) {
            // Получаем главное изображение
            $post['image_url'] = $post['picture'] ?? '';

            // Получаем все дополнительные изображения
            $post['images'] = [];
            if (!empty($post['attachments']['data'])) {
                foreach ($post['attachments']['data'] as $attachment) {
                    if (!empty($attachment['media']['image']['src'])) {
                        $post['images'][] = $attachment['media']['image']['src'];
                    }
                }
            }
        }

        return $data;
    }
}