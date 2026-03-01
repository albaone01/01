<?php
require_once __DIR__ . '/_bootstrap.php';

$mode = (string)($labelMode ?? '');
$pages = [
    'barcode_barang' => [
        'title' => 'Barcode Barang',
        'subtitle' => 'Cetak barcode produk untuk label rak atau kemasan.',
        'print_title' => 'Print Barcode Barang',
    ],
    'price_card_label' => [
        'title' => 'Price Card Label - Single Satuan',
        'subtitle' => 'Cetak kartu harga label berdasarkan satuan utama produk.',
        'print_title' => 'Print Price Card Label Single Satuan',
    ],
    'price_card_label_single' => [
        'title' => 'Price Card Label - Single Satuan',
        'subtitle' => 'Cetak kartu harga label berdasarkan satuan utama produk.',
        'print_title' => 'Print Price Card Label Single Satuan',
    ],
    'price_card_label_multi' => [
        'title' => 'Price Card Label - Multy Satuan',
        'subtitle' => 'Cetak kartu harga label dengan pilihan multi satuan per produk.',
        'print_title' => 'Print Price Card Label Multy Satuan',
    ],
    'price_card_folio' => [
        'title' => 'Price Card Kertas Folio',
        'subtitle' => 'Cetak kartu harga besar untuk display etalase.',
        'print_title' => 'Print Price Card Kertas Folio',
    ],
];

if (!isset($pages[$mode])) {
    http_response_code(404);
    exit('Mode halaman tidak valid.');
}

