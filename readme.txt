# AT Excel Price 1.1.0

Плагин WordPress: загрузка прайса `.xlsx` и вывод шорткодом `[at_excel_price id="N"]`.

ZipArchive не обязателен: при его отсутствии используется встроенный читатель ZIP на чистом PHP (нужна функция `gzinflate` / zlib). Также нужны `dom` и `libxml`.

## Обновления с GitHub

Репозиторий по умолчанию: `AtStudia/at-excel-price` (можно переопределить в **AT Excel Price → Оформление → Репозиторий**).

1. Код плагина лежит в корне репозитория (`at-excel-price.php` в корне).
2. Для новой версии увеличьте `Version` в `at-excel-price.php` и отправьте тег `v1.2.0`.
3. GitHub Action соберёт `at-excel-price.zip` и опубликует релиз. WordPress покажет обновление в разделе «Плагины».

Токен нужен только если репозиторий приватный (classic token с доступом `repo`).
