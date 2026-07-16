<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'Invalid method']); exit(); }

// Kézbesítés wrapper — teszteléshez felülírható (test_send.php ezt lecseréli, hogy ne küldjön valódi emailt)
if (!function_exists('deliver')) {
    function deliver($to, $subject, $body, $headers) {
        return mail($to, $subject, $body, $headers);
    }
}

// ─── Adatok kiolvasása (multipart form-data) ───
$name           = htmlspecialchars($_POST['name'] ?? '');
$phone          = htmlspecialchars($_POST['phone'] ?? '');
$email          = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$address        = htmlspecialchars($_POST['address'] ?? '');
$emergencyName  = htmlspecialchars($_POST['emergencyName'] ?? '');
$emergencyPhone = htmlspecialchars($_POST['emergencyPhone'] ?? '');
$emergencyRel   = htmlspecialchars($_POST['emergencyRelation'] ?? '');
$dogName        = htmlspecialchars($_POST['dogName'] ?? '');
$dogBreed       = htmlspecialchars($_POST['dogBreed'] ?? '');
$dogAge         = htmlspecialchars($_POST['dogAge'] ?? '');
$dogGender      = htmlspecialchars($_POST['dogGender'] ?? '');
$dogNeutered    = htmlspecialchars($_POST['dogNeutered'] ?? '');
$dogHeat        = htmlspecialchars($_POST['dogHeat'] ?? 'Nem');
$dogChip        = htmlspecialchars($_POST['dogChip'] ?? '');
$dogSince       = htmlspecialchars($_POST['dogSince'] ?? '');
$dogFood        = htmlspecialchars($_POST['dogFood'] ?? '');
$dogFoodAmt     = htmlspecialchars($_POST['dogFoodAmt'] ?? '');
$vaccRabies     = htmlspecialchars($_POST['vaccRabies'] ?? '');
$vaccCombo      = htmlspecialchars($_POST['vaccCombo'] ?? '');
$wormerName     = htmlspecialchars($_POST['wormerName'] ?? '');
$wormerDate     = htmlspecialchars($_POST['wormerDate'] ?? '');
$dogIllness     = htmlspecialchars($_POST['dogIllness'] ?? '');
$dogMeds        = htmlspecialchars($_POST['dogMeds'] ?? '');
$dogTrauma      = htmlspecialchars($_POST['dogTrauma'] ?? '');
$dogBehavior    = htmlspecialchars($_POST['dogBehavior'] ?? '');
$dogComment     = htmlspecialchars($_POST['dogComment'] ?? '');
$pkg            = htmlspecialchars($_POST['pkg'] ?? '');
$dateFrom       = htmlspecialchars($_POST['dateFrom'] ?? '');
$dateTo         = htmlspecialchars($_POST['dateTo'] ?? 'Nincs megadva');
$packagesRaw    = $_POST['packages'] ?? '';
$dogsCount      = intval($_POST['dogsCount'] ?? 1);
$extraDogsRaw   = $_POST['extraDogs'] ?? '';
$paymentMethod  = htmlspecialchars($_POST['paymentMethod'] ?? '');
$paymentTiming  = htmlspecialchars($_POST['paymentTiming'] ?? '');

// Extra kutyák (2–4) adatainak kiolvasása
$extraDogs = [];
for ($n = 2; $n <= 4; $n++) {
    if (empty($_POST["dog{$n}Name"]) && empty($_POST["dog{$n}Breed"])) continue;
    $extraDogs[$n] = [
        'name'       => htmlspecialchars($_POST["dog{$n}Name"] ?? ''),
        'breed'      => htmlspecialchars($_POST["dog{$n}Breed"] ?? ''),
        'age'        => htmlspecialchars($_POST["dog{$n}Age"] ?? ''),
        'gender'     => htmlspecialchars($_POST["dog{$n}Gender"] ?? ''),
        'neutered'   => htmlspecialchars($_POST["dog{$n}Neutered"] ?? ''),
        'chip'       => htmlspecialchars($_POST["dog{$n}Chip"] ?? ''),
        'since'      => htmlspecialchars($_POST["dog{$n}Since"] ?? ''),
        'food'       => htmlspecialchars($_POST["dog{$n}Food"] ?? ''),
        'foodAmt'    => htmlspecialchars($_POST["dog{$n}FoodAmt"] ?? ''),
        'vaccRabies' => htmlspecialchars($_POST["dog{$n}VaccRabies"] ?? ''),
        'vaccCombo'  => htmlspecialchars($_POST["dog{$n}VaccCombo"] ?? ''),
        'wormerName' => htmlspecialchars($_POST["dog{$n}WormerName"] ?? ''),
        'wormerDate' => htmlspecialchars($_POST["dog{$n}WormerDate"] ?? ''),
        'illness'    => htmlspecialchars($_POST["dog{$n}Illness"] ?? ''),
        'meds'       => htmlspecialchars($_POST["dog{$n}Meds"] ?? ''),
        'trauma'     => htmlspecialchars($_POST["dog{$n}Trauma"] ?? ''),
        'behavior'   => htmlspecialchars($_POST["dog{$n}Behavior"] ?? ''),
    ];
}