$jobMode = $mode === 'price_card_label' ? 'price_card_label_single' : $mode;
$isMultiSatuan = $jobMode === 'price_card_label_multi';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'save_job') {
    csrf_protect_json();
    try {
        $priceTier = (string)($_POST['price_tier'] ?? 'ecer');
        $judul = (string)($_POST['judul'] ?? '');
        $opsi = json_decode((string)($_POST['opsi_json'] ?? '{}'), true);
        $items = json_decode((string)($_POST['items_json'] ?? '[]'), true);
        if (!is_array($opsi)) $opsi = [];
        if (!is_array($items)) $items = [];

        $saved = lb_save_print_job($pos_db, $tokoId, $userId, $jobMode, $priceTier, $judul, $opsi, $items);
        lb_json_response(['ok' => true, 'msg' => 'Riwayat cetak berhasil disimpan.', 'data' => $saved]);
    } catch (Throwable $e) {
        lb_json_response(['ok' => false, 'msg' => $e->getMessage()], 400);
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$rows = lb_fetch_products($pos_db, $tokoId, $q, 700);
$recentJobs = lb_fetch_recent_jobs($pos_db, $tokoId, $jobMode, 12);
$csrf = csrf_token();
$meta = $pages[$mode];

require_once '../../../inc/header.php';
?>
<style>
    :root {
        --bg: #f5f7fb;
        --surface: #ffffff;
        --text: #0f172a;
        --muted: #64748b;
        --line: #e2e8f0;
        --primary: #0369a1;
        --primary-soft: #e0f2fe;
        --danger: #dc2626;
    }
    body { margin: 0; background: var(--bg); font-family: Inter, sans-serif; color: var(--text); }
    .wrap { max-width: 1320px; margin: 12px auto 24px; padding: 0 12px; }
    .grid { display: grid; gap: 12px; grid-template-columns: 1.8fr 1fr; }
    .card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 10px 28px rgba(2, 6, 23, .05); }
    .card .hd { padding: 14px 16px; border-bottom: 1px solid var(--line); }
    .card .bd { padding: 14px 16px; }
    h1 { margin: 0; font-size: 20px; font-weight: 700; }
    .sub { color: var(--muted); margin-top: 4px; font-size: 13px; }
    .toolbar { display: grid; grid-template-columns: 1.4fr .9fr .9fr .9fr .9fr .9fr auto auto; gap: 8px; align-items: end; }
    label { display: block; margin-bottom: 4px; font-size: 12px; color: #334155; font-weight: 700; }
    input[type=text], select, input[type=number] { width: 100%; border: 1px solid #cbd5e1; border-radius: 10px; padding: 9px 10px; font-size: 13px; box-sizing: border-box; }
    .btn { border: 1px solid var(--primary); background: var(--primary); color: #fff; border-radius: 10px; padding: 10px 12px; font-size: 13px; font-weight: 700; cursor: pointer; }
    .btn.secondary { border-color: #cbd5e1; background: #fff; color: #0f172a; }
    .btn.warn { border-color: var(--danger); background: #fff; color: var(--danger); }
    .metrics { margin-top: 10px; display: grid; gap: 8px; grid-template-columns: repeat(4,minmax(110px,1fr)); }
    .metric { border: 1px solid var(--line); border-radius: 10px; padding: 8px 10px; background: #f8fafc; }
    .metric small { color: var(--muted); display: block; font-size: 11px; }
    .metric strong { font-size: 17px; }
    .table-wrap { margin-top: 12px; border: 1px solid var(--line); border-radius: 12px; overflow: auto; max-height: 66vh; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th, td { border-bottom: 1px solid var(--line); padding: 8px; text-align: left; white-space: nowrap; }
    th { position: sticky; top: 0; background: #f8fafc; z-index: 2; color: #334155; }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
    tr:hover td { background: #fcfcfd; }
    .pill { display: inline-block; background: var(--primary-soft); color: var(--primary); border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 700; }
    .muted { color: var(--muted); }
    .danger { color: var(--danger); }
    .jobs { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
    .jobs li { border: 1px solid var(--line); border-radius: 10px; padding: 9px 10px; background: #fff; }
    .jobs .top { display: flex; justify-content: space-between; gap: 10px; }
    .jobs .top strong { font-size: 13px; }
    .jobs .top span { color: var(--muted); font-size: 11px; white-space: nowrap; }
    .jobs .meta { margin-top: 4px; color: #475569; font-size: 11px; }
    .sticky-actions { margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap; }
    .notice { margin-top: 10px; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 10px; padding: 10px; font-size: 12px; color: #334155; }
    @media (max-width: 1100px) {
        .grid { grid-template-columns: 1fr; }
        .toolbar { grid-template-columns: 1fr 1fr; }
    }
</style>

<div class="wrap">
    <div class="grid">
        <section class="card">
            <div class="hd">
                <h1><?= htmlspecialchars($meta['title']) ?></h1>
                <div class="sub"><?= htmlspecialchars($meta['subtitle']) ?></div>
            </div>
            <div class="bd">
                <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="toolbar">
                    <div>
                        <label>Cari Produk</label>
                        <input type="text" id="q" placeholder="Nama, SKU, atau barcode..." value="<?= htmlspecialchars($q) ?>">
                    </div>
                    <div>
                        <label>Tier Harga</label>
                        <select id="price_tier">
                            <option value="ecer">Harga Ecer</option>
                            <option value="grosir">Harga Grosir</option>
                            <option value="reseller">Harga Reseller</option>
                            <option value="member">Harga Member</option>
                            <option value="modal">Harga Modal</option>
                        </select>
                    </div>
                    <div>
                        <label>Lebar Label (mm)</label>
                        <input type="number" id="label_w" min="25" max="120" value="<?= $mode === 'price_card_folio' ? '90' : '55' ?>">
                    </div>
                    <div>
                        <label>Tinggi Label (mm)</label>
                        <input type="number" id="label_h" min="15" max="180" value="<?= $mode === 'price_card_folio' ? '55' : '32' ?>">
                    </div>
                    <div>
                        <label>Barcode Mode</label>
                        <select id="barcode_mode">
                            <option value="auto">Auto (Rekomendasi)</option>
                            <option value="b">Code128-B</option>
                            <option value="c">Code128-C</option>
                        </select>
                    </div>
                    <div>
                        <label>Lebar Bar (px)</label>
                        <input type="number" id="bar_w" min="0.6" max="2.4" step="0.1" value="1.2">
                    </div>
                    <div>
                        <label>Tinggi Bar (px)</label>
                        <input type="number" id="bar_h" min="28" max="120" step="1" value="46">
                    </div>
                    <button class="btn secondary" id="btn_filter">Terapkan</button>
                    <button class="btn secondary" id="btn_clear_search">Reset</button>
                </div>

                <div class="metrics">
                    <div class="metric"><small>Total Produk Tampil</small><strong id="m_total_produk">0</strong></div>
                    <div class="metric"><small>Produk Dipilih</small><strong id="m_selected">0</strong></div>
                    <div class="metric"><small>Total Copy</small><strong id="m_copy">0</strong></div>
                    <div class="metric"><small>Nilai Label</small><strong id="m_value">Rp 0</strong></div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:32px;"><input type="checkbox" id="check_all"></th>
                                <th>Produk</th>
                                <th>SKU</th>
                                <th>Barcode</th>
                                <th class="num">Ecer</th>
                                <th class="num">Grosir</th>
                                <th class="num">Reseller</th>
                                <th class="num">Member</th>
                                <th class="num">Modal</th>
                                <th class="num">Stok</th>
                                <?php if ($isMultiSatuan): ?><th>Satuan Cetak</th><?php endif; ?>
                                <th style="width:74px;">Copy</th>
                            </tr>
                        </thead>
                        <tbody id="tb_rows">
                        <?php if (!$rows): ?>
                            <tr><td colspan="<?= $isMultiSatuan ? 12 : 11 ?>" class="muted">Tidak ada produk untuk ditampilkan.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr
                                data-produk-id="<?= (int)$r['produk_id'] ?>"
                                data-nama="<?= htmlspecialchars((string)$r['nama_produk'], ENT_QUOTES) ?>"
                                data-sku="<?= htmlspecialchars((string)($r['sku'] ?? ''), ENT_QUOTES) ?>"
                                data-barcode="<?= htmlspecialchars((string)($r['barcode'] ?? ''), ENT_QUOTES) ?>"
                                data-unit-default="<?= htmlspecialchars((string)($r['unit_default'] ?? ''), ENT_QUOTES) ?>"
                                data-unit-options="<?= htmlspecialchars((string)($r['unit_options_json'] ?? '[]'), ENT_QUOTES) ?>"
                                data-harga-ecer="<?= (float)$r['harga_ecer'] ?>"
                                data-harga-grosir="<?= (float)$r['harga_grosir'] ?>"
                                data-harga-reseller="<?= (float)$r['harga_reseller'] ?>"
                                data-harga-member="<?= (float)$r['harga_member'] ?>"
                                data-harga-modal="<?= (float)$r['harga_modal'] ?>"
                            >
                                <td><input type="checkbox" class="row-check"></td>
                                <td><?= htmlspecialchars((string)$r['nama_produk']) ?></td>
                                <td class="muted"><?= htmlspecialchars((string)($r['sku'] ?: '-')) ?></td>
                                <td class="muted"><?= htmlspecialchars((string)($r['barcode'] ?: '-')) ?></td>
                                <td class="num"><?= number_format((float)$r['harga_ecer'], 0, ',', '.') ?></td>
                                <td class="num"><?= number_format((float)$r['harga_grosir'], 0, ',', '.') ?></td>
                                <td class="num"><?= number_format((float)$r['harga_reseller'], 0, ',', '.') ?></td>
                                <td class="num"><?= number_format((float)$r['harga_member'], 0, ',', '.') ?></td>
                                <td class="num"><?= number_format((float)$r['harga_modal'], 0, ',', '.') ?></td>
                                <td class="num <?= ((float)$r['stok_total'] <= 0 ? 'danger' : '') ?>"><?= number_format((float)$r['stok_total'], 2, ',', '.') ?></td>
                                <?php if ($isMultiSatuan): ?>
                                    <td>
                                        <?php $unitOpts = json_decode((string)($r['unit_options_json'] ?? '[]'), true); ?>
                                        <select class="unit-select">
                                            <?php foreach ((is_array($unitOpts) ? $unitOpts : []) as $opt): ?>
                                                <?php
                                                    $uLabel = (string)($opt['label'] ?? '');
                                                    if ($uLabel === '') continue;
                                                    $uMult = (float)($opt['multiplier'] ?? 1);
                                                    if ($uMult <= 0) $uMult = 1;
                                                ?>
                                                <option value="<?= htmlspecialchars($uLabel) ?>" data-multiplier="<?= htmlspecialchars((string)$uMult) ?>">
                                                    <?= htmlspecialchars($uLabel) ?> (x<?= rtrim(rtrim(number_format($uMult, 4, '.', ''), '0'), '.') ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                <?php endif; ?>
                                <td><input type="number" class="qty" min="1" max="500" value="1"></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="sticky-actions">
                    <button class="btn secondary" id="btn_pick_stock">Pilih stok > 0</button>
                    <button class="btn warn" id="btn_uncheck_all">Bersihkan Pilihan</button>
                    <button class="btn" id="btn_preview">Preview & Print</button>
                    <button class="btn secondary" id="btn_save">Simpan Riwayat</button>
                </div>
            </div>
        </section>

        <aside class="card">
            <div class="hd">
                <h1 style="font-size:16px;">Riwayat Cetak</h1>
                <div class="sub">Tersimpan per toko dan per jenis label.</div>
            </div>
            <div class="bd">
                <ul class="jobs">
                    <?php if (!$recentJobs): ?>
                        <li><span class="muted">Belum ada riwayat cetak.</span></li>
                    <?php else: foreach ($recentJobs as $j): ?>
                        <li>
                            <div class="top">
                                <strong><?= htmlspecialchars((string)$j['judul']) ?></strong>
                                <span><?= htmlspecialchars((string)$j['dibuat_pada']) ?></span>
                            </div>
                            <div class="meta">
                                Tier: <span class="pill"><?= htmlspecialchars((string)strtoupper((string)$j['price_tier'])) ?></span>
                                | Item: <?= (int)$j['total_item'] ?>
                                | Copy: <?= (int)$j['total_copy'] ?>
                                | Job#<?= (int)$j['job_id'] ?>
                            </div>
                        </li>
                    <?php endforeach; endif; ?>
                </ul>
                <div class="notice">
                    Semua data riwayat dipisah berdasarkan <strong>toko aktif</strong> dan tidak bercampur antar tenant POS SaaS.
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
const MODE = <?= json_encode($jobMode) ?>;
const TITLE = <?= json_encode($meta['print_title']) ?>;
const IS_MULTI_SATUAN = <?= $isMultiSatuan ? 'true' : 'false' ?>;
const csrfToken = document.getElementById('csrf_token').value;
const formatter = new Intl.NumberFormat('id-ID');
const rows = Array.from(document.querySelectorAll('#tb_rows tr')).filter(tr => tr.dataset && tr.dataset.produkId);

const el = {
  q: document.getElementById('q'),
  priceTier: document.getElementById('price_tier'),
  labelW: document.getElementById('label_w'),
  labelH: document.getElementById('label_h'),
  barcodeMode: document.getElementById('barcode_mode'),
  barW: document.getElementById('bar_w'),
  barH: document.getElementById('bar_h'),
  mTotal: document.getElementById('m_total_produk'),
  mSelected: document.getElementById('m_selected'),
  mCopy: document.getElementById('m_copy'),
  mValue: document.getElementById('m_value'),
  checkAll: document.getElementById('check_all')
};

function syncBarcodeControls(){
  const enabled = MODE === 'barcode_barang';
  ['barcodeMode','barW','barH'].forEach(k => {
    if (el[k]) el[k].disabled = !enabled;
  });
}

function rupiah(n){
  return 'Rp ' + formatter.format(Number(n || 0));
}

function selectedItems(){
  const tier = String(el.priceTier.value || 'ecer');
  return rows
    .filter(tr => tr.querySelector('.row-check')?.checked)
    .map(tr => {
      const qty = Math.max(1, Math.min(500, Number(tr.querySelector('.qty')?.value || 1)));
      let unitLabel = String(tr.dataset.unitDefault || '').trim();
      let unitMultiplier = 1;
      if (IS_MULTI_SATUAN) {
        const uSel = tr.querySelector('.unit-select');
        if (uSel) {
          unitLabel = String(uSel.value || unitLabel).trim();
          unitMultiplier = Number(uSel.selectedOptions?.[0]?.dataset?.multiplier || 1);
          if (!Number.isFinite(unitMultiplier) || unitMultiplier <= 0) unitMultiplier = 1;
        }
      }
      const basePrice = Number(tr.dataset['harga' + tier.charAt(0).toUpperCase() + tier.slice(1)] || 0);
      const price = basePrice * unitMultiplier;
      return {
        produk_id: Number(tr.dataset.produkId || 0),
        nama_produk: tr.dataset.nama || '',
        sku: tr.dataset.sku || '',
        barcode: tr.dataset.barcode || '',
        unit_label: unitLabel,
        unit_multiplier: unitMultiplier,
        harga: price,
        qty_copy: qty
      };
    })
    .filter(x => x.produk_id > 0);
}

function refreshMetrics(){
  const selected = selectedItems();
  const totalCopy = selected.reduce((a,b)=>a + Number(b.qty_copy || 0), 0);
  const totalValue = selected.reduce((a,b)=>a + ((Number(b.harga || 0) * Number(b.qty_copy || 0))), 0);
  el.mTotal.textContent = formatter.format(rows.length);
  el.mSelected.textContent = formatter.format(selected.length);
  el.mCopy.textContent = formatter.format(totalCopy);
  el.mValue.textContent = rupiah(totalValue);
}

function esc(v){
  return String(v || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function renderLabelHTML(items){
  const w = Math.max(25, Math.min(120, Number(el.labelW.value || 55)));
  const h = Math.max(15, Math.min(180, Number(el.labelH.value || 32)));
  const paper = MODE === 'price_card_folio' ? 'F4 portrait' : 'A4 portrait';
  const cards = [];
  items.forEach(it => {
    for(let i=0;i<Number(it.qty_copy || 1);i++){
      cards.push(it);
    }
  });

  const labelClass = MODE === 'barcode_barang' ? 'barcode' : (MODE === 'price_card_folio' ? 'folio' : 'price');
  const barW = Math.max(0.6, Math.min(2.4, Number(el.barW?.value || 1.2)));
  const barH = Math.max(28, Math.min(120, Number(el.barH?.value || 46)));
  const barcodeMode = String(el.barcodeMode?.value || 'auto');
  return `<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>${esc(TITLE)}</title>
  <style>
    @page { size: ${paper}; margin: 6mm; }
    body { margin: 0; font-family: Arial, sans-serif; color: #0f172a; }
    .sheet { display: flex; flex-wrap: wrap; gap: 2mm; align-content: flex-start; }
    .label { width: ${w}mm; height: ${h}mm; border: 1px solid #0f172a; border-radius: 2mm; padding: 2mm; box-sizing: border-box; overflow: hidden; }
    .name { font-size: ${MODE === 'price_card_folio' ? '12px' : '9px'}; font-weight: 700; line-height: 1.2; }
    .sku { font-size: 8px; color: #334155; margin-top: 1mm; }
    .price { font-size: ${MODE === 'price_card_folio' ? '19px' : '14px'}; font-weight: 700; margin-top: 1.6mm; }
    .barcode { display: flex; flex-direction: column; justify-content: space-between; }
    .barcode .bc-wrap { margin-top: 1mm; display: grid; gap: 1mm; justify-items: center; }
    .barcode .bc-svg { width: 100%; min-height: 11mm; display: flex; justify-content: center; align-items: center; overflow: hidden; }
    .barcode .bc-svg svg { display: block; width: 100%; height: 100%; }
    .barcode .bc-text { font-size: 9px; letter-spacing: .4px; text-align: center; }
    .price .bc-mini, .folio .bc-mini { margin-top: 1mm; font-size: 9px; color: #475569; }
    .folio .name { font-size: 14px; }
    .folio .price { font-size: 22px; }
  </style>
</head>
<body>
  <div class="sheet">
    ${cards.map(it => `
      <div class="label ${labelClass}">
        <div class="name">${esc(it.nama_produk)}</div>
        <div class="sku">SKU: ${esc(it.sku || '-')} | Sat: ${esc(it.unit_label || '-')}</div>
        <div class="price">${esc(rupiah(it.harga))}</div>
        ${
          MODE === 'barcode_barang'
            ? `<div class="bc-wrap"><div class="bc-svg" data-mode="${esc(barcodeMode)}" data-bar-w="${esc(barW)}" data-bar-h="${esc(barH)}" data-code="${esc(it.barcode || it.sku || it.produk_id)}"></div><div class="bc-text">${esc(it.barcode || '-')}</div></div>`
            : `<div class="bc-mini">BC: ${esc(it.barcode || '-')}</div>`
        }
      </div>
    `).join('')}
  </div>
  <script>
    const CODE128_PATTERNS = [
      "212222","222122","222221","121223","121322","131222","122213","122312","132212","221213","221312","231212",
      "112232","122132","122231","113222","123122","123221","223211","221132","221231","213212","223112","312131",
      "311222","321122","321221","312212","322112","322211","212123","212321","232121","111323","131123","131321",
      "112313","132113","132311","211313","231113","231311","112133","112331","132131","113123","113321","133121",
      "313121","211331","231131","213113","213311","213131","311123","311321","331121","312113","312311","332111",
      "314111","221411","431111","111224","111422","121124","121421","141122","141221","112214","112412","122114",
      "122411","142112","142211","241211","221114","413111","241112","134111","111242","121142","121241","114212",
      "124112","124211","411212","421112","421211","212141","214121","412121","111143","111341","131141","114113",
      "114311","411113","411311","113141","114131","311141","411131","211412","211214","211232","2331112"
    ];

    function code128BValues(raw) {
      const text = String(raw || '').replace(/[^\x20-\x7E]/g, '').slice(0, 60);
      if (!text) return null;
      const vals = [104];
      let checksum = 104;
      for (let i = 0; i < text.length; i++) {
        const code = text.charCodeAt(i) - 32;
        if (code < 0 || code > 94) continue;
        vals.push(code);
        checksum += code * (i + 1);
      }
      if (vals.length <= 1) return null;
      vals.push(checksum % 103);
      vals.push(106);
      return vals;
    }

    function code128CValues(raw) {
      const digits = String(raw || '').replace(/\D/g, '').slice(0, 80);
      if (!digits || (digits.length % 2 !== 0)) return null;
      if (digits.length < 4) return null;
      const vals = [105];
      let checksum = 105;
      let pos = 1;
      for (let i = 0; i < digits.length; i += 2) {
        const pair = Number(digits.slice(i, i + 2));
        if (!Number.isInteger(pair) || pair < 0 || pair > 99) return null;
        vals.push(pair);
        checksum += pair * pos;
        pos += 1;
      }
      vals.push(checksum % 103);
      vals.push(106);
      return vals;
    }

    function code128Values(raw, mode) {
      const m = String(mode || 'auto').toLowerCase();
      if (m === 'c') return code128CValues(raw) || code128BValues(raw);
      if (m === 'b') return code128BValues(raw);
      const c = code128CValues(raw);
      if (c) return c;
      return code128BValues(raw);
    }

    function code128Svg(raw, h = 46, moduleW = 1.25, mode = 'auto') {
      const vals = code128Values(raw, mode);
      if (!vals) return '';
      const quiet = 10;
      let modules = quiet * 2;
      for (const v of vals) {
        const p = CODE128_PATTERNS[v];
        if (!p) return '';
        for (let i = 0; i < p.length; i++) modules += Number(p[i]);
      }
      const w = Math.max(120, modules * moduleW);
      let x = quiet * moduleW;
      let bars = '';
      for (const v of vals) {
        const p = CODE128_PATTERNS[v];
        for (let i = 0; i < p.length; i++) {
          const width = Number(p[i]) * moduleW;
          if (i % 2 === 0) bars += '<rect x="' + x.toFixed(2) + '" y="0" width="' + width.toFixed(2) + '" height="' + h + '" fill="#000"/>';
          x += width;
        }
      }
      return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + w.toFixed(2) + ' ' + h + '" preserveAspectRatio="none" aria-label="CODE128">' + bars + '</svg>';
    }

    window.onload = () => {
      document.querySelectorAll('.bc-svg').forEach(el => {
        const code = el.getAttribute('data-code') || '';
        const mode = el.getAttribute('data-mode') || 'auto';
        const barW = Number(el.getAttribute('data-bar-w') || 1.25);
        const barH = Number(el.getAttribute('data-bar-h') || 46);
        const svg = code128Svg(code, barH, barW, mode);
        el.innerHTML = svg || '<div style="font-size:10px;color:#b91c1c">Barcode tidak valid</div>';
      });
      window.print();
    };
  <\/script>
</body>
</html>`;
}

function openPreview(){
  const items = selectedItems();
  if(!items.length){
    alert('Pilih minimal satu produk.');
    return;
  }
  const win = window.open('', '_blank');
  if(!win){
    alert('Popup diblokir browser. Izinkan popup untuk halaman ini.');
    return;
  }
  win.document.open();
  win.document.write(renderLabelHTML(items));
  win.document.close();
}

async function saveHistory(){
  const items = selectedItems();
  if(!items.length){
    alert('Pilih minimal satu produk sebelum simpan riwayat.');
    return;
  }
  const now = new Date();
  const judul = `${TITLE} - ${now.toLocaleString('id-ID')}`;
  const opts = {
    label_w_mm: Number(el.labelW.value || 0),
    label_h_mm: Number(el.labelH.value || 0),
    barcode_mode: String(el.barcodeMode?.value || 'auto'),
    bar_width_px: Number(el.barW?.value || 1.2),
    bar_height_px: Number(el.barH?.value || 46),
    query: String(el.q.value || ''),
    mode: MODE
  };

  const fd = new FormData();
  fd.set('action', 'save_job');
  fd.set('csrf_token', csrfToken);
  fd.set('price_tier', String(el.priceTier.value || 'ecer'));
  fd.set('judul', judul);
  fd.set('opsi_json', JSON.stringify(opts));
  fd.set('items_json', JSON.stringify(items.map(it => ({
    produk_id: it.produk_id,
    qty_copy: it.qty_copy,
    unit_label: it.unit_label,
    unit_multiplier: it.unit_multiplier
  }))));

  try {
    const r = await fetch(window.location.href, { method: 'POST', body: fd });
    const d = await r.json();
    if(!r.ok || !d.ok) throw new Error(d.msg || `HTTP ${r.status}`);
    alert(`${d.msg} Job #${d.data?.job_id || '-'} | Item ${d.data?.total_item || 0} | Copy ${d.data?.total_copy || 0}`);
    window.location.reload();
  } catch (e) {
    alert(e.message || 'Gagal menyimpan riwayat.');
  }
}

rows.forEach(tr => {
  tr.querySelector('.row-check')?.addEventListener('change', refreshMetrics);
  tr.querySelector('.qty')?.addEventListener('input', refreshMetrics);
  tr.querySelector('.unit-select')?.addEventListener('change', refreshMetrics);
});
el.priceTier.addEventListener('change', refreshMetrics);
el.checkAll?.addEventListener('change', () => {
  rows.forEach(tr => {
    const ck = tr.querySelector('.row-check');
    if (ck) ck.checked = el.checkAll.checked;
  });
  refreshMetrics();
});

document.getElementById('btn_pick_stock')?.addEventListener('click', () => {
  rows.forEach(tr => {
    const stokCell = tr.children[9];
    const stok = Number(String(stokCell?.textContent || '0').replace(/\./g, '').replace(',', '.'));
    const ck = tr.querySelector('.row-check');
    if(ck) ck.checked = stok > 0;
  });
  refreshMetrics();
});
document.getElementById('btn_uncheck_all')?.addEventListener('click', () => {
  rows.forEach(tr => {
    const ck = tr.querySelector('.row-check');
    if (ck) ck.checked = false;
  });
  if (el.checkAll) el.checkAll.checked = false;
  refreshMetrics();
});
document.getElementById('btn_preview')?.addEventListener('click', openPreview);
document.getElementById('btn_save')?.addEventListener('click', saveHistory);

document.getElementById('btn_filter')?.addEventListener('click', () => {
  const qs = new URLSearchParams(window.location.search);
  const q = String(el.q.value || '').trim();
  if (q) qs.set('q', q); else qs.delete('q');
  window.location.search = qs.toString();
});
document.getElementById('btn_clear_search')?.addEventListener('click', () => {
  const qs = new URLSearchParams(window.location.search);
  qs.delete('q');
  window.location.search = qs.toString();
});

refreshMetrics();
syncBarcodeControls();
</script>
