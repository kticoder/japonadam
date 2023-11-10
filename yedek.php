<?php

/*
Plugin Name: Japon Adam Aktivasyon
Description: Aktivasyon kodu doğrulama eklentisi
Version: 1.1.5
Author: Melih Çat & Ktidev
*/

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/kticoder/japonadam',
	__FILE__,
	'japonadam'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');
$myUpdateChecker->getVcsApi()->enableReleaseAssets();

class JaponAdamAktivasyon {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'jpn';

        add_action('admin_menu', [$this, 'japon_adam_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_custom_styles_scripts']);
        add_action('init', [$this, 'create_jpn_table_if_not_exists']);
    }

    // Yönetici menüsüne "Japon Adam" sayfasını ekler.
    public function japon_adam_menu() {
        add_menu_page('Japon Adam', 'Japon Adam', 'manage_options', 'japon-adam', [$this, 'display_jp_tut_page'], 'dashicons-cart', 6);
    }

    // Özel stil ve scriptleri yalnızca "Japon Adam" sayfasında yükler.
    public function enqueue_custom_styles_scripts($hook) {
        if ($hook === 'toplevel_page_japon-adam') {
            wp_enqueue_style('tailwind-css', 'https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css');
            wp_enqueue_script('japonadam-js', plugins_url('jpn.js', __FILE__), [], false, true);
        }
    }

    // lisanslı ürünleri getirir.
    public function fetch_lisans_products() {
        $response = wp_remote_get("https://japonadam.com/wp-json/mylisans/v1/get-lisans-products/");
        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    // Eğer mevcut değilse, veritabanında tabloyu oluşturur.
    public function create_jpn_table_if_not_exists() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)) != $this->table_name) {
            $sql = "CREATE TABLE $this->table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                aktivasyon_kodu varchar(255) NOT NULL,
                purchased_products LONGTEXT NOT NULL,
                -- installed LONGTEXT DEFAULT '' NOT NULL,
                PRIMARY KEY (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            if ($wpdb->last_error !== '') {
                error_log("DB Table Creation Error: " . $wpdb->last_error);
                return $wpdb->last_error;
            } else {
                return 'Tablo başarılı bir şekilde oluşturuldu!';
            }
        }
        return 'Tablo zaten var.';
    }

    /**
     * Kullanıcının satın aldığı ürünleri veritabanından alır.
     */
    public function fetch_purchased_products() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jpn';
        $result = $wpdb->get_var("SELECT purchased_products FROM $table_name LIMIT 1");
        if ($result) {
            $decoded_result = json_decode($result, true);
            return is_array($decoded_result) ? $decoded_result : [];
        }
        return [];
    }

    /**
     * Aktivasyon kodunun veritabanında olup olmadığını kontrol eder.
     * Eğer kod mevcut değilse yeni bir satır ekler.
     */
    public function check_and_insert_activation_code($aktivasyon_kodu, $purchased_products) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jpn';

        $existing_code = $wpdb->get_var($wpdb->prepare("SELECT aktivasyon_kodu FROM $table_name WHERE aktivasyon_kodu = %s", $aktivasyon_kodu));

        if (!$existing_code) {
            $inserted = $wpdb->insert($table_name, [
                'aktivasyon_kodu' => $aktivasyon_kodu,
                'purchased_products' => wp_json_encode($purchased_products)
            ]);

            if ($inserted === false) {
                error_log("DB Insert Error: Veritabanına ekleme yapılamadı.");
                return;
            }

            if($wpdb->last_error !== '') {
                error_log("DB Insert Error: " . $wpdb->last_error);
            }
        }
    }

    /**
     * Tüm aktivasyon kodlarını veritabanından siler.
     */
    public function remove_activation_code() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jpn';
        return $wpdb->query("TRUNCATE TABLE $table_name");
    }

    /**
     * Aktivasyon kodunun geçerli olup olmadığını doğrular.
     */
    public function aktivasyon_kodu_getir() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jpn';
        
        // Varsayılan olarak ilk satırdaki aktivasyon kodunu alıyor.
        $result = $wpdb->get_var("SELECT aktivasyon_kodu FROM $table_name LIMIT 1");
        
        return $result;
    }
    public function verify_activation_code() {
        global $wpdb;

        if (isset($_POST['remove_activation']) && $_POST['remove_activation'] === '1') {
            $this->remove_activation_code();
            return ["success" => true, "message" => "Aktivasyon kaldırıldı."];
        }

        if (!isset($_POST['aktivasyon_kodu'])) {
            return null;
        }

        $aktivasyon_kodu = sanitize_text_field($_POST['aktivasyon_kodu']);
        $response = wp_remote_get("https://japonadam.com/wp-json/mylisans/v1/api/?activation_key={$aktivasyon_kodu}");

        if (is_wp_error($response)) {
            return ["success" => false, "message" => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['valid']) || !$data['valid']) {
            return $data;
        }

        $this->check_and_insert_activation_code(
            $aktivasyon_kodu,
            isset($data['purchased_products']) ? $data['purchased_products'] : []
        );

        return $data;
    }
    public function is_product_installed($productid) {
        if (!isset($_POST['aktivasyon_kodu'])) {
            return null;
        }

        $aktivasyon_kodu = sanitize_text_field($_POST['aktivasyon_kodu']);
        $site_url = get_site_url();
        $response = wp_remote_get("https://japonadam.com/wp-json/mylisans/v1/check-productid/?activation_key={$aktivasyon_kodu}&site_url={$site_url}&productid={$productid}");

        return $response;
    }
    /**
     * Aktivasyon kodunun veritabanında olup olmadığını kontrol eder.
     */
    public function is_activation_code_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jpn';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        return $count > 0;
    }
    
    public function check_activation_status($site_url, $productid, $activation_key) {
        $site_url = get_site_url();
        // https varsa http'ye çevir
        $site_url = str_replace('https://', 'http://', $site_url);
        $parametreler = [
            'site_url' => $site_url,
            'product_id' => $productid,
            'activation_key' => $activation_key
        ];
        $response = wp_remote_get("http://japonadam.com/wp-json/mylisans/v1/check-activation-status/?site_url={$site_url}&product_id={$productid}&activation_key={$activation_key}");
        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return $data['success'];
    }


    public function display_jp_tut_page() {
        $all_products = $this->fetch_lisans_products();
        $result = $this->verify_activation_code();
        $aktivasyon_kodu = $this->aktivasyon_kodu_getir();
        $isActivated = $this->is_activation_code_exists();
        $inputValue = $isActivated ? '•••••••••••••••••' : '';
        $buttonValue = $isActivated ? 'Aktivasyonu Kaldır' : 'Doğrula';
        $buttonColor = $isActivated ? '#705b92' : '#f33059';
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'all_products';
        // Satın alınan ürünlerin productid'lerini al
        $purchased_product_ids = $this->fetch_purchased_products();

        if (!is_array($purchased_product_ids)) {
            $purchased_product_ids = [];
        }

        $filtered_products = array_filter($all_products, function($product) use ($purchased_product_ids) {
            return in_array($product['productid'], array_keys($purchased_product_ids));
        });

        if ($tab === 'purchased') {
            $products = $filtered_products;
        } else {
            $products = $all_products;
        }
        ?>
        <!-- Ana konteyner -->
        <div class="jp-tut rounded-2xl m-12" style="background-color: #2c1d58;">

            <!-- Üst navigasyon bölümü -->
            <div class="jp-nav flex justify-between items-center p-6 rounded-t-2xl" style="background:#332363;">

                <!-- Logo bölümü -->
                <div class="jp-logo">
                    <h2 class="text-white text-2xl">Japon Adam</h2>
                </div>

                <!-- Ürün kategorileri menüsü -->
                <div class="jp-tab-menu flex items-center text-white text-lg">
                    <ul class="flex gap-5 list-none p-0 m-0 items-center">
                        <li class="py-2 px-4 rounded cursor-pointer transition-colors duration-300">
                            <a href="?page=japon-adam&tab=all_products" class="hover:text-blue-100">Tüm Ürünler</a>
                        </li>
                        <div class="h-5 border-r-2 border-purple-800 mx-2"></div>
                        <li class="py-2 px-4 rounded cursor-pointer transition-colors duration-300">
                            <a href="?page=japon-adam&tab=purchased" class="hover:text-blue-100">Satın Aldıklarım</a>
                        </li>
                    </ul>
                </div>

                <!-- Aktivasyon kodu giriş formu -->
                <div class="jp-aktivasyon flex items-center ">
                    <form class="flex gap-2 w-full" method="post">
                        <input type="text" id="aktivasyonInput" name="aktivasyon_kodu" value="<?php echo esc_attr($inputValue); ?>" placeholder="Aktivasyon kodunuz" class="py-2 px-3 border-0 rounded text-sm flex-grow" <?php echo $isActivated ? 'readonly' : ''; ?>>
                        <input type="hidden" name="remove_activation" value="<?php echo $isActivated ? '1' : '0'; ?>">
                        <input type="submit" id="aktivasyonButton" value="<?php echo esc_attr($buttonValue); ?>" style="background-color: <?php echo esc_attr($buttonColor); ?>;" class="py-2 px-4 border-0 text-sm text-white cursor-pointer rounded">
                    </form>
                </div>
            </div>

            <!-- Ürün listesi bölümü -->
            <div class="jp-content p-12">
                <div class="jpn-product grid grid-cols-4 gap-5">
                    <?php foreach($products as $product): ?>
                        <!-- Ürün kartı -->
                        <div class="product flex flex-col justify-between border p-5 rounded-lg" style="border-color: #4f3e80; background-color: #332363; border-radius: 10px;" data-productid="<?php echo esc_attr($product['productid']); ?>" data-purchased="<?php echo in_array($product['productid'], array_keys($purchased_product_ids)) ? 'true' : 'false'; ?>">
                            <!-- Ürün detayları -->
                            <div class="product-info flex-grow">
                                <img src="<?php echo esc_url($product['thumbnail']); ?>" alt="<?php echo esc_attr($product['title']); ?>" style="border-color: #4f3e80;" class="w-full rounded-lg">
                                <div class="flex justify-between text-sm text-gray-400 mt-2 mb-1">
                                    <p>🔥 <?php echo esc_html($product['indirme_sayaci']); ?> 
                                    <p># <?php echo esc_html($product['veri_tipi']); ?></p>
                                </div>
                                <h3 class="text-white text-xl mb-1"><?php echo esc_html($product['title']); ?></h3>
                                <p class="text-gray-400 text-md mb-4"><?php echo esc_html($product['short_description']); ?></p>
                            </div>
                            <!-- Ürün işlem butonları -->
                            <div>
                                <!-- <button id="installplugin" class="bg-red-600 text-white border-0 rounded-lg py-2 text-md w-full" onclick="checkAndInstallPlugin(this,'<?php echo esc_attr($aktivasyon_kodu); ?>','<?php echo esc_attr($product['download_link']); ?>')" style="background-color: #f33059;"><?php echo  $this->check_activation_status(get_site_url(), $product['productid'], $aktivasyon_kodu) == 'true' ? 'Güncelle' : 'Kur'; ?></button> -->
                                <?php if ($this->check_activation_status(get_site_url(), $product['productid'], $aktivasyon_kodu) == 'true') { ?>
                                    <button id="installplugin" class="bg-green-600 text-white border-0 rounded-lg py-2 text-md w-full" onclick="checkAndInstallPlugin(this,'<?php echo esc_attr($aktivasyon_kodu); ?>','<?php echo esc_attr($product['download_link']); ?>')" style="background-color: #008000;">Güncelle</button>
                                <?php } else { ?>
                                    <button id="installplugin" class="bg-red-600 text-white border-0 rounded-lg py-2 text-md w-full" onclick="checkAndInstallPlugin(this,'<?php echo esc_attr($aktivasyon_kodu); ?>','<?php echo esc_attr($product['download_link']); ?>')" style="background-color: #f33059;">Kurulum Yap</button>
                                <?php } ?>

                                <div class="flex justify-between mt-2">
                                    <button href="<?php echo esc_url($product['permalink']); ?>" class="bg-blue-500 text-white border-0 rounded-lg py-2 text-md flex-grow mr-2" onclick="window.open('<?php echo esc_url($product['permalink']); ?>', '_blank')" >Satın Al</button>
                                    <a href="<?php echo esc_url($product['permalink']); ?>" class="bg-purple-500 text-white border-0 rounded-lg py-2 px-6 text-md hover:text-blue-100" style="background-color:#705b92;">İncele</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- Kurulum durumu popup bilgisi -->
                <div id="loadingPopup" class="fixed inset-0 flex items-center justify-center z-999 bg-black bg-opacity-50 hidden">
                    <div class="bg-white p-5 rounded-lg">
                        <p class="text-center mb-3">Kurulum yapılıyor...</p>
                        <div class="h-4 bg-gray-300 rounded-full">
                            <div id="progressBar" class="h-4 bg-purple-800 rounded-full" style="width: 0;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sayfa alt bilgi bölümü -->
            <div class="jp-footer flex justify-center items-center p-4 text-white">
                <div class="jp-destek mr-4">
                    <a href="#">Destek</a>
                </div>
                <div class="jp-sitemiz">
                    <a href="#">Sitemizi Ziyaret Edin</a>
                </div>
            </div>
        </div>

        <?php
    }
}

