<p align="center">
  <a href="https://github.com/wizwizdev/wizwizxui-timebot" target="_blank" rel="noopener noreferrer">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://user-images.githubusercontent.com/27927279/227711552-d2bc1089-5666-477b-9be7-d7e50a5286dc.png">
      <img width="200" height="200" src="https://user-images.githubusercontent.com/27927279/227711552-d2bc1089-5666-477b-9be7-d7e50a5286dc.png">
    </picture>
  </a>
</p>

<p align="center">
	<a href="./README.md">
	English
	</a>
	/
	<a href="./README-fa.md">
	فارسی
	</a>

</p>

<h1 align="center"/>ویزویز</h1>

<p align="center">
فروش آسان با <a href="https://github.com/wizwizdev/wizwizxui-timebot">ویزویز</a> نصب فقط با یک دستور
</p>

<p align="center">
ویزویز یک ربات قدرتمند و حرفه ای است که از چندین نوع پنل پشتیبانی می کند و بهترین گزینه برای فروش است، اکثر پروتکل ها را پشتیبانی می کند و نصب آسانی دارد. این ربات برای مردم عزیز ایران آماده شده است. یک جایگزین عالی برای فروش است تا بتوانید به راحتی کار خود را مدیریت کنید.
</p>


<div align=center>