// ─── Veszettség oltás 14 napos szabály ───
// Az oltásnak legalább 14 nappal az érkezés előtt kellett megtörténnie.
// Ha nincs (értelmezhető) érkezési dátum, a mai naphoz viszonyítunk.
function rabiesTooRecent($vaccDate, $arrivalDate) {
    $vacc = strtotime($vaccDate);
    if ($vacc === false) return false;
    $ref = strtotime($arrivalDate);
    if ($ref === false) $ref = time();
    return $vacc > strtotime('-14 days', $ref);
}

$rabiesAlertNames = [];
$rabiesFlagMain = $vaccRabies && rabiesTooRecent($vaccRabies, $dateFrom);
if ($rabiesFlagMain) $rabiesAlertNames[] = $dogName ?: '1. kutya';
foreach ($extraDogs as $n => &$d) {
    $d['rabiesFlag'] = $d['vaccRabies'] && rabiesTooRecent($d['vaccRabies'], $dateFrom);
    if ($d['rabiesFlag']) $rabiesAlertNames[] = $d['name'] ?: "{$n}. kutya";
}
unset($d);

// ─── Féreghajtás 3 hónapos szabály ───
// A féreghajtásnak az érkezéstől visszaszámítva 3 hónapnál nem régebbinek kell lennie.
// Ha nincs (értelmezhető) érkezési dátum, a mai naphoz viszonyítunk.
function wormerTooOld($wormerDate, $arrivalDate) {
    $w = strtotime($wormerDate);
    if ($w === false) return false;
    $ref = strtotime($arrivalDate);
    if ($ref === false) $ref = time();
    return $w < strtotime('-3 months', $ref);
}

$wormerAlertNames = [];
$wormerFlagMain = $wormerDate && wormerTooOld($wormerDate, $dateFrom);
if ($wormerFlagMain) $wormerAlertNames[] = $dogName ?: '1. kutya';
foreach ($extraDogs as $n => &$d) {
    $d['wormerFlag'] = $d['wormerDate'] && wormerTooOld($d['wormerDate'], $dateFrom);
    if ($d['wormerFlag']) $wormerAlertNames[] = $d['name'] ?: "{$n}. kutya";
}
unset($d);

// ─── Naponkénti csomagok több sorba — kutyánként (" || " választja el a kutyákat)
$packagesHtml = '';
$dogBlocks = explode('||', $packagesRaw);

foreach($dogBlocks as $block) {
    $block = trim($block);
    if(!$block) continue;

    $packagesHtml .= "<div style='padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.08);'>" 
        . nl2br(htmlspecialchars($block)) 
        . "</div>";
}

if(!$packagesHtml) $packagesHtml = '–';

// Végösszeg: az űrlap által küldött (kedvezményekkel számolt) érték az elsődleges.
$total = is_numeric($_POST['total'] ?? null) ? intval($_POST['total']) : 0;
$deposit = is_numeric($_POST['deposit'] ?? null) ? intval($_POST['deposit']) : round($total * 0.5);
$remainder = is_numeric($_POST['remainder'] ?? null) ? intval($_POST['remainder']) : ($total - $deposit);
$totalFmt = number_format($total, 0, ',', ' ') . ' Ft';
$depositFmt = number_format($deposit, 0, ',', ' ') . ' Ft';
$remainderFmt = number_format($remainder, 0, ',', ' ') . ' Ft';

// Spam vedelem - honeypot
if(!empty($_POST['website'])) {
    echo json_encode(['success'=>false,'error'=>'Spam detected']);
    exit();
}

if (!$name || !$email || !$dogName) {
    echo json_encode(['success'=>false,'error'=>'Missing required fields']); exit();
}

// ─── Képek feldolgozása ───
$attachments = [];
foreach($_FILES as $key => $file) {
    if(strpos($key, 'booklet_') === 0 || strpos($key, 'photo_') === 0) {
        if($file['error'] === UPLOAD_ERR_OK) {
            $attachments[] = [
                'name'    => basename($file['name']),
                'type'    => $file['type'],
                'content' => base64_encode(file_get_contents($file['tmp_name'])),
            ];
        }
    }
}

// ─── Stílusok ───
$dark    = '#1a1a1a';
$bodyBg  = '#1a1a1a';
$cardBg  = '#222222';
$primary = '#F99905';
$textLight = '#ffffff';
$textGray  = '#cccccc';
$border    = '#333333';
$logoUrl   = 'https://blaya.netlify.app/blaya_logo.png';

