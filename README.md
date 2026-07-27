# 🚀 Paper.id Payment Gateway for WooCommerce

<div align="center">

![Paper.id Logo](assets/images/paper-logo.svg)

### **Solusi Payment Gateway Serbaguna & Otomatis untuk WooCommerce**

 Accept Credit Cards, QRIS, Virtual Accounts, & E-Wallets directly on your WooCommerce Store via Paper.id Open API.

[![WooCommerce](https://img.shields.io/badge/WooCommerce-v5.0%2B-purple.svg?style=flat-square&logo=woocommerce)](https://woocommerce.com/)
[![PHP Version](https://img.shields.io/badge/PHP-v7.4%2B-blue.svg?style=flat-square&logo=php)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green.svg?style=flat-square)](LICENSE)
[![GitHub Release](https://img.shields.io/github/v/release/TxNixon/paperid-woocommerce-payment-gateway?style=flat-square&color=orange)](https://github.com/TxNixon/paperid-woocommerce-payment-gateway/releases)
[![Auto Update](https://img.shields.io/badge/Auto--Update-Supported-brightgreen.svg?style=flat-square&logo=github)](https://github.com/TxNixon/paperid-woocommerce-payment-gateway)

---

</div>

## 🌟 Mengapa Memilih Plugin Ini?

Plugin **Paper.id Payment Gateway for WooCommerce** dirancang khusus untuk pebisnis online dan merchant di Indonesia yang menginginkan sistem pembayaran otomatis, aman, dan tanpa repot. Terintegrasi penuh dengan sistem Paper.id untuk mempercepat proses checkout dan rekonsiliasi pembayaran toko Anda.

---

## ✨ Fitur Unggulan

| Fitur | Deskripsi |
| :--- | :--- |
| 💳 **Metode Pembayaran Lengkap** | Menerima **Kartu Kredit** (Visa/Mastercard), **QRIS** (Gopay, OVO, ShopeePay, Dana, LinkAja, BCA, dll), **Virtual Account** (BCA, Mandiri, BRI, BNI, Permata), dan **E-Wallet**. |
| ⚡ **Verifikasi Otomatis (Real-time Webhook)** | Pesanan pelanggan langsung terkonfirmasi secara *real-time* begitu pembayaran berhasil tanpa perlu konfirmasi manual. |
| 🧪 **Sandbox & Production Mode** | Dilengkapi fitur pengujian (*Sandbox*) untuk uji coba transaksi sebelum beralih ke transaksi live (*Production*). |
| 🔄 **1-Click GitHub Auto-Updater** | **Update Otomatis!** Pelanggan dapat memperbarui plugin langsung dari Dashboard WordPress cukup dengan 1 klik saat Anda merilis versi baru di GitHub. |
| 📝 **Log Debugging Lengkap** | Memudahkan pemantauan log transaksi langsung dari `WooCommerce > Status > Logs`. |
| ⚡ **HPOS Compliant** | Kompatibel penuh dengan WooCommerce High-Performance Order Storage (HPOS) versi terbaru. |

---

## 💳 Metode Pembayaran Yang Didukung

```
                        ┌──────────────────────────────────────────┐
                        │   Paper.id WooCommerce Payment Gateway   │
                        └────────────────────┬─────────────────────┘
                                             │
      ┌──────────────────┬───────────────────┼───────────────────┬──────────────────┐
      ▼                  ▼                   ▼                   ▼                  ▼
  💳 Credit Card      📱 QRIS             🏦 Virtual Acc       👛 E-Wallet         🏛 PayLater
 Visa / Mastercard  Semua Bank & E-Wallet BCA, Mandiri, BRI, BNI  GoPay, OVO, Shopee  Paper Usaha
```

---

## 🛠️ Persyaratan Sistem

- **WordPress**: 5.6 atau lebih baru
- **WooCommerce**: 5.0 atau lebih baru
- **PHP**: 7.4 atau lebih baru (Rekomendasi PHP 8.1+)
- **cURL Extension**: Aktif pada server hosting
- **Akun Paper.id**: Terdaftar dan memiliki akses *Open API / Payment Gateway*

---

## 📦 Panduan Instalasi (Untuk Merchant / Client)

1. **Download Plugin**:
   - Download file `.zip` versi terbaru dari [GitHub Releases](https://github.com/TxNixon/paperid-woocommerce-payment-gateway/releases).
2. **Upload ke WordPress**:
   - Masuk ke Admin Dashboard WordPress Anda (`wp-admin`).
   - Buka menu **Plugins > Add New > Upload Plugin**.
   - Pilih file `paper-id-woocommerce.zip` lalu klik **Install Now** dan **Activate**.
3. **Konfigurasi Pengaturan**:
   - Buka menu **WooCommerce > Settings > Payments**.
   - Klik **Manage** / **Kelola** pada pembayaran **Paper.id Payment Gateway**.
   - Masukkan **Client ID** & **Client Secret** dari akun Paper.id Anda.
   - Salin **Callback / Webhook URL** yang tertera dan daftarkan ke Dashboard API Paper.id Anda.
   - Simpan Pengaturan (*Save Changes*).

---

## 🔄 Sistem Auto-Update Otomatis

Plugin ini dilengkapi fitur **GitHub Auto-Update Engine**. Ketika ada pembaruan versi baru dari pengembang:

1. Notifikasi update akan muncul secara otomatis di menu **Plugins** admin WordPress toko Anda.
2. Anda cukup mengklik **"Update Now"** untuk mendapatkan fitur & perbaikan terbaru tanpa perlu mengunduh ulang file zip secara manual.

---

## ⚙️ Tangkapan Layar & Alur Transaksi

```
[ Pelanggan Checkout ] ──► [ Pilih Paper.id ] ──► [ Redirect Halaman Pembayaran ]
                                                              │
                                                      (Pembayaran Sukses)
                                                              │
[ Status WooCommerce "Processing" ] ◄── [ Callback Webhook Instant ] ◄┘
```

---

## 🛡️ Lisensi & Hak Cipta

Hak Cipta © 2026. Diterbitkan di bawah lisensi [GNU General Public License v2.0](LICENSE).  
Logo dan Merk Dagang Paper.id adalah milik resmi **PT Pakar Digital Mediatama (Paper.id)**.

---

<div align="center">
  <sub>Built with ❤️ for Indonesian Merchants & WooCommerce Entrepreneurs.</sub>
</div>
