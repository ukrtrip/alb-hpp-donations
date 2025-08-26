# ALB HPP Donations

Плагін WordPress для прийому **благодійних внесків** через **Hosted Payment Page** Альянс Банку. Містить шорткод `[alb_donate]`, UI адмінки, авторизацію пристрою, безпечне керування токеном, крон-задачі для синхронізації платежів та авто-переавторизації.

---

## ✨ Можливості

- Інтеграція з **Alliance Bank HPP** (створення платежу, редірект, нотифікація).
- **Керування токеном** (строк дії 24 год): щогодинний «сторож» оновлює токен, якщо залишилось ≤ 12 год.
- **Синхронізація відкритих платежів** кожні 10 хвилин через WP-Cron.
- Адмін-сторінки: Налаштування, **Історія платежів** (з кнопкою **«Видалити всі платежі»**).
- REST-ендпоїнт для ручної синхронізації відкритих платежів (лише для адміністраторів).
- Логи (опціонально через `ALB_HPP_DEBUG`), дружні попередження про відсутність **GMP**.
- Коректний парсинг `tokenExpiration` (таймзона `Europe/Kyiv`).

---

## 🧩 Вимоги

- WordPress 6.0+
- PHP 8.0+ (тестовано на 8.1)
- Розширення PHP: GMP, HASH, OpenSSL (для SimpleJWT)
- Вихід в Інтернет до HPP API банку

---

## 📦 Встановлення

1. Завантажте папку плагіну до `/wp-content/plugins/` або встановіть ZIP через адмінку.
2. Активуйте **ALB HPP Donations** у **Плагіни → Встановлені**.
3. Перейдіть у **Налаштування → ALB HPP Donations** та заповніть:
   - Base URL / `serviceCode` / `merchantId`
   - `successUrl` / `failUrl` / `notificationUrl`
   - Режим декрипту (локальний SimpleJWT або віддалений API)
4. Пройдіть **авторизацію пристрою**.
5. (Опціонально) Встановіть **WP Crontrol** для перегляду планувальника.

---

## ⚙️ Крон

Плагін використовує WP-Cron (викликається трафіком або системним cron’ом).

- `alb_hpp_token_guard_cron`
- `alb_hpp_sync_cron`

> На проді рекомендовано системний cron, що викликає `wp-cron.php` кожні 5 хвилин.

---

## 🔌 REST API

- `alb/v1 /create-order` — alb-hpp-donations/alb-hpp-donations.php
- `alb/v1 /notify` — alb-hpp-donations/alb-hpp-donations.php
- `alb/v1 /sync-order` — alb-hpp-donations/alb-hpp-donations.php
- `alb/v1 /sync-pending` — alb-hpp-donations/alb-hpp-donations.php
- `alb/v1 /reauthorize-now` — alb-hpp-donations/alb-hpp-donations.php

Приклад: `POST /wp-json/alb/v1/sync-pending` → `{"ok": true, "synced": <n>}` (доступ лише адмінам).

---

## 🗃️ База даних

- `alb_hpp_payments`

> Імена таблиць мають префікс `$wpdb->prefix` (наприклад, `wp_`).

---

## 🧪 Усунення несправностей

- **`Call to undefined function gmp_init()`** — увімкніть/встановіть `ext-gmp` (`php-gmp`).
- **Час «мінус 3 години»** — у **Налаштуваннях WP** виставте таймзону `Europe/Kyiv` (уникайте `Etc/GMT+3`).
- **WP-Cron не спрацьовує** — додайте системний cron, що викликає `wp-cron.php`.
- **Авторизація пристрою не проходить** — перевірте ключі/мережу; увімкніть `ALB_HPP_DEBUG` (логи у `wp-content/uploads/alb-logs/`).


---


## 📜 Ліцензія

GPL-2.0-or-later (`LICENSE`).

---