[![Telegram Channel](https://img.shields.io/endpoint?label=Channel&style=flat-square&url=https%3A%2F%2Ftg.sumanjay.workers.dev%2Fwizwizch&color=blue)](https://telegram.dog/wizwizch)
[![Telegram Group](https://img.shields.io/endpoint?color=neon&label=Support%20Group&style=flat-square&url=https%3A%2F%2Ftg.sumanjay.workers.dev%2Fwizwizdev)](https://telegram.dog/wizwizdev)
<img src="https://img.shields.io/github/license/wizwizdev/wizwizxui-timebot?style=flat-square" />
<img src="https://img.shields.io/github/v/release/wizwizdev/wizwizxui-timebot.svg" />
<!-- <img src="https://visitor-badge.glitch.me/badge?page_id=wizwizdev.wizwizdev" />
 -->
</div>

<br>
<br>
    <a align="center">
        <img src="https://github.com/wizwizdev/wizwizxui-timebot/assets/27927279/f6635ea5-ab26-4c64-a7b8-952203f79763" />
    </a>     
<br>
<br>







# حالت API

تمام ارتباط ربات با پنل‌ها از یک لایه مشترک به نام `panelapi.php` (تابع `panelApi()`) انجام می‌شود و دیگر هر تابع جداگانه لاگین و کوکی ندارد.

| نوع پنل | API مورد استفاده |
| --- | --- |
| سنایی (3x-ui) | `/panel/api/inbounds/*` |
| علیرضا (x-ui) | `/xui/API/inbounds/*` |
| مرزبان | REST و توکن Bearer (بدون تغییر) |
| ساده (vaxilu / Niduka) | API عمومی ندارد، از مسیرهای خود پنل `/xui/inbound/*` استفاده می‌شود |

بر پایه پروژه [wizwizdev/wizwizxui-timebot](https://github.com/wizwizdev/wizwizxui-timebot) (لایسنس MIT).

# دستور نصب روی Ubuntu-20.4


- اگر سرور شما دسترسی روت ندارد، لطفا با دستور sudo -i دسترسی روت بدهید و سپس نصب کنید
- یک ربات در @botfather ایجاد کنید و آن را استارت کنید
- قبل از نصب حتما ip سرور را روی دامنه تنظیم کنید 
> دستور نصب را در کنسول وارد کرده و موارد مورد نیاز را برای تکمیل نصب وارد کنید.
```
bash <(curl -s https://raw.githubusercontent.com/ali-mpb1377/wizwizxui-timebot/main/wizwiz.sh)
```
- در مرحله اول «sub.domain.com» یا «domain.com» را بدون https وارد کنید
- ایمیل را وارد کنید
- کلمه y را وارد کنید
- عدد 2 را وارد کنید
- نام کاربری برای دیتابیس را وارد کنید
- رمز عبور برای دیتابیس را وارد کنید
- توکن ربات را وارد کنید
- آیدی عددی ادمین را از @userinfobot بگیرید و وارد کنید
- مجدد «sub.domain.com» یا «domain.com» را بدون https وارد کنید
- بسیار خوب، پیام نصب ( ✅ ربات wizwiz با موفقیت نصب شد! ) به ربات ارسال می شود.

<br>
<br>

## دستور آپدیت ربات - آپدیت پنل - بک آپ - حذف ویزویز

- با هر به روز رسانی و بک آپ، یک اعلان برای ربات مدیر ارسال می شود


```
bash <(curl -s https://raw.githubusercontent.com/ali-mpb1377/wizwizxui-timebot/main/update.sh)
```

<br>
<hr>
<br>
<br>



# پنل های پشتیبانی شده

- (Marzban)
````
sudo bash -c "$(curl -sL https://github.com/Gozargah/Marzban-scripts/raw/master/marzban.sh)" @ install
````
- (Niduka Akalanka)
````
bash <(curl -Ls https://raw.githubusercontent.com/NidukaAkalanka/x-ui-english/master/install.sh) 0.2.1.2
````
- (Sanaei)
````
bash <(curl -Ls https://raw.githubusercontent.com/mhsanaei/3x-ui/master/install.sh) v1.7.9
````
- (Alireza)
````
bash <(curl -Ls https://raw.githubusercontent.com/alireza0/x-ui/master/install.sh) 1.5.5
````
- (Vaxilu)
````
bash <(curl -Ls https://raw.githubusercontent.com/vaxilu/x-ui/master/install.sh)
````



<br>
<hr>
<br>


# حمایت

- Tron (TRX): `TY8j7of18gbMtneB8bbL7SZk5gcntQEemG`
- Bitcoin: `bc1qcnkjnqvs7kyxvlfrns8t4ely7x85dhvz5gqge4`
- Dogecoin: `DMyGMghEh4W55P3VeVHntCN3vYAFtshvVH`



<br>
<hr>
<br>


# امکانات

- درگاه nowpayments - zarinpal - nextpay و ارزی ریالی
- پشتیبانی از - xtls - tls - reality - Grpc - ws - tcp
- پشتیبانی vless - vmess - trojan
- امکان تمدید سرویس
- نمایش مشخصات کانفیگ به صورت ساب ( داخل نرم افزار v2ray )
- نمایندگی ( خرید تکی و انبوه - مدیریت کانفیگ - آمار فروش و ... )
- قابلیت جست و جو کانفیگ های خریداری شده برای دسترسی راحت ( نماینده )
- نمایش مشخصات  کانفیگ به صورت وب
- دکمه ثبت لینک برای تمدید و به روزرسانی کانفیگ ( از بات خرید نشده )
- دکمه دریافت QRcode کانفیگ 
- حذف کانفیگ توسط کاربر ( حذف از پنل x-ui و دیتابیس + اعلان حذف )
- قابلیت اضافه کردن حجم و زمان روی سرور دلخواه + اعلان
- قابلیت کسر موجودی از اعتبار کاربر
- قابلیت قطع و دریافت لینک جدید توسط کاربر ( تغییر uuid )
- قابلیت به روزرسانی کانکشن ( با توجه به تغییرات شما در پنل )
- قابلیت تغییر نام کانفیگ ( رندم و عادی )
- تعیین نام کانفیگ هنگام خرید توسط کاربر ( پلن دلخواه )
- اشتراک هوشمند
- وضعیت فیلتر شدن سرورها
- تغییر مکان خودکار
- افزایش حجم و زمان سرویس دهی
- قابلیت پاس کردن
- امکان سفارش طرح مورد نظر توسط کاربر
- احراز هویت شماره تماس ایرانی و خارجی
- پشتیبان گیری از پنل x-ui
- زیر مجموعه و کمیسیون
- کدهای تخفیف و هدیه ایجاد کنید
- امکان ردیابی کاربر
- ایجاد دکمه و پاسخ برای آن
- خروجی پیکربندی با IP یا دامنه های مختلف
- امکان تغییر پروتکل و نوع شبکه
- تنظیم پورت پیکربندی به صورت تصادفی یا خودکار
- کیف پول (امکان شارژ - انتقال موجودی)
- ارسال اعلان عضو جدید در ربات به (ادمین)
- نمایش اطلاعات کاربر (user-admin)
- امکان ارسال پیام خصوصی از ادمین به کاربر
- امکان مدیریت و مشاهده سرورها - دسته بندی ها - پلن ها
- قابلیت مسدود کردن و آزادسازی
- امکان اضافه کردن ادمین
- نمایش موجودی سرورها
- امکان ارسال گزارش درآمد به کانال
- ارسال پیام های عمومی
- پیکربندی های فروخته شده را دریافت کنید
- ایجاد پورت مشترک و پیکربندی پورت اختصاصی
- تست حساب برای کاربران
- قابلیت کارت به کارت
- نمایش حساب های فروخته شده هر طرح
- قابلیت نمایش (لینک نرم افزار)
- ارسال پیام های عمومی با CronJob
- اعلام پایان حجم و زمان پیکربندی (به کاربر)
- قفل اجباری کانال
- امکان دریافت جزئیات لینک
- قابلیت خاموش/روشن (همه ویژگی های ربات)
- اطلاع رسانی اطلاعات خرید + تمدید و ... به صورت کامل به ربات ادمین



<br>
<hr>
<br>


حتما به گروه و کانال بپیوندید و از ما حمایت کنید

## Contact Developer
💎 Group: https://t.me/wizwizdev
💎 Channel: https://t.me/wizwizch

<br>
<br>

## Stargazers over time

[![Stargazers over time](https://starchart.cc/wizwizdev/wizwizxui-timebot.svg)](https://starchart.cc/wizwizdev/wizwizxui-timebot)
