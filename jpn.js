function installPlugin(buttonElement,aktivasyonKodu) {
    var productContainer = buttonElement.closest('.product');
    var isPurchased = productContainer.getAttribute('data-purchased') === 'true';

    if (!isPurchased) {
        alert('Bu ürünü henüz satın almadınız.');
        return;
    }

    var productID = productContainer.getAttribute('data-productid');

    // Yükleme barını göster
    document.getElementById('loadingPopup').classList.remove('hidden');
    var progressBar = document.getElementById('progressBar');
    var progressWidth = 0;
    // AJAX isteği oluşturalım:
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/wp-json/mylisans/v1/install-plugin/?product_id=' + productID + '&aktivasyon_kodu=' + aktivasyonKodu, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) { // AJAX isteği tamamlandığında
            clearInterval(interval); // Intervalı sonlandır.
            document.getElementById('loadingPopup').classList.add('hidden'); // "Kurulum devam ediyor..." mesajını gizle.
            progressBar.style.width = '0%'; // Yükleme barını sıfırla.

            if (xhr.status == 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    progressBar.style.width = '100%';
                    alert("Eklenti başarıyla kuruldu!");
                    console.log(response);
                    buttonElement.textContent = "Bu eklenti sitenizde kurulu.";
                    buttonElement.style.backgroundColor = "green";
                    buttonElement.style.pointerEvents = "none";
                    addProductToInstalledList(productID);
                } else if (response.message === 'Eklenti kurulamadı: Daha önce zaten kurulmuş.') {
                    buttonElement.textContent = "Bu ürün sitenizde zaten kurulmuş.";
                    buttonElement.style.backgroundColor = "green";
                    buttonElement.style.pointerEvents = "none";
                } else if (response.message === 'Bu ürün için bir hakkınız yok!') {
                    alert(response.message);
                } else if (response.message === 'Bu ürün için tüm haklarınızı kullandınız!') {
                    alert(response.message);
                } else if (response.message === 'Bu ürün için bir hakkınız yok!') {
                    alert(response.message);
                } else {
                    alert(response.message);
                }
            } else {
                alert('Bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.');
            }
        }
    };
    // Yükleme barının süresini artır
    var interval = setInterval(function() {
        progressWidth += 33; 
        progressBar.style.width = progressWidth + '%';
        if(progressWidth >= 90) clearInterval(interval);
    }, 500);

    xhr.send();
}

function addProductToInstalledList(productID) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/wp-json/mylisans/v1/add-installed-product/?product_id=' + productID, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            var response = JSON.parse(xhr.responseText);
            if (!response.success) {
                console.error("Ürün installed_products'a eklenirken hata oluştu: ", response.message);
            }
        }
    };
    xhr.send();
}