// Eklenti çalıştırılırken sınıfın bir örneğini oluşturma
new JaponAdamAktivasyon();
add_action('rest_api_init', function () {
    register_rest_route('mylisans/v1', '/install-plugin/', array(
        'methods' => 'GET',
        'callback' => 'install_plugin_endpoint_callback',
        'permission_callback' => '__return_true'
    ));
});

function install_plugin_endpoint_callback($request) {
    $download_links = $request->get_param('download_link');
    return install_plugin_or_theme($download_links);
}

function install_plugin_or_theme($download_links) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
    require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    require_once(ABSPATH . 'wp-admin/includes/theme.php');

    $links = explode(',', $download_links);
    foreach ($links as $link) {
        $link = trim($link);
        if (strpos($link, 'theme') !== false) {  // Tema linklerinde 'theme' kelimesini kontrol ediyoruz
            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Theme_Upgrader($skin);
            $installed = $upgrader->install($link);
            if (!$installed || is_wp_error($installed)) {
                return array(
                    'success' => false,
                    'message' => 'Tema kurulamadı: ' . $skin->get_errors()->get_error_message()
                );
            }
        } else {  // Eğer 'theme' kelimesi yoksa, eklenti olarak kabul ediyoruz
            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);
            $installed = $upgrader->install($link);
            if (!$installed || is_wp_error($installed)) {
                return array(
                    'success' => false,
                    'message' => 'Eklenti kurulamadı: ' . $skin->get_errors()->get_error_message()
                );
            }

            $plugin_file = $upgrader->plugin_info();
            $activate = activate_plugin($plugin_file);
            if (is_wp_error($activate)) {
                return array(
                    'success' => false,
                    'message' => 'Eklenti kuruldu ama aktifleştirilemedi: ' . $activate->get_error_message()
                );
            }
        }
    }
    return array(
        'success' => true,
        'message' => 'Eklenti/tema başarıyla kuruldu ve aktifleştirildi!'
    );
}


