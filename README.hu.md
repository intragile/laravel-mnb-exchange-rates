# Laravel MNB Exchange Rates

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)

A **Magyar Nemzeti Bank hivatalos devizaárfolyamai** PHP-hez és Laravelhez, **`ext-soap` nélkül**.

[English README → README.md](README.md)

- **Nem kell SOAP-bővítmény.** A kérést kézzel állítjuk össze, a választ SimpleXML-lel olvassuk.
  A karcsú Docker-image-ekben ritkán van `ext-soap`, ennek a csomagnak nincs rá szüksége.
- **Számlára tehető árfolyam.** Az értékek pontos decimális stringek, nem floatok. A 100
  egységre jegyzett devizákat (JPY, IDR, KRW) 1 egységre számolja át: a 100 JPY-ra jegyzett
  `205,30` értékből `2.053` lesz.
- **Hétvége és ünnepnap.** Az `on($dátum)` az adott napon érvényes táblát adja: az aznapit, vagy
  ha aznap nem volt jegyzés, az utolsó előtte közzétettet. A tábla megmondja, melyik napra szól.
- **A szolgáltatás buktatói lefedve.** Tizedesvessző, üres napok devizalista nélkül, némán
  kihagyott ismeretlen deviza, HTTP 500 mögötti SOAP fault: mindegyiket kezeli, és az élő
  szolgáltatásból rögzített válaszokon tesztelve van.
- **Keretrendszer-független mag.** Az `MnbClient` sima PHP, Composer-függőség nélkül. A Laravel-réteg
  facade-ot, válasz-cache-t, `Mnb::fake()`-et és Artisan parancsot ad hozzá.

## Telepítés

```bash
composer require intragile/laravel-mnb-exchange-rates
```

PHP 8.3+ és `ext-simplexml` kell. A Laravel-réteg a 12-es és 13-as Laravelt támogatja, és
automatikusan betöltődik.

## Használat

```php
use IntrAgile\MnbExchangeRates\MnbClient;

$mnb = new MnbClient();

$mnb->latest()->rate('EUR')->perUnit();   // "363.18"
$mnb->latest()->rate('JPY')->perUnit();   // "2.053" (100 egységre jegyezve: 205,30)
$mnb->on('2025-12-27')->date;             // 2025-12-23: karácsonykor nem volt jegyzés
$mnb->rate('USD', '2026-03-15');          // az on(...)->rate(...) rövidítése
$mnb->convert(100, 'EUR', 'USD');         // float, forint keresztárfolyamon
$mnb->between('2026-10-01', '2026-10-31', ['EUR', 'USD']);
```

Laravelben:

```php
use IntrAgile\MnbExchangeRates\Laravel\Facades\Mnb;

Mnb::rate('EUR', $szamla->teljesites_datuma)->perUnit();
```

```bash
php artisan mnb:rates 2025-12-27 --currency=EUR
```

Tesztben:

```php
Mnb::fake(['2026-11-06' => ['EUR' => '363.94']]);
Mnb::rate('EUR', '2026-11-08')->perUnit(); // "363.94", ugyanazokkal a visszalépési szabályokkal
```

A beállításokat, a kivételeket és a cache működését az [angol README](README.md) írja le részletesen.

## Jó tudni

- **Az MNB munkanapokon dél körül teszi közzé az árfolyamokat.** Közzététel előtt az `on(ma)` az
  előző munkanap tábláját adja, mert abban a pillanatban az az érvényes.
- **A szolgáltatás csak HTTP-n érhető el**, a HTTPS-cím 404-et ad erre az útvonalra.
- **A `convert()` floatot ad**, ami megjelenítésre és becslésre való. Könyveléshez a
  `rate(...)->perUnit()` értékkel és a saját pénzkezelő könyvtáraddal számolj.
- **Hogy a jogszabály melyik napi árfolyamot írja elő** (például az áfás szabályok a devizás
  számlánál), azt neked kell eldöntened. A csomag a kért napon érvényes hivatalos MNB-középárfolyamot adja.

## Licenc

MIT, lásd [LICENSE](LICENSE). Az árfolyamadatok forrása a Magyar Nemzeti Bank.

---

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu).
