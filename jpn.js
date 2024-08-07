function checkAndInstallPlugin(buttonElement, aktivasyonKodu){
    var productContainer = buttonElement.closest('.product');
    var productID = productContainer.getAttribute('data-productid');
    var siteURL = window.location.hostname;  // Veya doğru site URL'sini buraya girin

    // XMLHttpRequest objesini oluştur
    var xhrCheck = new XMLHttpRequest();

    // Data objesini oluştur
    var data = {
        product_id: productID,
        activation_key: aktivasyonKodu,
        site_url: siteURL
    };

    // JSON formatına dönüştür
    var jsonData = JSON.stringify(data);

    // İsteği ayarla
    xhrCheck.open('POST', 'https://japonadam.com/wp-json/mylisans/v1/check-activation/', true);
    xhrCheck.setRequestHeader('Content-Type', 'application/json; charset=UTF-8');

    // Yanıtı işle
    xhrCheck.onreadystatechange = function () {
        if (xhrCheck.readyState == 4 && xhrCheck.status == 200) {
            var response = JSON.parse(xhrCheck.responseText);
            if (response.valid) {
                // Eğer check_activation true dönerse, getDownloadLink fonksiyonunu çağır
                getDownloadLink(productID, function(download_link) {
                    installPlugin(buttonElement, aktivasyonKodu, download_link);
                });
            } else {
                alert('Aktivasyon başarısız: ' + response.error);
            }
        }
    };

    // İsteği gönder
    xhrCheck.send(jsonData);
}

function xor_decrypt(input, key) {
    input = atob(input);
    var output = '';
    for (var i = 0; i < input.length; i++) {
        output += String.fromCharCode(input.charCodeAt(i) ^ key.charCodeAt(i % key.length));
    }
    return output;
}

function getDownloadLink(productID, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'https://japonadam.com/wp-json/mylisans/v1/get-download-link/' + productID, true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                var key = 'japonadamsifre'; // Bu anahtarı hem sunucuda hem de istemcide aynı tutun
                var decrypted_link = xor_decrypt(response.product_name, key);
                callback(response.product_name);
            } else {
                alert('Download link could not be retrieved: ' + response.message);
            }
        }
    };
    xhr.send();
}

// Mevcut installPlugin fonksiyonu
function installPlugin(buttonElement, aktivasyonKodu,indirmeLinki){
    
    var productContainer = buttonElement.closest('.product');
    var isPurchased = productContainer.getAttribute('data-purchased') === 'true';

    if (!isPurchased) {
        alert(translations.notPurchased);
        return;
    }

    var productID = productContainer.getAttribute('data-productid');

    // Yükleme barını göster
    document.getElementById('japonloadingPopup').classList.remove('hidden');
    document.getElementById('japonloadingPopup').style.display = "";
    var japonprogressBar = document.getElementById('japonprogressBar');
    var progressWidth = 0;
    // AJAX isteği oluşturalım:
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/wp-json/mylisans/v1/install-plugin/?product_id=' + productID + '&aktivasyon_kodu=' + aktivasyonKodu+  '&product_name=' + indirmeLinki, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) { // AJAX isteği tamamlandığında
            clearInterval(interval); // Intervalı sonlandır.
            document.getElementById('japonloadingPopup').classList.add('hidden'); // "Kurulum devam ediyor..." mesajını gizle.
            document.getElementById('japonloadingPopup').style.display = "none";
            japonprogressBar.style.widthtranslation_array = '0%'; // Yükleme barını sıfırla.

            if (xhr.status == 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    japonprogressBar.style.width = '100%';
                    alert(translations.pluginInstalled);
                    console.log(response);
                    buttonElement.textContent = translations.productInstalled;;
                    buttonElement.style.backgroundColor = "green";
                    buttonElement.style.pointerEvents = "none";
                    addProductToInstalledList(productID);
                } else if (response.message === 'Eklenti kurulamadı: Daha önce zaten kurulmuş.') {
                    buttonElement.textContent = translations.pluginAlreadyInstalled;;
                    buttonElement.style.backgroundColor = "green";
                    buttonElement.style.pointerEvents = "none";
                } else if (response.message.indexOf('Destination folder already exists.') !== -1) {
                    alert(translations.pluginExists);
                } else {
                    alert(response.message);
                }
            } else {
                alert(translations.errorOccurred);
            }
        }
    };
    // Yükleme barının süresini artır
    var interval = setInterval(function() {
        progressWidth += 33; 
        japonprogressBar.style.width = progressWidth + '%';
        if(progressWidth >= 90) clearInterval(interval);
    }, 500);

    xhr.send();}


    document.addEventListener('DOMContentLoaded', function() {
    var refreshButton = document.getElementById('refreshButton');
    if (refreshButton) {
        refreshButton.addEventListener('click', function() {
            var aktivasyonKodu = document.getElementById('aktivasyonInput').value;
            refreshPurchasedProducts(aktivasyonKodu);
        });
    }
});

function refreshPurchasedProducts(aktivasyonKodu) {
    // AJAX isteği göndererek satın alınan ürünleri güncelleyin
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                // Başarılı yanıt alındığında sayfayı yenileyin
                location.reload();
            } else {
                console.error('Satın alınan ürünler güncellenirken bir hata oluştu.');
            }
        }
    };
    xhr.open('POST', ajaxurl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
    xhr.send('action=refresh_purchased_products&aktivasyon_kodu=' + encodeURIComponent(aktivasyonKodu));
}

document.addEventListener('DOMContentLoaded', function() {
    var refreshButton = document.getElementById('refreshButton');
    if (refreshButton) {
        refreshButton.addEventListener('click', function() {
            var aktivasyonKodu = document.getElementById('aktivasyonInput').value;
            refreshPurchasedProducts(aktivasyonKodu);
        });
    }
});

function refreshPurchasedProducts(aktivasyonKodu) {
    // AJAX isteği göndererek satın alınan ürünleri güncelleyin
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                // Başarılı yanıt alındığında sayfayı yenileyin
                location.reload();
            } else {
                alert('Satın alınan rnler gncellenirken bir hata oluştu.');
            }
        }
    };
    xhr.open('POST', ajaxurl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
    xhr.send('action=refresh_purchased_products&aktivasyon_kodu=' + encodeURIComponent(aktivasyonKodu));
}