# Anonymous Pastebin Clone

A self-hosted, anonymity-respecting Pastebin clone written in PHP and MySQL with optional account features (authored pastes, likes, profile pages) layered on top of a fully anonymous fast path.

[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql)](https://www.mysql.com)
[![Charts](https://img.shields.io/badge/Charts-Chart.js-FF6384?logo=chart.js)](https://www.chartjs.org)
[![Use](https://img.shields.io/badge/Use-Educational-blue)](#license)

---

## English

### ◆ Overview

The application offers the core Pastebin experience — write, share, view raw, search — and layers an optional account system on top for users who want a profile, "pinned" pastes, and a like/dislike feed. Pastes can be marked public, unlisted, or private with a per-paste password hash. A lightweight admin area handles banner copy, role management, and abuse tooling.

### ⚡ Feature Map

| Area | Features |
|---|---|
| ▣ Pastes | Create, edit, delete, raw view, syntax-tagged display, expiration, view counter |
| ▣ Visibility | Public, unlisted (link-only), private with per-paste password |
| ▣ Discovery | Recent feed, "top" feed, search, paginated listings |
| ▣ Account | Registration with verified password, role-based permissions, profile page, pinned paste, likes |
| ▣ Moderation | Admin panel, role flags, ban field, configurable banner texts |
| ▣ Analytics | Daily users / pastes / views / likes dashboard with Chart.js |

### ▣ Tech Stack

| Layer | Technology |
|---|---|
| Runtime | PHP 8 |
| Database | MySQL |
| UI | Server-rendered PHP templates + Tailwind via CDN, Chart.js for analytics |
| Helpers | Password hashing, per-paste salt, prepared statements throughout |

### ▢ Project Layout

```
index.php             Home / recent feed
create.php            New paste form + insert
view.php              Single paste view
raw.php               Raw text endpoint
search.php            Title / content search
top.php / recent.php  Discovery feeds
user.php / profile.php Public user pages
dashboard.php         Platform-wide statistics
admin/                Admin tooling
api/                  Lightweight JSON endpoints
ajax/                 In-page async endpoints
includes/             Header, footer, helpers
data/                 Static data (languages, categories)
sql/, sql_scheme/     Schema and migration helpers
```

### ▶ Getting Started

1. ► Create a MySQL database and import the SQL files in `sql_scheme/`.
2. ► Copy `config.example.php` to `config.php` and fill in DB credentials.
3. ► Serve the directory with Apache, nginx + PHP-FPM, or `php -S` locally.

### ⓘ Notes on Anonymity

- ► The default fast path does not require an account
- ► IP addresses are not stored alongside anonymous pastes by default
- ► The admin panel is gated by role flags stored in the users table

---

## Türkçe

### ◆ Genel Bakış

Uygulama, Pastebin'in temel deneyimini sunar — yazma, paylaşma, raw görüntüleme, arama — ve profil, sabitlenmiş paste ve beğeni akışı isteyen kullanıcılar için bunun üzerine isteğe bağlı bir hesap sistemi ekler. Paste'ler genel, listelenmemiş veya paste başına parola hash'i ile özel olabilir. Hafif bir yönetici alanı; banner metni, rol yönetimi ve kötüye kullanım araçlarını barındırır.

### ⚡ Özellik Haritası

İngilizce bölümle aynıdır.

### ▣ Teknoloji Yığını

PHP 8 + MySQL, sunucu tarafında oluşturulan şablonlar, hazırlıklı sorgular, paste başına salt'lı şifre saklama ve Chart.js ile analitik panosu.

### ▶ Kurulum

1. ► Bir MySQL veritabanı oluştur ve `sql_scheme/` içindeki SQL dosyalarını içe aktar.
2. ► `config.example.php` dosyasını `config.php` olarak kopyalayıp DB bilgilerini doldur.
3. ► Dizini Apache, nginx + PHP-FPM ile veya yerel olarak `php -S` ile sun.

### ⓘ Anonimlik Notları

- ► Varsayılan hızlı yol bir hesap gerektirmez
- ► Anonim paste'lerle birlikte IP adresleri varsayılan olarak saklanmaz
- ► Yönetici paneli, users tablosunda saklanan rol bayraklarıyla korunur

---

## License

Released for educational and portfolio review purposes only.