// ─── Logó a levelekben ───
// A logót a levélbe ágyazzuk (CID inline kép), így a levelezők nem blokkolják
// és nem "üres placeholder"-ként jelenik meg. A base64 a blaya_email_assets.php-ban van.
// Ha az asset fájl bármi okból hiányzik, visszaesünk a régi URL-es megoldásra.
$logoInline = null;
$logoSrc    = $logoUrl;
$assetsFile = __DIR__ . '/blaya_email_assets.php';
if (is_file($assetsFile)) {
    include $assetsFile;
    if (!empty($BLAYA_LOGO_B64)) {
        $logoInline = [
            'name'    => 'blaya_logo.png',
            'type'    => 'image/png',
            'cid'     => 'blayalogo',
            'content' => chunk_split($BLAYA_LOGO_B64),
        ];
        $logoSrc = 'cid:blayalogo';
    }
}

// ─── Fizetési (utalási) adatok — TODO: írd be a valós számlaszámot! ───
$bankAccountName   = 'Brkity Bernadett (BLAYA Kutyapanzió)';
$bankAccountNumber = '00000000-00000000-00000000'; // <-- IDE a valós számlaszám
$bankName          = ''; // pl. 'OTP Bank' (opcionális, üresen hagyható)

function row($label, $value, $textGray, $border) {
    if (!$value) return '';
    return "
    <tr>
      <td style='padding:8px 16px;font-size:13px;color:{$textGray};font-weight:600;border-bottom:1px solid {$border};width:200px;vertical-align:top;font-family:Arial,sans-serif;'>{$label}</td>
      <td style='padding:8px 16px;font-size:13px;color:{$textGray};border-bottom:1px solid {$border};font-family:Arial,sans-serif;'>{$value}</td>
    </tr>";
}
function rowHtml($label, $value, $textGray, $border) {
    if (!$value) return '';
    return "
    <tr>
      <td style='padding:8px 16px;font-size:13px;color:{$textGray};font-weight:600;border-bottom:1px solid {$border};width:200px;vertical-align:top;font-family:Arial,sans-serif;'>{$label}</td>
      <td style='padding:8px 16px;font-size:13px;color:{$textGray};border-bottom:1px solid {$border};font-family:Arial,sans-serif;'>{$value}</td>
    </tr>";
}
function sec($title, $primary) {
    return "
    <tr>
      <td colspan='2' style='padding:14px 16px 8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:{$primary};border-bottom:2px solid {$primary};font-family:Arial,sans-serif;'>{$title}</td>
    </tr>";
}

// Egy extra kutya (2–4.) teljes adatblokkja
function extraDogSection($d, $primary, $textGray, $border) {
    $title = $d['name'] ? ($d['name'] . ' adatai') : 'További kutya adatai';
    $html  = sec($title, $primary);
    $html .= row('Kutya neve:', $d['name'], $textGray, $border);
    $html .= row('Fajta:', $d['breed'], $textGray, $border);
    $html .= row('Kor:', $d['age'], $textGray, $border);
    $html .= row('Nem:', $d['gender'], $textGray, $border);
    $html .= row('Ivartalanított:', $d['neutered'], $textGray, $border);
    $html .= row('Chip száma:', $d['chip'], $textGray, $border);
    $html .= row('Mióta van a kutya:', $d['since'], $textGray, $border);
    $html .= row('Etetés típusa:', $d['food'], $textGray, $border);
    $html .= row('Napi adag:', $d['foodAmt'] ? $d['foodAmt'] . ' g' : '', $textGray, $border);
    $htitle = $d['name'] ? ($d['name'] . ' egészségügyi adatai') : 'További kutya – egészségügyi adatok';
    $html .= sec($htitle, $primary);
    $rabiesVal = $d['vaccRabies'];
    if (!empty($d['rabiesFlag'])) {
        $rabiesVal = "<span style='color:#DE0E0E;font-weight:700;font-size:15px;'>⚠ {$rabiesVal} — 14 NAPON BELÜLI OLTÁS!</span>";
    }
    $html .= row('Veszettség oltás:', $rabiesVal, $textGray, $border);
    $html .= row('Kombinált oltás:', $d['vaccCombo'], $textGray, $border);
    $html .= row('Utolsó féreghajtó neve:', $d['wormerName'], $textGray, $border);
    $wormerVal = $d['wormerDate'];
    if (!empty($d['wormerFlag'])) {
        $wormerVal = "<span style='color:#DE0E0E;font-weight:700;font-size:15px;'>⚠ {$wormerVal} — 3 HÓNAPNÁL RÉGEBBI!</span>";
    }
    $html .= row('Utolsó féreghajtó dátuma:', $wormerVal, $textGray, $border);
    $html .= row('Betegség:', $d['illness'], $textGray, $border);
    $html .= row('Gyógyszer:', $d['meds'], $textGray, $border);
    $html .= row('Trauma/Támadás:', $d['trauma'], $textGray, $border);
    $html .= row('Habitus:', $d['behavior'], $textGray, $border);
    return $html;
}

