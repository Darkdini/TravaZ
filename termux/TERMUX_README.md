# Запуск TravianZ на Termux (Android)

Инструкция, как поднять игру прямо на телефоне через [Termux](https://f-droid.org/packages/com.termux/) — без Docker и без root. Используется встроенный веб‑сервер PHP + MariaDB.

> Ставьте Termux из **F-Droid** (версия из Google Play устарела и не обновляет пакеты).

## 1. Подготовка Termux

```bash
pkg update -y && pkg upgrade -y
pkg install -y git
termux-setup-storage   # разрешите доступ к памяти, если попросит
```

## 2. Получить игру

```bash
git clone https://github.com/Darkdini/TravaZ.git
cd TravaZ
```

## 3. Установка (один раз)

```bash
bash termux/setup.sh
```

Скрипт сам:
- поставит `php` и `mariadb`;
- инициализирует хранилище базы данных;
- запустит MariaDB;
- создаст базу `travian` и пользователя `travianz` / `travianzpass`.

Значения можно переопределить переменными окружения:

```bash
DB_NAME=travian DB_USER=travianz DB_PASS=МойПароль bash termux/setup.sh
```

## 4. Запуск сервера

```bash
bash termux/start.sh
```

По умолчанию сервер слушает `0.0.0.0:8080`. Другой порт:

```bash
PORT=9000 bash termux/start.sh
```

## 5. Установка игры через мастер

Откройте в браузере телефона:

```
http://localhost:8080/install
```

В мастере укажите параметры базы данных:

| Поле        | Значение     |
|-------------|--------------|
| Host        | `127.0.0.1`  |
| Port        | `3306`       |
| Database    | `travian`    |
| User        | `travianz`   |
| Password    | `travianzpass` |
| Язык        | **Русский**  |

Пройдите шаги мастера до конца — игра создаст таблицы и мир.

## 6. Игра с других устройств в той же сети

Узнайте IP телефона (`ifconfig` или в настройках Wi‑Fi), затем на другом устройстве откройте `http://IP_ТЕЛЕФОНА:8080`.

## Полезное

- **Остановить сервер:** `Ctrl+C` в окне, где запущен `start.sh`.
- **Остановить MariaDB:** `mysqladmin -u root shutdown`
- **Логи базы:** `$PREFIX/tmp/mariadb.log`
- **Сбросить базу полностью:** остановите MariaDB и удалите `$PREFIX/var/lib/mysql`, затем снова запустите `setup.sh`.
- **Держать сервер живым при выключенном экране:** установите приложение и выполните `termux-wake-lock`.

## Почему так, а не Docker

Docker на Android/Termux без root не работает. Игре не нужен Apache: единственные правила `.htaccess` (запрет прямого доступа к `*.tpl`, `*.sql`, `*.log`) воспроизводит роутер `termux/router.php`. Он же выставляет рабочий каталог как это делает Apache, чтобы относительные `include` в коде работали без изменений.

## Требования к PHP

Игра использует расширения `mysqli`, `pdo_mysql`, `gd`, `zip` — все они входят в пакет `php` из репозитория Termux, ставить отдельно ничего не нужно.
