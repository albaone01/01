<?php
require_once __DIR__ . '/_bootstrap.php';

$q = trim((string)($_GET['q'] ?? ''));
$rows = lb_fetch_products_harga_naik($pos_db, $tokoId, $q, 900);
$csrf = csrf_token();

require_once '../../../inc/header.php';
?>
<style>
    :root { --bg:#f5f7fb; --surface:#fff; --line:#e2e8f0; --text:#0f172a; --muted:#64748b; --primary:#0369a1; --danger:#dc2626; }
    body{margin:0;background:var(--bg);font-family:Inter,sans-serif;color:var(--text)}
    .wrap{max-width:1320px;margin:12px auto 24px;padding:0 12px}
    .card{background:var(--surface);border:1px solid var(--line);border-radius:14px;box-shadow:0 10px 28px rgba(2,6,23,.05)}
    .hd{padding:14px 16px;border-bottom:1px solid var(--line)}
    .bd{padding:14px 16px}
    h1{margin:0;font-size:20px}
    .sub{margin-top:4px;color:var(--muted);font-size:13px}
    .toolbar{display:grid;grid-template-columns:1.4fr .9fr .9fr .9fr auto auto;gap:8px;align-items:end}
    label{display:block;margin-bottom:4px;font-size:12px;font-weight:700;color:#334155}
    input[type=text],select,input[type=number]{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:9px 10px;font-size:13px;box-sizing:border-box}
    .btn{border:1px solid var(--primary);background:var(--primary);color:#fff;border-radius:10px;padding:10px 12px;font-size:13px;font-weight:700;cursor:pointer}
    .btn.secondary{border-color:#cbd5e1;background:#fff;color:#0f172a}
    .metrics{margin-top:10px;display:grid;gap:8px;grid-template-columns:repeat(4,minmax(110px,1fr))}
    .metric{border:1px solid var(--line);border-radius:10px;padding:8px 10px;background:#f8fafc}
    .metric small{display:block;color:var(--muted);font-size:11px}
    .metric strong{font-size:17px}
    .table-wrap{margin-top:12px;border:1px solid var(--line);border-radius:12px;overflow:auto;max-height:70vh}
    table{width:100%;border-collapse:collapse;font-size:12px}
    th,td{border-bottom:1px solid var(--line);padding:8px;text-align:left;white-space:nowrap}
    th{position:sticky;top:0;background:#f8fafc;z-index:2;color:#334155}
    .num{text-align:right;font-variant-numeric:tabular-nums}
    .up{color:var(--danger);font-weight:700}
    .empty{padding:18px;text-align:center;color:var(--muted)}
    @media(max-width:1100px){.toolbar{grid-template-columns:1fr 1fr}.metrics{grid-template-columns:1fr 1fr}}
</style>

<div class="wrap">
    <section class="card">
        <div class="hd">
            <h1>Print Harga Naik</h1>
            <div class="sub">Deteksi produk dengan harga beli terbaru lebih tinggi dari harga beli sebelumnya, lalu cetak label rak.</div>
        </div>
        <div class="bd">
            <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="toolbar">
                <div>
                    <label>Cari Produk</label>
                    <input type="text" id="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nama, SKU, barcode...">
                </div>
                <div>
                    <label>Tier Harga Label</label>
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
                    <input type="number" id="label_w" min="35" max="120" value="55">
                </div>
                <div>
                    <label>Tinggi Label (mm)</label>
                    <input type="number" id="label_h" min="18" max="100" value="34">
                </div>
                <button class="btn secondary" id="btn_filter">Terapkan</button>
                <button class="btn secondary" id="btn_print">Preview & Print</button>
            </div>

            <div class="metrics">
                <div class="metric"><small>Total Produk Harga Naik</small><strong id="m_total">0</strong></div>
                <div class="metric"><small>Produk Dipilih</small><strong id="m_selected">0</strong></div>
                <div class="metric"><small>Total Copy</small><strong id="m_copy">0</strong></div>
                <div class="metric"><small>Rata-rata Kenaikan</small><strong id="m_avg_up">Rp 0</strong></div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width:32px;"><input type="checkbox" id="check_all"></th>
                        <th>Produk</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th class="num">Beli Sebelum</th>
                        <th class="num">Beli Akhir</th>
                        <th class="num">Naik</th>
                        <th class="num">%</th>
                        <th class="num">Ecer</th>
                        <th class="num">Grosir</th>
                        <th class="num">Reseller</th>
                        <th class="num">Member</th>
                        <th class="num">Copy</th>
                    </tr>
                    </thead>
                    <tbody id="tb_rows">
                    <?php if (!$rows): ?>
                        <tr><td colspan="13" class="empty">Belum ada produk dengan harga beli naik.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <?php
                            $sebelum = (float)$r['harga_beli_sebelum'];
                            $akhir = (float)$r['harga_beli_akhir'];
                            $naik = $akhir - $sebelum;
                            $pct = $sebelum > 0 ? ($naik / $sebelum) * 100 : 0;
                        ?>
                        <tr
                            data-produk-id="<?= (int)$r['produk_id'] ?>"
                            data-nama="<?= htmlspecialchars((string)$r['nama_produk'], ENT_QUOTES) ?>"
                            data-sku="<?= htmlspecialchars((string)($r['sku'] ?? ''), ENT_QUOTES) ?>"
                            data-barcode="<?= htmlspecialchars((string)($r['barcode'] ?? ''), ENT_QUOTES) ?>"
                            data-harga-ecer="<?= (float)$r['harga_ecer'] ?>"
                            data-harga-grosir="<?= (float)$r['harga_grosir'] ?>"
                            data-harga-reseller="<?= (float)$r['harga_reseller'] ?>"
                            data-harga-member="<?= (float)$r['harga_member'] ?>"
                            data-harga-modal="<?= (float)$r['harga_modal'] ?>"
                            data-beli-sebelum="<?= $sebelum ?>"
                            data-beli-akhir="<?= $akhir ?>"
                        >
                            <td><input type="checkbox" class="row-check"></td>
                            <td><?= htmlspecialchars((string)$r['nama_produk']) ?></td>
                            <td><?= htmlspecialchars((string)($r['sku'] ?: '-')) ?></td>
                            <td><?= htmlspecialchars((string)($r['barcode'] ?: '-')) ?></td>
                            <td class="num"><?= number_format($sebelum, 0, ',', '.') ?></td>
                            <td class="num"><?= number_format($akhir, 0, ',', '.') ?></td>
                            <td class="num up"><?= number_format($naik, 0, ',', '.') ?></td>
                            <td class="num up"><?= number_format($pct, 2, ',', '.') ?>%</td>
                            <td class="num"><?= number_format((float)$r['harga_ecer'], 0, ',', '.') ?></td>
                            <td class="num"><?= number_format((float)$r['harga_grosir'], 0, ',', '.') ?></td>
                            <td class="num"><?= number_format((float)$r['harga_reseller'], 0, ',', '.') ?></td>
                            <td class="num"><?= number_format((float)$r['harga_member'], 0, ',', '.') ?></td>
                            <td class="num"><input type="number" class="qty" min="1" max="500" value="1" style="width:66px"></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script>
const nf = new Intl.NumberFormat('id-ID');
const rows = Array.from(document.querySelectorAll('#tb_rows tr')).filter(tr => tr.dataset && tr.dataset.produkId);
const el = {
  q: document.getElementById('q'),
  tier: document.getElementById('price_tier'),
  w: document.getElementById('label_w'),
  h: document.getElementById('label_h'),
  checkAll: document.getElementById('check_all'),
  mTotal: document.getElementById('m_total'),
  mSelected: document.getElementById('m_selected'),
  mCopy: document.getElementById('m_copy'),
  mAvgUp: document.getElementById('m_avg_up')
};

function rp(v){ return 'Rp ' + nf.format(Number(v || 0)); }
function priceByTier(tr, tier){
  const key = 'harga' + tier.charAt(0).toUpperCase() + tier.slice(1);
  return Number(tr.dataset[key] || 0);
}
function selectedItems(){
  const tier = String(el.tier.value || 'ecer');
  return rows.filter(tr => tr.querySelector('.row-check')?.checked).map(tr => ({
      produk_id: Number(tr.dataset.produkId || 0),
      nama_produk: tr.dataset.nama || '',
      sku: tr.dataset.sku || '',
      barcode: tr.dataset.barcode || '',
      harga: priceByTier(tr, tier),
      qty_copy: Math.max(1, Math.min(500, Number(tr.querySelector('.qty')?.value || 1))),
      beli_sebelum: Number(tr.dataset.beliSebelum || 0),
      beli_akhir: Number(tr.dataset.beliAkhir || 0)
  })).filter(x => x.produk_id > 0);
}
function refreshMetrics(){
  const selected = selectedItems();
  const copy = selected.reduce((a,b)=>a + b.qty_copy, 0);
  const avgUp = rows.length
    ? rows.reduce((a,tr)=>a + (Number(tr.dataset.beliAkhir||0) - Number(tr.dataset.beliSebelum||0)), 0) / rows.length
    : 0;
  el.mTotal.textContent = nf.format(rows.length);
  el.mSelected.textContent = nf.format(selected.length);
  el.mCopy.textContent = nf.format(copy);
  el.mAvgUp.textContent = rp(avgUp);
}
function esc(v){ return String(v||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

function buildPrintHTML(items){
  const w = Math.max(35, Math.min(120, Number(el.w.value || 55)));
  const h = Math.max(18, Math.min(100, Number(el.h.value || 34)));
  const cards = [];
  items.forEach(it => { for(let i=0;i<it.qty_copy;i++) cards.push(it); });
  return `<!doctype html><html><head><meta charset="utf-8"><title>Print Harga Naik</title>
  <style>
    @page{size:A4 portrait;margin:6mm}
    body{margin:0;font-family:Arial,sans-serif;color:#111827}
    .sheet{display:flex;flex-wrap:wrap;gap:2mm}
    .card{width:${w}mm;height:${h}mm;border:1px solid #111827;border-radius:2mm;padding:2mm;box-sizing:border-box;overflow:hidden}
    .name{font-size:10px;font-weight:700;line-height:1.2}
    .sku{font-size:8px;color:#475569;margin-top:1mm}
    .price{font-size:16px;font-weight:700;margin-top:1.2mm}
    .up{font-size:9px;color:#b91c1c;margin-top:1mm}
  </style></head><body><div class="sheet">
  ${cards.map(it => `<div class="card">
      <div class="name">${esc(it.nama_produk)}</div>
      <div class="sku">SKU: ${esc(it.sku || '-')} | BC: ${esc(it.barcode || '-')}</div>
      <div class="price">${esc(rp(it.harga))}</div>
      <div class="up">Harga beli naik: ${esc(rp(it.beli_sebelum))} → ${esc(rp(it.beli_akhir))}</div>
  </div>`).join('')}
  </div></body></html>`;
}

function previewPrint(){
  const selected = selectedItems();
  if(!selected.length){ alert('Pilih minimal 1 produk.'); return; }
  const win = window.open('', '_blank');
  if(!win){ alert('Popup diblokir browser. Izinkan popup lalu coba lagi.'); return; }
  win.document.open();
  win.document.write(buildPrintHTML(selected));
  win.document.close();
  setTimeout(()=>win.print(), 240);
}

document.getElementById('btn_filter').addEventListener('click', ()=>{
  const q = (el.q.value || '').trim();
  window.location = '?q=' + encodeURIComponent(q);
});
document.getElementById('btn_print').addEventListener('click', previewPrint);
el.checkAll?.addEventListener('change', ()=>{ rows.forEach(tr => { const c = tr.querySelector('.row-check'); if(c) c.checked = el.checkAll.checked; }); refreshMetrics(); });
rows.forEach(tr => {
  tr.querySelector('.row-check')?.addEventListener('change', refreshMetrics);
  tr.querySelector('.qty')?.addEventListener('input', refreshMetrics);
});
refreshMetrics();
</script>