// Extra kutyák szekcióinak összeállítása
$extraDogsHtml = '';
foreach($extraDogs as $d) {
    $extraDogsHtml .= extraDogSection($d, $primary, $textGray, $border);
}

// ─── FOGLALÓ BEKÉRÉSE GOMB (előre kitöltött email a foglalónak) ───
// A gombra kattintva megnyílik egy kész email a vendég címére: foglaló összege,
// számlaszám és közlemény előre kitöltve — csak átnézni és elküldeni kell.
$payRef = trim("BLAYA foglalo - {$dogName}" . ($dateFrom ? " - {$dateFrom}" : ""));
$payMailLines = [
    "Kedves {$name}!",
    "",
    "Köszönjük a foglalási kérelmet! A foglalás véglegesítéséhez kérjük az alábbi foglaló összegének átutalását:",
    "",
    "Foglaló összege: {$depositFmt}",
    "Kedvezményezett: {$bankAccountName}",
    "Számlaszám: {$bankAccountNumber}" . ($bankName ? " ({$bankName})" : ""),
    "Közlemény: {$payRef}",
    "",
    "A foglalás a foglaló beérkezésével válik véglegessé. A fennmaradó összeg ({$remainderFmt}) a kutya hazavitelekor esedékes.",
    "",
    "Kérdés esetén keress minket bátran!",
    "",
    "Üdvözlettel,",
    "Brkity Bernadett",
    "BLAYA Kutyapanzió",
    "+36 30 159 2499",
];
$payMailto = "mailto:{$email}"
    . "?subject=" . rawurlencode("BLAYA Kutyapanzió – foglaló fizetési adatok ({$dogName})")
    . "&body=" . rawurlencode(implode("\r\n", $payMailLines));
$payMailtoAttr = htmlspecialchars($payMailto, ENT_QUOTES);

// ─── BELSŐ EMAIL ───
// Piros figyelmeztető sáv, ha bármelyik kutya veszettség oltása 14 napon belüli
$rabiesBannerHtml = '';
if (!empty($rabiesAlertNames)) {
    $alertNames = implode(', ', $rabiesAlertNames);
    $rabiesBannerHtml = "
  <tr><td style='background:#DE0E0E;padding:20px 32px;text-align:center;'>
    <div style='font-size:22px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:1px;font-family:Arial,sans-serif;line-height:1.4;'>&#9888; FIGYELEM: VESZETTSÉG OLTÁS 14 NAPON BELÜLI!</div>
    <div style='font-size:14px;font-weight:700;color:#ffffff;font-family:Arial,sans-serif;margin-top:8px;line-height:1.6;'>Érintett kutya: {$alertNames}<br>Az oltás kevesebb mint 14 nappal az érkezés előtt történt — a panzióztatás feltétele nem teljesül!</div>
  </td></tr>";
}
$vaccRabiesDisplay = $vaccRabies;
if ($rabiesFlagMain) {
    $vaccRabiesDisplay = "<span style='color:#DE0E0E;font-weight:700;font-size:15px;'>⚠ {$vaccRabies} — 14 NAPON BELÜLI OLTÁS!</span>";
}

// Féreghajtás figyelmeztető sáv, ha bármelyik kutya féreghajtása 3 hónapnál régebbi
$wormerBannerHtml = '';
if (!empty($wormerAlertNames)) {
    $wAlertNames = implode(', ', $wormerAlertNames);
    $wormerBannerHtml = "
  <tr><td style='background:#DE0E0E;padding:20px 32px;text-align:center;'>
    <div style='font-size:22px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:1px;font-family:Arial,sans-serif;line-height:1.4;'>&#9888; FIGYELEM: FÉREGHAJTÁS 3 HÓNAPNÁL RÉGEBBI!</div>
    <div style='font-size:14px;font-weight:700;color:#ffffff;font-family:Arial,sans-serif;margin-top:8px;line-height:1.6;'>Érintett kutya: {$wAlertNames}<br>A féreghajtás több mint 3 hónapja történt — a panzióztatás feltétele nem teljesül!</div>
  </td></tr>";
}
$wormerDateDisplay = $wormerDate;
if ($wormerFlagMain) {
    $wormerDateDisplay = "<span style='color:#DE0E0E;font-weight:700;font-size:15px;'>⚠ {$wormerDate} — 3 HÓNAPNÁL RÉGEBBI!</span>";
}

