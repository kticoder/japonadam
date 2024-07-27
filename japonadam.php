<?php
/*
Plugin Name: Japon Adam Aktivasyon
Description: Aktivasyon kodu doğrulama eklentisi
Version: 1.1.37
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

function japonadam_load_textdomain() {
    load_plugin_textdomain('japonadam', false, basename(dirname(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'japonadam_load_textdomain');

function japonadam_add_help_center_submenu() {
    $satin_alinan_site = new JaponAdamAktivasyon();
    $satin_alinan_site = $satin_alinan_site->get_purchase_site($satin_alinan_site->aktivasyon_kodu_getir());
    // Aktivasyon kodu kontrolü
    if (!$satin_alinan_site) {
        return;
    }
    // add_submenu_page('japon-adam', 'Yardım Merkezi', 'Yardım Merkezi', 'manage_options', 'japonadam-help-center', 'japonadam_help_center_redirect');
    // add_submenu_page('japon-adam', __('Yardım Merkezi', 'japonadam'), __('Yardım Merkezi', 'japonadam'), 'manage_options', 'japonadam-help-center', 'japonadam_help_center_redirect');
}

function japonadam_help_center_redirect() {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">';
    $response = wp_remote_get('https://japonadam.com/wp-json/sitekisitlama/v1/issues');
    if (is_wp_error($response)) {
        echo '<div class="text-red-500">API isteği başarısız oldu.</div>';
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $issues = json_decode($body, true);

    if (!is_array($issues)) {
        echo '<div class="text-red-500">Veri alınamadı.</div>';
        return;
    }

    // Kategorilere göre gruplama
    $categories = [];
    foreach ($issues as $issue) {
        $categories[$issue['category_name']][] = $issue;
    }

    // Tabbed arayüz
    echo '<div class="container mx-auto p-4">';
    echo '<div class="tabs flex border-b mb-4">';
    foreach ($categories as $category_name => $category_issues) {
        echo '<button class="tab px-4 py-2" data-category="' . esc_attr($category_name) . '">' . esc_html($category_name) . '</button>';
    }
    echo '</div>';

    // Sorunlar ve çözümler
    foreach ($categories as $category_name => $category_issues) {
        echo '<div class="category-content hidden" data-category="' . esc_attr($category_name) . '">';
        foreach ($category_issues as $issue) {
            echo '<div class="issue bg-white p-4 rounded shadow mb-4">';
            echo '<div class="flex justify-between items-center">';
            echo '<h3 class="text-xl font-bold">' . esc_html($issue['issue_title']) . '</h3>';
            echo '<button class="toggle-solution bg-blue-500 text-white px-2 py-1 rounded">+</button>';
            echo '</div>';
            echo '<div class="solution bg-gray-100 p-2 mt-2 rounded hidden">';
            echo ($issue['solution']);
            echo '</div>';
            echo '<p class="text-sm text-gray-500 mt-2">' . esc_html($issue['created_at']) . '</p>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';

    // JavaScript for tab and toggle functionality
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const tabs = document.querySelectorAll(".tab");
            const contents = document.querySelectorAll(".category-content");
            const toggleButtons = document.querySelectorAll(".toggle-solution");

            tabs.forEach(tab => {
                tab.addEventListener("click", function() {
                    const category = this.getAttribute("data-category");

                    tabs.forEach(t => t.classList.remove("border-b-2", "border-blue-500"));
                    this.classList.add("border-b-2", "border-blue-500");

                    contents.forEach(content => {
                        if (content.getAttribute("data-category") === category) {
                            content.classList.remove("hidden");
                        } else {
                            content.classList.add("hidden");
                        }
                    });
                });
            });

            toggleButtons.forEach(button => {
                button.addEventListener("click", function() {
                    const solution = this.parentElement.nextElementSibling;
                    solution.classList.toggle("hidden");
                    this.textContent = solution.classList.contains("hidden") ? "+" : "-";
                });
            });

            // İlk sekmeyi ve içeriği varsayılan olarak göster
            if (tabs.length > 0) {
                tabs[0].click();
            }
        });
    </script>';
}
# satın alınan site varsa



class JaponAdamAktivasyon {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'jpn';

        add_action('admin_bar_menu', [$this, 'add_notification_menu']);

        // add_action('wp_enqueue_scripts', [$this, 'my_theme_enqueue_scripts']);
        add_action('admin_menu', [$this, 'japon_adam_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_custom_styles_scripts']);
        add_action('init', [$this, 'create_jpn_table_if_not_exists']);
        add_action('wp_ajax_refresh_purchased_products', [$this, 'refresh_purchased_products']);
    }

    // Yönetici menüsüne "Japon Adam" sayfasını ekler.
    public function japon_adam_menu() {
        # bu eklentinin adıyla aynı olsun
        $eklenti_bilgileri = get_plugin_data(__FILE__);
        $eklenti_adi = $eklenti_bilgileri['Name'];
        add_menu_page('Japon Adam', $eklenti_adi, 'manage_options', 'japon-adam', [$this, 'display_jp_tut_page'], 'dashicons-cart', 6);
        // add_submenu_page('Japon Adam', 'Aktivasyon & Kurulum', 'Aktivasyon & Kurulum', 'manage_options', 'japon-adam', [$this, 'display_jp_tut_page']);
        // add_submenu_page('japon-adam', 'Aktivasyon', 'Aktivasyon', 'manage_options', 'japon-adam', [$this, 'display_jp_tut_page']);
        add_submenu_page('japon-adam', __('Aktivasyon', 'japonadam'), __('Aktivasyon', 'japonadam'), 'manage_options', 'japon-adam', [$this, 'display_jp_tut_page']);
        japonadam_add_help_center_submenu();
    }

    public function add_notification_menu() {
        add_action('admin_bar_menu', [$this, 'custom_toolbar_menu'], 999);
    }

    public function custom_toolbar_menu($wp_admin_bar) {
        $notifications = $this->get_notifications();
        $notification_count = count($notifications);

        $args = [
            'id' => 'japonadam_notifications',
            'title' => '<span class="ab-icon dashicons dashicons-bell"></span><span class="ab-label">' . $notification_count . '</span>',
            'href' => '#',
        ];
        $wp_admin_bar->add_node($args);

        foreach ($notifications as $notification) {
            $wp_admin_bar->add_node([
                'id' => 'japonadam_notification_' . $notification->id,
                'parent' => 'japonadam_notifications',
                'title' => $notification->bildirim_metni,
                'href' => '#',
            ]);
        }
    }
    private function get_notifications() {
        // Check if notifications were fetched in the last 10 minutes
        $last_checked = get_transient('japonadam_last_notification_check');
        if ($last_checked && (time() - $last_checked) < 3600) { // 600 seconds = 10 minutes
            return get_transient('japonadam_notifications');
        }

        // Fetch new notifications from the API
        $response = wp_remote_get('https://japonadam.com/wp-json/bildirimler/v1/liste');
            
        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $notifications = json_decode($body);

        if (is_array($notifications)) {
            $notifications = array_slice($notifications, -3);
        } else {
            $notifications = [];
        }

        // Save notifications to the transient
        set_transient('japonadam_notifications', $notifications, 3600); // 600 seconds = 10 minutes
        set_transient('japonadam_last_notification_check', time(), 3600); // 600 seconds = 10 minutes

        return $notifications;
    }

    // Method to get notifications from the transient
    public function get_cached_notifications() {
        $notifications = get_transient('japonadam_notifications');
        if ($notifications === false) {
            $notifications = $this->get_notifications();
        }
        return $notifications;
    }
    // Özel stil ve scriptleri yalnızca "Japon Adam" sayfasında ykler.
    public function enqueue_custom_styles_scripts($hook) {
        if ($hook === 'toplevel_page_japon-adam') {
            wp_enqueue_style('tailwind-css', 'https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css');
            wp_enqueue_script('japonadam-js', plugins_url('jpn.js', __FILE__), [], false, true);

            $translation_array = array(
                'notPurchased' => __('Bu ürünü henüz satın almadınız.', 'japonadam'),
                'pluginInstalled' => __('Ürününüz başarıyla kuruldu', 'japonadam'),
                'pluginAlreadyInstalled' => __('Bu ürün sitenizde zaten kurulmuş.', 'japonadam'),
                'pluginExists' => __('Bu eklenti sitenizde zaten kurulu.', 'japonadam'),
                'errorOccurred' => __('Bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.', 'japonadam'),
                'productInstalled' => __('Bu ürün sitenizde kurulu.', 'japonadam')

            );

            wp_localize_script('japonadam-js', 'translations', $translation_array);
            // wp_enqueue_script('japonadam-js', plugins_url('jpn.js', __FILE__), [], false, true);
        }
    }

    // lisanslı ürünleri getirir.
    public function fetch_lisans_products() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jpn_products';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if the table exists, and create it if it doesn't
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                sku varchar(255) NOT NULL,
                title varchar(255) NOT NULL,
                thumbnail varchar(255) NOT NULL,
                indirme_sayaci int NOT NULL,
                veri_tipi varchar(255) NOT NULL,
                short_description text NOT NULL,
                permalink varchar(255) NOT NULL,
                PRIMARY KEY (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            if ($wpdb->last_error !== '') {
                error_log("DB Table Creation Error: " . $wpdb->last_error);
                return [];
            }
        }
        
        // Check if the data exists in the database
        $existing_products = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        if ($existing_products > 0) {
            // Return the data from the database
            return $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
        }
        

        // Fetch product data from the API
        $response = wp_remote_get("https://japonadam.com/wp-json/mylisans/v1/jpn-urun/");
        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $products = json_decode($body, true);

        if (!is_array($products)) {
            return [];
        }

        // Save the data to the database
        foreach ($products as $product) {
            $wpdb->insert($table_name, [
                'sku' => $product['sku'],
                'title' => $product['title'],
                'thumbnail' => $product['thumbnail'],
                'indirme_sayaci' => $product['indirme_sayaci'],
                'veri_tipi' => $product['veri_tipi'],
                'short_description' => $product['short_description'],
                'permalink' => $product['permalink']
            ]);
        }

        // Return the data from the database
        return $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
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
        $table_name = $wpdb->prefix . 'jpn';

        if (isset($_POST['remove_activation']) && $_POST['remove_activation'] === '1') {
            $this->remove_activation_code();
            return ["success" => true, "message" => "Aktivasyon kaldırıldı."];
        }

        if (!isset($_POST['aktivasyon_kodu'])) {
            return null;
        }

        $aktivasyon_kodu = sanitize_text_field($_POST['aktivasyon_kodu']);

        // Check if the activation code exists in the database
        $existing_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE aktivasyon_kodu = %s", $aktivasyon_kodu), ARRAY_A);

        if ($existing_data) {
            // If the activation code exists, return the data from the database
            $data = json_decode($existing_data['purchased_products'], true);
            return ["success" => true, "data" => $data];
        } else {
            // If the activation code does not exist, proceed with the API call
            $response = wp_remote_get("https://japonadam.com/wp-json/mylisans/v1/api/?activation_key={$aktivasyon_kodu}");

            if (is_wp_error($response)) {
                return ["success" => false, "message" => $response->get_error_message()];
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!isset($data['valid']) || !$data['valid']) {
                return $data;
            }

            // Store the activation code and data in the database
            $wpdb->insert($table_name, [
                'aktivasyon_kodu' => $aktivasyon_kodu,
                'purchased_products' => wp_json_encode($data['purchased_products'])
            ]);

            return ["success" => true, "data" => $data['purchased_products']];
        }

        // // $this->check_and_insert_activation_code(
        // //     $aktivasyon_kodu,
        // //     isset($data['purchased_products']) ? $data['purchased_products'] : []
        // // );

        // return $data;
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
        $response = wp_remote_get("https://japonadam.com/wp-json/mylisans/v1/check-activation-status/?site_url={$site_url}&product_id={$productid}&activation_key={$activation_key}");
        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return $data['success'];
    }
    public function get_remaining_downloads($activation_key, $product_skus) {
        $api_url = 'https://japonadam.com/wp-json/mylisans/v1/remaining-downloads';
        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'body' => array(
                'activation_key' => $activation_key,
                'product_skus' => $product_skus // Expecting an array of SKUs
            )
        ));

        if (is_wp_error($response)) {
            error_log("Hata: " . $response->get_error_message());
            return 'Bilgi alınamadı';
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (wp_remote_retrieve_response_code($response) == 200 && isset($data['remaining_downloads'])) {
                return $data['remaining_downloads'];
            } else {
                error_log("Hata: " . $body);
                return 'Bilgi alınamadı';
            }
        }
    }
    

    public function refresh_purchased_products() {
        $aktivasyon_kodu = $this->aktivasyon_kodu_getir();

        if ($aktivasyon_kodu) {
            $response = wp_remote_get("https://japonadam.com/wp-json/mylisans/v1/api/?activation_key={$aktivasyon_kodu}");
            abonelik_kontrol($aktivasyon_kodu,true);

            if (is_wp_error($response)) {
                wp_send_json_error('API isteği başarısız oldu.');
            } else {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                // Satın alınan ürünleri veritabanında güncelle
                global $wpdb;
                $table_name = $wpdb->prefix . 'jpn';

                $wpdb->update(
                    $table_name,
                    ['purchased_products' => wp_json_encode($data['purchased_products'])],
                    ['aktivasyon_kodu' => $aktivasyon_kodu]
                );

                wp_send_json_success();
            }
        } else {
            wp_send_json_error('Aktivasyon kodu bulunamadı.');
        }
    }
    public function get_purchase_site($activation_code) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jpn_purchase_sites';

        // Check if the table exists, and create it if it doesn't
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                activation_code varchar(255) NOT NULL,
                purchase_site varchar(255) NOT NULL,
                PRIMARY KEY (id)
            ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        // Check if the activation code exists in the database
        $purchase_site = $wpdb->get_var($wpdb->prepare("SELECT purchase_site FROM $table_name WHERE activation_code = %s", $activation_code));
        update_option('purchased_site', $purchase_site); 

        if ($purchase_site) {
            return $purchase_site;
        }

        // If not, fetch from the API
        $url = "https://japonadam.com/wp-json/mylisans/v1/get-purchase-site";
        $response = wp_remote_get($url, array('timeout' => 15, 'body' => array('activation_code' => $activation_code)));

        if (is_wp_error($response)) {
            error_log("Hata: " . $response->get_error_message());
            return null;
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (wp_remote_retrieve_response_code($response) == 200) {
                $purchase_site = $data['satin_alinan_site'];
                error_log("Satın Alınan Site: " . $purchase_site);

                // Save the result to the database
                $wpdb->insert($table_name, [
                    'activation_code' => $activation_code,
                    'purchase_site' => $purchase_site
                ]);
                update_option('purchased_site', $purchase_site); // Store the purchased site information

                return $purchase_site;
            } else {
                error_log("Hata: " . $body);
                return null;
            }
        }
    }

    public function display_jp_tut_page() {
        $all_products = $this->fetch_lisans_products();
        $result = $this->verify_activation_code();
        $aktivasyon_kodu = $this->aktivasyon_kodu_getir();
        $isActivated = $this->is_activation_code_exists();
        $inputValue = $isActivated ? '•••••••••••••••••' : '';
        $buttonValue = $isActivated ? __('Aktivasyonu Kaldır', 'japonadam') : __('Doğrula', 'japonadam');
        $buttonColor = $isActivated ? '#bc2626' : '#1cbcff';
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'purchased';
        // Satın alınan ürünlerin productid'lerini al
        $purchased_product_ids = $this->fetch_purchased_products();
        // içindeki sadece ürün idlerini ver 
        $sadece_urun_idleri = array_keys($purchased_product_ids);

        if (!is_array($purchased_product_ids)) {
            $purchased_product_ids = [];
        }

        $filtered_products = array_filter($all_products, function($product) use ($purchased_product_ids) {
            return in_array($product['sku'], array_keys($purchased_product_ids));
        });

        $products = $filtered_products;
        $satin_alinan_site = $this->get_purchase_site($aktivasyon_kodu);
        $satin_alinan_site_destek = "https://japonadam.com/destek/";


        #products içindeki permalinklerdeki domaini satın alınan site ile değiştir
        $products = array_map(function($product) use ($satin_alinan_site) {
            $product['permalink'] = str_replace('https://japonadam.com', $satin_alinan_site, $product['permalink']);
            return $product;
        }, $products);
        // sadece ilk 5 rn
        // $products = array_slice($products, 0, 5);

        $warnings = [];
        $upload_limit = wp_max_upload_size() / (1024 * 1024); // Convert to MB
        if ($upload_limit < 100) {
            $warnings[] = __('Upload limitiniz 100 MB\'den düşük. Lütfen sunucu ayarlarınızı güncelleyin.', 'japonadam');
        }

        // Check if permalink structure is not "Post Name"
        if (get_option('permalink_structure') !== '/%postname%/') {
            $warnings[] = __('Yanlış yapılandırma! Ayarlar > Kalıcı Bağlantılar > Yazı Adı olarak değiştiriniz.', 'japonadam');
        }

        $remaining_downloads = $this->get_remaining_downloads($aktivasyon_kodu, $sadece_urun_idleri);
        ?>

        <!-- Ana konteyner -->
        <div class="jp-tut rounded-2xl m-12" style="background-color: #262626;"> 

            <!-- Üst navigasyon bölümü -->
            <div class="jp-nav flex justify-between items-center p-6 rounded-t-2xl" style="background:#1b1b1b;">

                <!-- Logo bölümü -->
                <div class="jp-logo w-12 h-12">
                    <img src="https://japonadam.com/wp-content/uploads/2023/10/japonadam-logo.png" style="border: 0;">
                </div>

                <!-- Ürün kategorileri menüsü -->
                <div class="jp-tab-menu flex items-center text-white text-lg">
                    <ul class="flex gap-5 list-none p-0 m-0 items-center">
                        <li class="py-2 px-4 rounded cursor-pointer transition-colors duration-300">
                            <a href="?page=japon-adam&tab=purchased" class="hover:text-blue-100"><?php _e('Satın Aldıklarım', 'japonadam'); ?></a>
                        </li>

                        <div class="h-5 border-r-2 border-gray-600 mx-2"></div>
                        <li class="py-2 px-4 rounded cursor-pointer transition-colors duration-300">
                            <a class="hover:text-blue-100" href="https://japonadam.com/magaza/" target="_blank"><?php _e('Tüm Ürünler', 'japonadam'); ?></a>
                        </li>
                        <div class="h-5 border-r-2 border-gray-600 mx-2"></div>
                        <li class="py-2 px-4 rounded cursor-pointer transition-colors duration-300">
                            <a href="?page=japon-adam&tab=help-center" class="hover:text-blue-100"><?php _e('Yardım Merkezi', 'japonadam'); ?></a>
                        </li>
                        
                    </ul>
                </div>

                <!-- Aktivasyon kodu giriş formu -->
                <div class="jp-aktivasyon flex items-center ">
                    <form class="flex gap-2 w-full" method="post">
                        <?php if ($isActivated): ?>
                            <button type="button" id="refreshButton" class="py-2 px-4 border-0 text-sm text-white cursor-pointer rounded" style="background-color: #4CAF50;"><?php _e('Yenile', 'japonadam'); ?></button>
                        <?php endif; ?>
                        <input type="text" id="aktivasyonInput" name="aktivasyon_kodu" value="<?php echo esc_attr($inputValue); ?>" placeholder="<?php _e('Aktivasyon kodunuz', 'japonadam'); ?>" class="py-2 px-3 border-0 rounded text-sm flex-grow" <?php echo $isActivated ? 'readonly' : ''; ?>>
                        <input type="hidden" name="remove_activation" value="<?php echo $isActivated ? '1' : '0'; ?>">
                        <input type="submit" id="aktivasyonButton" value="<?php echo esc_attr($buttonValue); ?>" style="background-color: <?php echo esc_attr($buttonColor); ?>;" class="py-2 px-4 border-0 text-sm text-white cursor-pointer rounded">
                    </form>
                    <?php if ($warnings): ?>
                        <button id="importantNoticesButton" class="bg-orange-600 p-4 rounded-full blink">
                            <span class="dashicons dashicons-warning" style="color: #FFA500;"></span>
                        </button>
                    <?php endif; ?>

                    <style>
                        @keyframes blink {
                            0% { opacity: 1; }
                            50% { opacity: 0.5; }
                            100% { opacity: 1; }
                        }
                        .blink {
                            animation: blink 1s linear infinite;
                        }
                    </style>
                </div>
            </div>
<?php if ($tab === 'help-center'): ?>
    <div class="help-center flex">
        <div class="categories w-1/4 p-4 bg-gray-800 text-white">
            <ul>
                <?php
                $satin_alinan_site = $this->get_purchase_site($aktivasyon_kodu);
                $api_url = $satin_alinan_site . '/wp-json/sitekisitlama/v1/issues';
                $response = wp_remote_get($api_url);
                if (is_wp_error($response)) {
                    $response = wp_remote_get('https://japonadam.com/wp-json/sitekisitlama/v1/issues');
                }
                if (!is_wp_error($response)) {
                    $body = wp_remote_retrieve_body($response);
                    $issues = json_decode($body, true);
                    if (is_array($issues)) {
                        $categories = [];
                        foreach ($issues as $issue) {
                            $categories[$issue['category_name']][] = $issue;
                        }
                        foreach ($categories as $category_name => $category_issues): ?>
                            <li class="py-2 px-4 cursor-pointer" data-category="<?php echo esc_attr($category_name); ?>">
                                <?php echo esc_html($category_name); ?>
                            </li>
                        <?php endforeach;
                    }
                }
                ?>
            </ul>
        </div>
        <div class="issues w-3/4 p-4">
            <?php foreach ($categories as $category_name => $category_issues): ?>
                <div class="category-content hidden" data-category="<?php echo esc_attr($category_name); ?>">
                    <?php foreach ($category_issues as $issue): ?>
                        <div class="issue bg-white p-4 rounded shadow mb-4">
                            <div class="flex justify-between items-center">
                                <h3 class="text-xl font-bold"><?php echo esc_html($issue['issue_title']); ?></h3>
                                <button class="toggle-solution bg-blue-500 text-white px-2 py-1 rounded">+</button>
                            </div>
                            <div class="solution bg-gray-100 p-2 mt-2 rounded hidden">
                                <?php echo $issue['solution']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const categoryItems = document.querySelectorAll(".categories li");
            const contents = document.querySelectorAll(".category-content");
            const toggleButtons = document.querySelectorAll(".toggle-solution");

            categoryItems.forEach(item => {
                item.addEventListener("click", function() {
                    const category = this.getAttribute("data-category");

                    categoryItems.forEach(i => i.classList.remove("bg-gray-700"));
                    this.classList.add("bg-gray-700");

                    contents.forEach(content => {
                        if (content.getAttribute("data-category") === category) {
                            content.classList.remove("hidden");
                        } else {
                            content.classList.add("hidden");
                        }
                    });
                });
            });

            toggleButtons.forEach(button => {
                button.addEventListener("click", function() {
                    const solution = this.parentElement.nextElementSibling;
                    solution.classList.toggle("hidden");
                    this.textContent = solution.classList.contains("hidden") ? "+" : "-";
                });
            });

            // İlk kategoriyi ve içeriği varsayılan olarak göster
            if (categoryItems.length > 0) {
                categoryItems[0].click();
            }
        });
    </script>
<?php endif; ?>
<?php if ($tab === 'purchased'): ?>

            <!-- Ürün listesi bölümü -->
            <div class="jp-content p-12">
                <div class="jpn-product grid grid-cols-4 gap-5">
                    <?php if (!$isActivated): ?>
                    <div class="col-span-4 text-left text-white" style="background-color: #1b1b1b; border-radius: 10px; padding: 20px;">
                        <strong><?php _e('Ürünleri görmek için sağ üst köşeden aktivasyon anahatarınızı giriniz.', 'japonadam'); ?></strong>
                    </div>
                    <?php else: ?>
                    <?php foreach($products as $product): ?>
                        <!-- Ürün kartı -->
                        <div class="product flex flex-col justify-between border p-5 rounded-lg" style="border-color: #4d4d4d; background-color: #333333; border-radius: 10px;" data-productid="<?php echo esc_attr($product['sku']); ?>" data-purchased="<?php echo in_array($product['sku'], array_keys($purchased_product_ids)) ? 'true' : 'false'; ?>">
                            <!-- Ürün detayları -->
                            <div class="product-info flex-grow">
                                <h3 class="text-white text-xl mb-1"><?php echo esc_html($product['title']); ?></h3>
                                <img src="<?php echo esc_url($product['thumbnail']); ?>" alt="<?php echo esc_attr($product['title']); ?>" style="border-color: #404040;" class="w-full rounded-lg">
                                <div class="flex justify-between text-sm text-gray-400 mt-2 mb-1">
                                    <p>🔥 <?php echo esc_html($product['indirme_sayaci']); ?> 
                                    <p>#<?php echo esc_html(__($product['veri_tipi'], 'japonadam')); ?></p>
                                </div>
                                
                            </div>
                            <!-- Ürün işlem butonları -->
                            <div>
                                <button id="installplugin" class="bg-red-600 text-white border-0 rounded-lg py-2 text-md w-full" data-productid="<?php echo esc_attr($product['sku']); ?>" onclick="checkAndInstallPlugin(this,'<?php echo esc_attr($aktivasyon_kodu); ?>')" style="background-color: #1CBCFF;"><?php _e('Kurulum Yap', 'japonadam'); ?></button>
                            </div>
                            <div class="indirme-hakki mt-2 text-center">
                                <?php
                                // Örnek olarak her rne 5 indirme hakkı atadık, gerçek veriyi rn bilgilerinizden çekmelisiniz.
                                $limit_sayisi = $remaining_downloads[$product['sku']];
                                echo "<span class='text-white'>" .'Limit:' . " </span><span class='text-green-400'>" . esc_html($limit_sayisi) . "</span>";

                                ?>
                            </div>
                        <!-- <div class="flex justify-center">
                            <div  class="bg-green-500 text-white rounded-lg px-4 py-2">01</div>
                        </div> -->
                        </div>

                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <!-- Kurulum durumu popup bilgisi -->
                <div id="japonloadingPopup" class="fixed inset-0 flex items-center justify-center z-999 bg-black bg-opacity-50 hidden" style="display: none;">
                    <div class="bg-white p-5 rounded-lg">
                        <p class="text-center mb-3"><?php _e('Kurulum yapılıyor...', 'japonadam'); ?></p>
                        <div class="h-4 bg-gray-300 rounded-full">
                            <div id="japonprogressBar" class="h-4 rounded-full" style="background-color: #1CBCFF; width: 0;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sayfa alt bilgi bölümü -->
            <div class="jp-footer flex justify-center items-center p-4 text-white">
                <div class="jp-destek mr-4">
                    <a href="<?php echo esc_url($satin_alinan_site_destek); ?>" target="_blank"><?php _e('Destek', 'japonadam'); ?></a>
                </div>
                <div class="jp-sitemiz">
                    <a href="<?php echo esc_url($satin_alinan_site); ?>" target="_blank"><?php _e('Sitemizi Ziyaret Edin', 'japonadam'); ?></a>
                </div>
            </div>

            <!-- Önemli Uyarılar Modal -->
            <div id="importantNoticesModal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
                <div class="bg-black p-6 rounded-lg w-1/2">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-white"><?php _e('Önemli Uyarılar', 'japonadam'); ?></h2>
                        <button id="closeModalButton" class="text-white">&times;</button>
                    </div>
                    <div>
                        <ul class="list-disc ml-5 text-orange-500 font-bold">
                            <?php foreach ($warnings as $warning): ?>
                                <li style="color: #FFA500;"><?php echo esc_html($warning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var button = document.getElementById('importantNoticesButton');
                    if (button) {
                        button.addEventListener('click', function() {
                            this.classList.remove('blink');
                        });
                    }
                });
            </script>
            <script>
                document.getElementById('importantNoticesButton').addEventListener('click', function() {
                    document.getElementById('importantNoticesModal').classList.remove('hidden');
                });

                document.getElementById('closeModalButton').addEventListener('click', function() {
                    document.getElementById('importantNoticesModal').classList.add('hidden');
                });
            </script>
        </div>
        </div>
    <?php endif; ?>

        <?php
    }
}



// Eklenti çalıştırılırken sınıfın bir rneğini oluşturma
$aktivasyon = new JaponAdamAktivasyon();
// fetch_lisans_products fonksiyonunu çalıştır
register_activation_hook(__FILE__, [$aktivasyon, 'fetch_lisans_products']);

function envato_activation_hook() {
    $mu_plugins_dir = WPMU_PLUGIN_DIR;
    if (!file_exists($mu_plugins_dir)) {
        wp_mkdir_p($mu_plugins_dir);
    }

    $source_file = plugin_dir_path(__FILE__) . 'envato.php';
    $destination_file = $mu_plugins_dir . '/envato.php';

    if (file_exists($source_file)) {
        if (!copy($source_file, $destination_file)) {
            error_log('Envato.php dosyası mu-plugins klasörüne kopyalanamadı.');
        }
    } else {
        error_log('Kaynak envato.php dosyası bulunamadı.');
    }
}

register_activation_hook(__FILE__, 'envato_activation_hook');

add_action('rest_api_init', function () {
    register_rest_route('mylisans/v1', '/install-plugin/', array(
        'methods' => 'GET',
        'callback' => 'install_plugin_endpoint_callback',
        'permission_callback' => '__return_true'
    ));
});

function xor_decrypt($input, $key) {
    $input = base64_decode($input);
    $output = '';
    for ($i = 0; $i < strlen($input); $i++) {
        $output .= chr(ord($input[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return $output;
}

function install_plugin_endpoint_callback($request) {
    $download_links = $request->get_param('product_name');
    $aktivasyon_kodu = $request->get_param('aktivasyon_kodu');
    $product_id = $request->get_param('product_id');
    $download_links = xor_decrypt($download_links, 'japonadamsifre');

    // Linklere product_id ve aktivasyon_kodu parametrelerini ekleyin
    $links_with_params = array_map(function($link) use ($product_id, $aktivasyon_kodu) {
        return add_query_arg(['sku' => $product_id, 'aktivasyon_kodu' => $aktivasyon_kodu], $link);
    }, explode(',', $download_links));
    // Parametre eklenmiş linkleri virgülle birleştir
    $download_links_with_params = implode(',', $links_with_params);

    return install_plugin_or_theme($download_links_with_params, $aktivasyon_kodu);
}

// function log_kaydet($message) {
//     $log_file = plugin_dir_path(__FILE__) . 'log.txt';
//     $timestamp = date('[Y-m-d H:i:s] ');
//     $log_message = $timestamp . $message . PHP_EOL;
//     file_put_contents($log_file, $log_message, FILE_APPEND);
// }

function abonelik_kontrol($activation_code,$zaman_deaktif = false) {
    $last_check_time = get_option('last_membership_check_time');
    $current_time = time();

    if (!$zaman_deaktif) {
        if ($last_check_time && ($current_time - $last_check_time) < 3600) {
            return get_option('membership_status');
        }
    }

    $purchased_site = get_option('purchased_site');

    $response = wp_remote_get("{$purchased_site}/wp-json/membership/v1/check-membership?activation_code={$activation_code}");

    if (is_wp_error($response)) {
        return ['exists' => false, 'has_active_membership' => false, 'message' => 'API request failed.'];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Store the membership status and the current time in the options table
    update_option('membership_status', $data);
    update_option('last_membership_check_time', $current_time);

    return $data;
}

function install_plugin_or_theme($download_links, $aktivasyon_kodu) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
    require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    require_once(ABSPATH . 'wp-admin/includes/theme.php');

    $purchased_site = get_option('purchased_site');

    // SKU kontrolü ve abonelik kontrolü
    // global $wpdb;
    // $table_name = $wpdb->prefix . 'jpn_products';
    // $subscription_skus = ['999', '998', '997'];
    // $product_skus = $wpdb->get_col("SELECT sku FROM $table_name");

    // if ($purchased_site === 'https://wptokyo.com' || $purchased_site === 'https://japonadam.com' || array_intersect($subscription_skus, $product_skus)) {
    //     $membership_status = abonelik_kontrol($aktivasyon_kodu);
    //     if (!$membership_status['has_active_membership']) {
    //         return [
    //             'success' => false,
    //             'message' => 'You do not have an active membership.'
    //         ];
    //     }
    // }
    

    $links = explode(',', $download_links);
    foreach ($links as $link) {
        $link = trim($link);
        $item_slug = basename($link, '.zip');
        $item_slug = explode('.zip', $item_slug)[0];

        if (strpos($link, 'theme') !== false) {
            // Tema için eski versiyonu silme
            $all_themes = wp_get_themes();
            foreach ($all_themes as $theme_slug => $theme) {
                if (strpos($theme_slug, $item_slug) === 0) {
                    $active_theme = wp_get_theme();
                    if ($active_theme->get_stylesheet() == $theme_slug) {
                        switch_theme(WP_DEFAULT_THEME);
                    }
                    delete_theme($theme_slug);
                    break;
                }
            }

            // Yeni temayı kurma
            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Theme_Upgrader($skin);
            $installed = $upgrader->install($link);
            if (!$installed || is_wp_error($installed)) {
                return array(
                    'success' => false,
                    'message' => 'Tema kurulamadı: ' . $skin->get_errors()->get_error_message()
                );
            }
        } else {
            // Eklenti için eski versiyonu silme
            $all_plugins = get_plugins();
            foreach ($all_plugins as $plugin_path => $plugin_data) {
                if (strpos($plugin_path, $item_slug) === 0) {
                    deactivate_plugins($plugin_path);
                    delete_plugins(array($plugin_path));
                    break;
                }
            }

            // Yeni eklentiyi kurma ve aktifleştirme
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


function japonadam_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=japon-adam') . '">Ayarlar</a>';
    array_unshift($links, $settings_link);
    return $links;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'japonadam_plugin_action_links');
function custom_footer_style() { echo '<style> #wpfooter { position: relative !important; } </style>'; } add_action('admin_head', 'custom_footer_style');