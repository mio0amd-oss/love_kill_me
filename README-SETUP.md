# subkazani — rebuild from scratch

این نسخه عمداً از صفر ساخته شده ولی ظاهر اصلی سایت حفظ شده است.

## ساختار

- `site/` نسخه عمومی GitHub Pages است.
- `site/index.html` قالب اصلی و استایل فعلی را نگه می‌دارد، اما محتوا را از `site/data/content.json` می‌خواند.
- `site/data/content.json` در شروع خالی است؛ هیچ محصول/محتوای نمونه‌ای ساخته نمی‌شود.
- `site/assets/` محل فایل‌های جانبی دلخواه است.
- `admin/` پنل PHP امن است و باید روی یک هاست PHP جدا اجرا شود، ترجیحاً `admin.subkazani.ir`.
- `legacy/` نسخه اصلی فعلی و بسته قبلی برای نگهداری/مقایسه است.

## فایل‌های ظاهر فعلی

قالب، CSS و رفتار اصلی پخش‌کننده از `index.html` فعلی گرفته شده است. فایل‌های باینری موجود در GitHub مثل MP3 و `avatar.jpg` داخل این محیط قابل کپی مستقیم نبودند؛ آن‌ها را از مخزن فعلی خودت به ریشه `site/` کپی کن. `back.jpg` هم اگر داری در همان‌جا قرار بده.

فایل‌های شناخته‌شده مخزن فعلی:
- `Music.mp3`
- `aktor.mp3`
- `toto.mp3`
- `sahel.mp3`
- `avatar.jpg`
- `CNAME`
- `README.md`
- `docs/`

## راه‌اندازی پنل

1. پوشه `admin` را روی هاست PHP قرار بده.
2. `config.example.php` را به `config.php` تغییر نام بده.
3. `github_owner=mio0amd-oss`، `github_repo=love_kill_me` و branch را تنظیم کن.
4. یک GitHub token فقط با حداقل دسترسی لازم برای همین repository بساز و در `config.php` قرار بده. توکن را هرگز داخل `site/` یا JavaScript عمومی قرار نده.
5. برای رمز پنل، hash بساز:
   `php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"`
6. hash را در `admin_password_hash` قرار بده.
7. دامنه پیشنهادی پنل: `admin.subkazani.ir`.

## منطق جدید

- سایت عمومی همچنان استاتیک و مناسب GitHub Pages است.
- پنل با PHP روی هاست جدا اجرا می‌شود.
- پنل `content.json` را با GitHub API به‌روزرسانی می‌کند.
- آپلود موسیقی MP3 ابتدا فایل را در `music/` ذخیره می‌کند و سپس JSON را به‌روزرسانی می‌کند؛ اگر JSON شکست بخورد، فایل تازه تا حد امکان rollback می‌شود.
- حذف موسیقی از محتوا انجام می‌شود و سپس فایل GitHub نیز حذف می‌شود.
- حذف‌ها حذف واقعی هستند، نه inactive کردن.
- CSRF، session سخت‌گیرانه، `HttpOnly`، `SameSite=Strict`، `session_regenerate_id` و محدودیت تلاش ورود در پنل وجود دارد.

## نکته مهم درباره GitHub Pages

`admin.php` روی GitHub Pages اجرا نمی‌شود. بنابراین آدرس پیشنهادی پنل:
`https://admin.subkazani.ir/`
و سایت عمومی:
`https://subkazani.ir/`

## شروع کاملاً تمیز

اگر می‌خواهی هیچ محتوای قبلی روی سایت نباشد، همین `site/data/content.json` را نگه دار؛ آرایه‌های `music` و `texts` عمداً خالی هستند.
