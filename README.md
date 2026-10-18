# savchenkotherapy-site

## Заявки из формы в Telegram

Форма записи отправляет данные на `api/lead.php`, а скрипт пересылает их в Telegram-бота.

### Первая настройка на сервере

1. Создать бота у @BotFather (`/newbot`) и сохранить токен. В @BotFather → `/setjoingroups` → Disable.
2. Написать своему боту `/start`, затем открыть `https://api.telegram.org/bot<ТОКЕН>/getUpdates` и взять число из `"chat":{"id":...}`.
3. На сервере создать файл `tg-config.php` **на уровень выше папки сайта** (если сайт лежит в `/var/www/site`, файл — `/var/www/tg-config.php`). Образец — `tg-config.example.php`:

   ```php
   <?php
   return [
     'token'   => '123456:ABC...',
     'chat_id' => '123456789',
   ];
   ```

   Права: `chmod 640 tg-config.php`, владелец — пользователь, от которого работает PHP. В репозиторий этот файл не кладём.
   Вместо файла можно задать переменные окружения `TG_TOKEN` и `TG_CHAT_ID` для PHP-FPM.
4. Отправить тестовую заявку с сайта. Если сообщение не пришло — смотреть лог ошибок PHP, скрипт пишет туда причину.

Если токен утёк: @BotFather → `/revoke`, новый токен вписать в `tg-config.php`.

### Служебные файлы

Сайт подтягивается из GitHub, поэтому в папке сайта лежит `.git`. Его нельзя отдавать наружу.
Для Apache это делает `.htaccess`. Для nginx добавить в блок `server`:

```nginx
location ~ /\.(git|claude|htaccess) { return 404; }
location ~ ^/(README\.md|_headers)$ { return 404; }
location ~ ^/informed-consent\.(html|pdf)$ { add_header X-Robots-Tag "noindex, nofollow"; }
```
