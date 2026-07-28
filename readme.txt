=== Paper.id Payment Gateway for WooCommerce ===
Contributors: joe
Tags: woocommerce, payment gateway, paper.id, credit card, qris, indonesia
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.7
License: GPLv2 or later

Integrasi resmi & mudah untuk menerima pembayaran Kartu Kredit, QRIS, Virtual Account, dan E-Wallet melalui Paper.id di toko WooCommerce Anda.

== Description ==

Plugin ini mengintegrasikan layanan **Paper.id Open API** ke dalam WooCommerce. Dengan plugin ini, toko online Anda dapat secara otomatis memproses dan mengonfirmasi pembayaran dari pelanggan.

= Fitur Utama =
* **Dukungan Kartu Kredit**: Terima Visa, Mastercard, dan JCB via Paper.id.
* **Metode Pembayaran Lengkap**: QRIS, Virtual Account (BCA, Mandiri, BRI, BNI, Permata, dll), dan E-Wallet.
* **Konfirmasi Otomatis (Webhook)**: Status pesanan di WooCommerce akan terupdate secara real-time saat pembayaran berhasil.
* **Dukungan Sandbox & Live**: Uji coba integrasi di lingkungan Sandbox sebelum masuk ke Production.
* **Logging Detail**: Memudahkan troubleshooting dan pemantauan transaksi.

== Installation ==

1. Unduh file `.zip` plugin atau salin folder `paper_plugin` ke `/wp-content/plugins/`.
2. Masuk ke Dashboard WordPress > **Plugins** > **Add New** > **Upload Plugin**.
3. Aktifkan **Paper.id Payment Gateway for WooCommerce**.
4. Buka **WooCommerce** > **Settings** > **Payments** > **Paper.id Payment Gateway**.
5. Salin **Callback / Webhook URL** yang tertera dan mendaftarkannya pada dashboard Paper.id (**Settings > API Dashboard**).
6. Masukkan **Client ID** dan **Client Secret** Anda dari Paper.id.
7. Simpan perubahan.

== Changelog ==

= 1.0.0 =
* Rilis versi perdana plugin Paper.id Payment Gateway untuk WooCommerce.
