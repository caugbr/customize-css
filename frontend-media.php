<?php

// Bloqueia uploads via AJAX no frontend
function block_media_upload() {
    wp_send_json_error(['message' => __('Uploads are not allowed.', 'text-domain')]);
}

// Filtra os arquivos exibidos na mídia
function filter_media_library($query) {
    if (defined('DOING_AJAX') && DOING_AJAX && $_POST['action'] === 'query-attachments') {
        global $query_filter;
        if (is_callable($query_filter)) {
            $query = call_user_func($query_filter, $query);
        }
    }
    return $query;
}

// Bloqueia exclusão de mídias no frontend
function restrict_media_deletion($allcaps, $cap, $args) {
    if ($cap[0] === 'delete_post') {
        $post = get_post($args[0]);
        if ($post && $post->post_type === 'attachment') {
            $allcaps['delete_post'] = false;
        }
    }
    return $allcaps;
}

$query_filter = null;
function restrict_frontend_media($filter = null) {
    if (!is_admin()) {
        if (is_callable($filter)) {
            global $query_filter;
            $query_filter = $filter;
        }
        add_action('wp_ajax_upload-attachment', 'block_media_upload');
        add_filter('ajax_query_attachments_args', 'filter_media_library');
        add_filter('user_has_cap', 'restrict_media_deletion', 10, 3);
    }
}