// A tárgysor tiszta marad; a figyelmeztetések a levél törzsében, piros sávban jelennek meg.
$internalSubject = "=?UTF-8?B?" . base64_encode("Új foglalási kérelem – {$dogName} ({$name})") . "?=";
$internalBody = "
<!DOCTYPE html><html lang='hu'><head><meta charset='UTF-8'>
<meta name='color-scheme' content='light only'>
<meta name='supported-color-schemes' content='light'>
<style>
:root { color-scheme: light only; }
body { background-color: #1a1a1a !important; }
</style>
</head>
<body style='margin:0;padding:0;background:{$bodyBg};font-family:Arial,sans-serif;' bgcolor='#1a1a1a'>
<table width='100%' cellpadding='0' cellspacing='0' bgcolor='#1a1a1a' style='background:{$bodyBg};padding:32px 16px;'>
<tr><td align='center'>
<table width='620' cellpadding='0' cellspacing='0' bgcolor='#222222' style='background:{$cardBg};border-radius:16px;overflow:hidden;'>

  <tr><td style='background:{$dark};padding:28px 32px;text-align:center;border-bottom:3px solid {$primary};'>
    <img src='{$logoSrc}' alt='BLAYA' width='72' height='72' style='width:72px;height:72px;display:block;margin:0 auto 8px;background:#1a1a1a;padding:8px;border-radius:8px;'>
    <div style='font-size:11px;color:{$primary};letter-spacing:3px;text-transform:uppercase;font-family:Arial,sans-serif;'>Kutyapanzió</div>
  </td></tr>

  <tr><td style='background:{$primary};padding:14px 32px;text-align:center;'>
    <div style='font-size:15px;font-weight:700;color:{$dark};text-transform:uppercase;letter-spacing:2px;font-family:Arial,sans-serif;'>⭐ Új foglalási kérelem érkezett</div>
  </td></tr>
  {$rabiesBannerHtml}
  {$wormerBannerHtml}
  <tr><td style='padding:24px 32px 0;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid {$border};border-radius:8px;overflow:hidden;'>
      " . sec('Gazda adatai', $primary) . "
      " . row('Teljes név:', $name, $textGray, $border) . "
      " . row('Telefonszám:', $phone, $textGray, $border) . "
      " . row('Email:', $email, $textGray, $border) . "
      " . row('Lakcím:', $address, $textGray, $border) . "
      " . sec('Vészhelyzeti kapcsolattartó', $primary) . "
      " . row('Kapcsolattartó neve:', $emergencyName, $textGray, $border) . "
      " . row('Kapcsolattartó telefonszáma:', $emergencyPhone, $textGray, $border) . "
      " . row('Kapcsolat a gazdával:', $emergencyRel, $textGray, $border) . "
      " . sec($dogsCount > 1 ? ($dogName ? $dogName . ' adatai (1. kutya)' : '1. kutya adatai') : 'A kutya adatai', $primary) . "
      " . row('Kutya neve:', $dogName, $textGray, $border) . "
      " . row('Fajta:', $dogBreed, $textGray, $border) . "
      " . row('Kor:', $dogAge, $textGray, $border) . "
      " . row('Nem:', $dogGender, $textGray, $border) . "
      " . row('Ivartalanított:', $dogNeutered, $textGray, $border) . "
      " . row('Tüzelő szuka:', $dogHeat, $textGray, $border) . "
      " . row('Chip száma:', $dogChip, $textGray, $border) . "
      " . row('Mióta van a kutya:', $dogSince, $textGray, $border) . "
      " . row('Etetés típusa:', $dogFood, $textGray, $border) . "
      " . row('Napi adag:', $dogFoodAmt ? $dogFoodAmt . ' g' : '', $textGray, $border) . "
      " . sec('Egészségügyi adatok', $primary) . "
      " . row('Veszettség oltás:', $vaccRabiesDisplay, $textGray, $border) . "
      " . row('Kombinált oltás:', $vaccCombo, $textGray, $border) . "
      " . row('Utolsó féreghajtó neve:', $wormerName, $textGray, $border) . "
      " . row('Utolsó féreghajtó dátuma:', $wormerDateDisplay, $textGray, $border) . "
      " . row('Betegség:', $dogIllness, $textGray, $border) . "
      " . row('Gyógyszer:', $dogMeds, $textGray, $border) . "
      " . row('Trauma/Támadás:', $dogTrauma, $textGray, $border) . "
      " . sec('Viselkedés', $primary) . "
      " . row('Habitus:', $dogBehavior, $textGray, $border) . "
      " . row('Megjegyzés:', $dogComment, $textGray, $border) . "
      " . $extraDogsHtml . "
      " . sec($dogsCount > 1 ? "Foglalás részletei ({$dogsCount} kutya)" : 'Foglalás részletei', $primary) . "
      " . rowHtml('Naponkénti csomagok:', $packagesHtml, $textGray, $border) . "
      " . row('Érkezés:', $dateFrom, $textGray, $border) . "
      " . row('Távozás:', $dateTo, $textGray, $border) . "
      " . row('Becsült végösszeg:', $totalFmt, $textGray, $border) . "
      " . row('Foglaló (50%):', $depositFmt, $textGray, $border) . "
      " . row('Maradék (távozáskor):', $remainderFmt, $textGray, $border) . "
      " . row('Fizetés ütemezése:', $paymentTiming, $textGray, $border) . "
      " . row('Választott fizetési mód:', $paymentMethod, $textGray, $border) . "
    </table>
  </td></tr>

  <tr><td style='padding:24px 32px;text-align:center;'>
    <a href='mailto:{$email}' style='display:inline-block;background:{$primary};color:{$dark};font-weight:700;font-size:14px;padding:14px 32px;border-radius:8px;text-decoration:none;letter-spacing:1px;font-family:Arial,sans-serif;margin:4px;'>✉ Válasz küldése</a>
    <a href='{$payMailtoAttr}' style='display:inline-block;background:#2e7d32;color:#ffffff;font-weight:700;font-size:14px;padding:14px 32px;border-radius:8px;text-decoration:none;letter-spacing:1px;font-family:Arial,sans-serif;margin:4px;'>💳 Foglaló bekérése ({$depositFmt})</a>
  </td></tr>

  <tr><td style='background:{$dark};padding:16px 32px;text-align:center;border-top:1px solid {$border};'>
    <div style='font-size:11px;color:rgba(255,255,255,0.35);font-family:Arial,sans-serif;'>BLAYA Kutyapanzió · Csávoly, Dózsa György u. 22. · info@blaya.hu · +36 30 159 2499</div>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>";

// ─── VENDÉG EMAIL ───
$autoSubject = "=?UTF-8?B?" . base64_encode("Köszönjük a foglalási kérelmet – BLAYA Kutyapanzió") . "?=";
$autoBody = "
<!DOCTYPE html><html lang='hu'><head><meta charset='UTF-8'>
<meta name='color-scheme' content='light only'>
<meta name='supported-color-schemes' content='light'>
<style>
:root { color-scheme: light only; }
body { background-color: #1a1a1a !important; }
</style>
</head>
<body style='margin:0;padding:0;background:{$bodyBg};font-family:Arial,sans-serif;' bgcolor='#1a1a1a'>
<table width='100%' cellpadding='0' cellspacing='0' bgcolor='#1a1a1a' style='background:{$bodyBg};padding:32px 16px;'>
<tr><td align='center'>
<table width='620' cellpadding='0' cellspacing='0' bgcolor='#222222' style='background:{$cardBg};border-radius:16px;overflow:hidden;'>

  <tr><td style='background:{$dark};padding:28px 32px;text-align:center;border-bottom:3px solid {$primary};'>
    <img src='{$logoSrc}' alt='BLAYA' width='72' height='72' style='width:72px;height:72px;display:block;margin:0 auto 8px;background:#1a1a1a;padding:8px;border-radius:8px;'>
    <div style='font-size:11px;color:{$primary};letter-spacing:3px;text-transform:uppercase;font-family:Arial,sans-serif;'>Kutyapanzió</div>
  </td></tr>

  <tr><td style='background:{$primary};padding:14px 32px;text-align:center;'>
    <div style='font-size:15px;font-weight:700;color:{$dark};font-family:Arial,sans-serif;'>🐾 Köszönjük a foglalási kérelmet!</div>
  </td></tr>

  <tr><td style='padding:32px;'>
    <p style='font-size:15px;color:{$textLight};margin:0 0 16px;font-family:Arial,sans-serif;'>Kedves <strong style='color:{$primary};'>{$name}</strong>!</p>
    <p style='font-size:14px;color:{$textGray};line-height:1.7;margin:0 0 20px;font-family:Arial,sans-serif;'>
      Megkaptuk <strong style='color:{$textLight};'>{$dogName}</strong> foglalási kérelmét. Az alábbiakban összefoglaljuk a megadott adatokat:
    </p>

    <table width='100%' cellpadding='0' cellspacing='0' style='background:{$dark};border:1px solid {$border};border-radius:10px;overflow:hidden;margin-bottom:20px;'>
      <tr><td colspan='2' style='padding:12px 16px;background:{$border};font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:{$primary};font-family:Arial,sans-serif;'>Foglalás részletei</td></tr>
      <tr>
        <td style='padding:10px 16px;font-size:13px;color:{$textGray};width:140px;font-family:Arial,sans-serif;'><strong>Érkezés:</strong></td>
        <td style='padding:10px 16px;font-size:13px;color:{$textLight};font-family:Arial,sans-serif;'>{$dateFrom}</td>
      </tr>
      <tr style='border-top:1px solid {$border};'>
        <td style='padding:10px 16px;font-size:13px;color:{$textGray};font-family:Arial,sans-serif;'><strong>Távozás:</strong></td>
        <td style='padding:10px 16px;font-size:13px;color:{$textLight};font-family:Arial,sans-serif;'>{$dateTo}</td>
      </tr>
      <tr style='border-top:1px solid {$border};'>
        <td style='padding:10px 16px;font-size:13px;color:{$textGray};font-family:Arial,sans-serif;'><strong>Naponkénti<br>csomagok:</strong></td>
        <td style='padding:10px 16px;font-size:13px;color:{$textLight};font-family:Arial,sans-serif;'>{$packagesHtml}</td>
      </tr>
      <tr style='border-top:1px solid {$border};'>
        <td style='padding:10px 16px;font-size:13px;color:{$textGray};font-family:Arial,sans-serif;'><strong>Becsült végösszeg:</strong></td>
        <td style='padding:10px 16px;font-size:13px;font-weight:700;color:{$primary};font-family:Arial,sans-serif;'>{$totalFmt}</td>
      </tr>
      <tr style='border-top:1px solid {$border};'>
        <td style='padding:10px 16px;font-size:13px;color:{$textGray};font-family:Arial,sans-serif;'><strong>Foglaló (50%):</strong></td>
        <td style='padding:10px 16px;font-size:13px;font-weight:700;color:{$primary};font-family:Arial,sans-serif;'>{$depositFmt}</td>
      </tr>
      <tr style='border-top:1px solid {$border};'>
        <td style='padding:10px 16px;font-size:13px;color:{$textGray};font-family:Arial,sans-serif;'><strong>Maradék (távozáskor):</strong></td>
        <td style='padding:10px 16px;font-size:13px;color:{$textLight};font-family:Arial,sans-serif;'>{$remainderFmt}</td>
      </tr>
      <tr style='border-top:1px solid {$border};'>
        <td style='padding:10px 16px;font-size:13px;color:{$textGray};font-family:Arial,sans-serif;'><strong>Fizetés ütemezése:</strong></td>
        <td style='padding:10px 16px;font-size:13px;color:{$textLight};font-family:Arial,sans-serif;'>{$paymentTiming}</td>
      </tr>
      <tr style='border-top:1px solid {$border};'>
        <td style='padding:10px 16px;font-size:13px;color:{$textGray};font-family:Arial,sans-serif;'><strong>Fizetési mód:</strong></td>
        <td style='padding:10px 16px;font-size:13px;color:{$textLight};font-family:Arial,sans-serif;'>{$paymentMethod}</td>
      </tr>
    </table>

    <table width='100%' cellpadding='0' cellspacing='0' style='background:#2a1f00;border:1px solid {$primary};border-radius:10px;margin-bottom:24px;'>
      <tr><td style='padding:16px 20px;'>
        <div style='font-size:13px;font-weight:700;color:{$primary};margin-bottom:8px;font-family:Arial,sans-serif;'>⚠ Fontos tudnivaló</div>
        <div style='font-size:13px;color:{$textGray};line-height:1.7;font-family:Arial,sans-serif;'>
          A foglalási kérelem leadása <strong style='color:{$textLight};'>nem jelenti a foglalás véglegesítését.</strong><br>
          A foglalás akkor válik véglegessé, amikor az előzetes egyeztetés után a <strong style='color:{$primary};'>foglaló összege befizetésre kerül.</strong>
        </div>
      </td></tr>
    </table>

    <p style='font-size:14px;color:{$textGray};line-height:1.7;margin:0 0 8px;font-family:Arial,sans-serif;'>
      Hamarosan felvesszük Önnel a kapcsolatot a részletek egyeztetése céljából. Ha sürgős kérdése van, keressen minket bátran!
    </p>
    <p style='font-size:14px;color:{$textGray};line-height:1.7;margin:0 0 28px;font-family:Arial,sans-serif;'>
      Szeretettel várjuk <strong style='color:{$primary};'>{$dogName}</strong>-t! 🐾
    </p>

    <!-- ALÁÍRÁS -->
    <table width='100%' cellpadding='0' cellspacing='0' style='border-top:1px solid {$border};padding-top:20px;'>
      <tr><td style='padding-top:20px;'>
        <div style='font-size:16px;font-weight:700;color:{$textLight};font-family:Arial,sans-serif;'>Brkity Bernadett</div>
        <div style='font-size:13px;color:{$primary};margin-bottom:12px;font-family:Georgia,serif;font-style:italic;'>BLAYA Kutyapanzió – második otthon</div>
        <div style='font-size:12px;color:{$textGray};line-height:2;font-family:Arial,sans-serif;'>
          📍 Csávoly, Dózsa György u. 22.<br>
          📞 +36 30 159 2499<br>
          ✉ info@blaya.hu<br>
          🌐 www.blaya.hu
        </div>
      </td></tr>
    </table>

    <!-- SOCIAL -->
    <table width='100%' cellpadding='0' cellspacing='0' style='margin-top:24px;border-top:1px solid {$border};padding-top:20px;'>
      <tr><td style='padding-top:16px;'>
        <div style='font-size:11px;color:{$textGray};text-transform:uppercase;letter-spacing:2px;margin-bottom:12px;font-family:Arial,sans-serif;'>Kövess minket</div>
        <table cellpadding='0' cellspacing='0'>
          <tr>
            <td style='padding-right:10px;'>
              <a href='https://facebook.com/BlayaKutyapanzio' style='display:inline-block;background:{$border};color:{$textLight};font-size:12px;padding:8px 16px;border-radius:6px;text-decoration:none;font-family:Arial,sans-serif;'>📘 Facebook</a>
            </td>
            <td style='padding-right:10px;'>
              <a href='https://instagram.com/blaya.kutyapanzio' style='display:inline-block;background:{$border};color:{$textLight};font-size:12px;padding:8px 16px;border-radius:6px;text-decoration:none;font-family:Arial,sans-serif;'>📷 Instagram</a>
            </td>
            <td>
              <a href='https://tiktok.com/@blaya.kutyapanzio' style='display:inline-block;background:{$border};color:{$textLight};font-size:12px;padding:8px 16px;border-radius:6px;text-decoration:none;font-family:Arial,sans-serif;'>🎵 TikTok</a>
            </td>
          </tr>
        </table>
      </td></tr>
    </table>

  </td></tr>

  <tr><td style='background:{$dark};padding:16px 32px;text-align:center;border-top:1px solid {$border};'>
    <div style='font-size:11px;color:rgba(255,255,255,0.3);font-family:Arial,sans-serif;'>BLAYA Kutyapanzió · Csávoly, Dózsa György u. 22. · info@blaya.hu · +36 30 159 2499</div>
    <div style='font-size:10px;color:rgba(255,255,255,0.2);margin-top:4px;font-family:Arial,sans-serif;'>Ez egy automatikusan generált üzenet.</div>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>";

// ─── MIME összeállítás (inline logó CID-del + opcionális csatolmányok) ───
// Visszaad egy [Content-Type fejléc, törzs] párost. A HTML és a beágyazott logó
// egy multipart/related blokkba kerül; ha vannak csatolt fájlok, azt egy külső
// multipart/mixed burkolja.
function buildMessage($html, $logo, $attachments) {
    $relB = 'rel_' . md5(uniqid('', true));
    $related  = "--{$relB}\r\n";
    $related .= "Content-Type: text/html; charset=UTF-8\r\n";
    $related .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $related .= chunk_split(base64_encode($html)) . "\r\n";
    if ($logo) {
        $related .= "--{$relB}\r\n";
        $related .= "Content-Type: {$logo['type']}\r\n";
        $related .= "Content-Transfer-Encoding: base64\r\n";
        $related .= "Content-ID: <{$logo['cid']}>\r\n";
        $related .= "Content-Disposition: inline; filename=\"{$logo['name']}\"\r\n\r\n";
        $related .= $logo['content'] . "\r\n";
    }
    $related .= "--{$relB}--\r\n";

    if (empty($attachments)) {
        return ["Content-Type: multipart/related; boundary=\"{$relB}\"\r\n", $related];
    }

    $mixB = 'mix_' . md5(uniqid('', true));
    $body  = "--{$mixB}\r\n";
    $body .= "Content-Type: multipart/related; boundary=\"{$relB}\"\r\n\r\n";
    $body .= $related . "\r\n";
    foreach ($attachments as $att) {
        $body .= "--{$mixB}\r\n";
        $body .= "Content-Type: {$att['type']}; name=\"{$att['name']}\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$att['name']}\"\r\n\r\n";
        $body .= chunk_split($att['content']) . "\r\n";
    }
    $body .= "--{$mixB}--";
    return ["Content-Type: multipart/mixed; boundary=\"{$mixB}\"\r\n", $body];
}

$toBlaya   = 'info@blaya.hu';
$fromEmail = 'info@blaya.hu';
$fromName  = 'BLAYA Kutyapanzió';

list($ct1, $body1) = buildMessage($internalBody, $logoInline, $attachments);
$h1  = "From: {$fromName} <{$fromEmail}>\r\n";
$h1 .= "Reply-To: {$email}\r\n";
$h1 .= "MIME-Version: 1.0\r\n";
$h1 .= $ct1;

list($ct2, $body2) = buildMessage($autoBody, $logoInline, []);
$h2  = "From: {$fromName} <{$fromEmail}>\r\n";
$h2 .= "Reply-To: {$fromEmail}\r\n";
$h2 .= "MIME-Version: 1.0\r\n";
$h2 .= $ct2;

$sent1 = deliver($toBlaya, $internalSubject, $body1, $h1);
$sent2 = deliver($email,   $autoSubject,     $body2, $h2);

echo json_encode(['success' => $sent1 ? true : false]);
?>