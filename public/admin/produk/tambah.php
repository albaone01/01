<?php
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/header.php';
require_once '../../../inc/csrf.php';

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if (!$tokoId) die('Sesi toko tidak valid');

$csrfToken = csrf_token();

function fetch_all_stmt(mysqli_stmt $stmt): array {
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

$stmt = $pos_db->prepare("SELECT kategori_id,nama_kategori FROM kategori_produk WHERE toko_id=? AND deleted_at IS NULL ORDER BY nama_kategori");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$kategori = fetch_all_stmt($stmt);
$stmt->close();

$stmt = $pos_db->prepare("SELECT supplier_id,nama_supplier FROM supplier WHERE toko_id=? ORDER BY nama_supplier");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$supplier = fetch_all_stmt($stmt);
$stmt->close();

$stmt = $pos_db->prepare("SELECT gudang_id,nama_gudang FROM gudang WHERE toko_id=? AND aktif=1 AND deleted_at IS NULL ORDER BY CASE WHEN nama_gudang='Gudang Utama' THEN 0 ELSE 1 END, nama_gudang");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$gudang = fetch_all_stmt($stmt);
$stmt->close();

$stmt = $pos_db->prepare("SELECT produk_id,sku,barcode FROM produk WHERE toko_id=? AND deleted_at IS NULL");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$produkList = fetch_all_stmt($stmt);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .wrap { max-width: 920px; margin: 10px auto; }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap:8px; }
        .full { grid-column: 1 / -1; }
        label { display:block; font-weight:700; font-size:12px; color:#334155; margin-bottom:2px; }
        input,select { width:100%; padding:7px 8px; border:1px solid #cbd5e1; border-radius:8px; font-size:12px; }
        .row-actions { display:flex; gap:6px; margin-top:8px; flex-wrap:wrap; }
        .error-banner { display:none; border:1px solid #fecaca; background:#fff1f2; color:#b91c1c; padding:7px 8px; border-radius:8px; margin-bottom:8px; font-size:12px; }
        .field-error { color:#b91c1c; min-height:14px; font-size:12px; display:block; margin-top:4px; }
        .invalid { border-color:#fca5a5; background:#fff1f2; }
        .hint { color:#64748b; font-size:12px; }
        .collapse { display:none; border:1px dashed #7dd3fc; background:#f0f9ff; padding:8px; border-radius:8px; margin-top:6px; }
        .collapse.show { display:block; }
        .preview { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:6px; }
        .preview .box { border:1px solid #e2e8f0; border-radius:8px; padding:6px; font-size:11px; color:#64748b; background:#f8fafc; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h2 style="margin-top:0;">Tambah Produk</h2>
        <form id="formTambah" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
            <div id="errorBanner" class="error-banner"></div>
            <div class="grid">
                <div>
                    <label>SKU</label>
                    <input name="sku" required>
                    <div class="row-actions"><button type="button" id="btnSku">Auto SKU</button></div>
                    <small class="field-error" data-for="sku"></small>
                </div>
                <div>
                    <label>Barcode</label>
                    <input name="barcode" id="barcode" placeholder="Scan barcode di sini">
                    <small class="field-error" data-for="barcode"></small>
                </div>
                <div class="full">
                    <label>Nama Produk</label>
                    <input name="nama_produk" required>
                    <small class="field-error" data-for="nama_produk"></small>
                </div>
                <div>
                    <label>Supplier</label>
                    <select name="supplier_id" required>
                        <option value="">-- Pilih Supplier --</option>
                        <?php foreach($supplier as $s): ?>
                            <option value="<?=$s['supplier_id']?>"><?=htmlspecialchars($s['nama_supplier'])?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-error" data-for="supplier_id"></small>
                </div>
                <div>
                    <label>Kategori</label>
                    <select name="kategori_id" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach($kategori as $k): ?>
                            <option value="<?=$k['kategori_id']?>"><?=htmlspecialchars($k['nama_kategori'])?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-error" data-for="kategori_id"></small>
                </div>
                <div>
                    <label>Satuan</label>
                    <input name="satuan" required placeholder="PCS / BOX / BOTOL">
                    <small class="field-error" data-for="satuan"></small>
                </div>
                <div>
                    <label>Harga Modal</label>
                    <input type="number" name="harga_modal" min="0" step="0.01" required>
                    <small class="field-error" data-for="harga_modal"></small>
                </div>
                <div>
                    <label>Harga Ecer</label>
                    <input type="number" name="harga_ecer" min="0" step="0.01" required>
                </div>
                <div>
                    <label>Harga Grosir</label>
                    <input type="number" name="harga_grosir" min="0" step="0.01" required>
                </div>
                <div>
                    <label>Harga Reseller</label>
                    <input type="number" name="harga_reseller" min="0" step="0.01" required>
                </div>
                <div>
                    <label>Harga Member</label>
                    <input type="number" name="harga_member" min="0" step="0.01" required>
                </div>
                <div class="full">
                    <label>Preview Harga + Pajak</label>
                    <div class="preview">
                        <div class="box">Ecer: <strong id="pv_ecer">Rp 0</strong></div>
                        <div class="box">Grosir: <strong id="pv_grosir">Rp 0</strong></div>
                        <div class="box">Reseller: <strong id="pv_reseller">Rp 0</strong></div>
                        <div class="box">Member: <strong id="pv_member">Rp 0</strong></div>
                    </div>
                </div>
                <div>
                    <label>Min Stok</label>
                    <input type="number" name="min_stok" min="0" value="0">
                    <small class="field-error" data-for="min_stok"></small>
                </div>
                <div>
                    <label>Pajak (%)</label>
                    <input type="number" name="pajak_persen" min="0" max="100" step="0.01" value="0">
                </div>
                <div>
                    <label><input type="checkbox" name="is_jasa" id="is_jasa" value="1"> Produk Jasa (tanpa stok)</label>
                </div>
                <div>
                    <label><input type="checkbox" name="is_konsinyasi" value="1"> Produk Konsinyasi</label>
                </div>
                <div class="full">
                    <label><input type="checkbox" name="isi_stok_awal" id="isi_stok_awal" value="1"> Isi stok awal?</label>
                    <div id="stokAwal" class="collapse">
                        <div class="grid">
                            <div>
                                <label>Gudang</label>
                                <select name="gudang_id" id="gudang_id">
                                    <option value="">-- Pilih Gudang --</option>
                                    <?php foreach($gudang as $g): ?>
                                        <option value="<?=$g['gudang_id']?>"><?=htmlspecialchars($g['nama_gudang'])?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="field-error" data-for="gudang_id"></small>
                            </div>
                            <div>
                                <label>Qty</label>
                                <input type="number" name="stok_awal_qty" id="stok_awal_qty" min="1" step="1" value="1">
                                <small class="field-error" data-for="stok_awal_qty"></small>
                            </div>
                            <div>
                                <label>Harga Modal Stok Awal</label>
                                <input type="number" name="stok_awal_harga_modal" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="full">
                    <label>Foto</label>
                    <input type="file" name="foto" accept="image/*">
                    <div class="hint">MIME check aktif, nama file UUID, resize/compress, SVG/PHP berbahaya ditolak.</div>
                </div>
                <div class="full">
                    <label><input type="checkbox" name="aktif" value="1" checked> Aktif</label>
                </div>
            </div>
            <div class="row-actions">
                <button type="submit" id="btnSave">Simpan</button>
                <a href="master_barang.php">Kembali</a>
            </div>
        </form>
    </div>
</div>
<script>
const form = document.getElementById('formTambah');
const existing = <?=json_encode($produkList, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const gudangData = <?=json_encode($gudang, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;

function n(v){ const x=parseFloat(v); return Number.isFinite(x)?x:0; }
function rupiah(v){ return new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(n(v)))); }
function clearErr(){
  document.querySelectorAll('.field-error').forEach(e=>e.textContent='');
  document.querySelectorAll('.invalid').forEach(e=>e.classList.remove('invalid'));
  const b=document.getElementById('errorBanner'); b.style.display='none'; b.textContent='';
}
function fieldErr(name,msg){
  const t=form.querySelector(`.field-error[data-for="${name}"]`); if(t) t.textContent=msg;
  const f=form[name] || document.getElementById(name); if(f) f.classList.add('invalid');
}
function banner(msg){ const b=document.getElementById('errorBanner'); b.textContent=msg; b.style.display='block'; }
function updatePreview(){
  const tax=Math.max(0,n(form.pajak_persen.value))/100;
  ['ecer','grosir','reseller','member'].forEach(k=>{
    const val=n(form[`harga_${k}`].value)*(1+tax);
    const el=document.getElementById(`pv_${k}`); if(el) el.textContent='Rp '+rupiah(val);
  });
}
function toggleStok(){
  const jasa = form.is_jasa.checked;
  const isi = form.isi_stok_awal;
  const box = document.getElementById('stokAwal');
  if (jasa) { isi.checked=false; form.min_stok.value=0; }
  isi.disabled = jasa;
  box.classList.toggle('show', !jasa && isi.checked);
}
function validate(){
  clearErr();
  const errs=[];
  const sku=(form.sku.value||'').trim().toLowerCase();
  const bc=(form.barcode.value||'').trim().toLowerCase();
  const hm=n(form.harga_modal.value), he=n(form.harga_ecer.value), hg=n(form.harga_grosir.value), hr=n(form.harga_reseller.value), hmbr=n(form.harga_member.value);
  const min=parseInt(form.min_stok.value||'0',10);

  if(!form.nama_produk.value.trim()){ errs.push('Nama produk wajib diisi'); fieldErr('nama_produk','Wajib'); }
  if(!sku){ errs.push('SKU wajib diisi'); fieldErr('sku','Wajib'); }
  if(!form.supplier_id.value){ errs.push('Supplier wajib dipilih'); fieldErr('supplier_id','Wajib'); }
  if(!form.kategori_id.value){ errs.push('Kategori wajib dipilih'); fieldErr('kategori_id','Wajib'); }
  if(!form.satuan.value.trim()){ errs.push('Satuan wajib diisi'); fieldErr('satuan','Wajib'); }
  if(min<0){ errs.push('Min stok tidak boleh negatif'); fieldErr('min_stok','Tidak boleh negatif'); }
  if(hm<0){ errs.push('Harga modal tidak valid'); fieldErr('harga_modal','Tidak valid'); }
  if(he<hm||hg<hm||hr<hm||hmbr<hm){ errs.push('Harga jual tidak boleh < harga modal'); ['harga_ecer','harga_grosir','harga_reseller','harga_member'].forEach(x=>fieldErr(x,'>= harga modal')); }

  if(sku && existing.some(p=>String(p.sku||'').toLowerCase()===sku)){ errs.push('SKU sudah digunakan'); fieldErr('sku','Duplikat'); }
  if(bc && existing.some(p=>String(p.barcode||'').toLowerCase()===bc)){ errs.push('Barcode sudah digunakan'); fieldErr('barcode','Duplikat'); }

  if(!form.is_jasa.checked && form.isi_stok_awal.checked){
    if(!gudangData.length){ errs.push('Belum ada gudang aktif'); }
    if(!form.gudang_id.value){ errs.push('Gudang stok awal wajib dipilih'); fieldErr('gudang_id','Wajib'); }
    if(parseInt(form.stok_awal_qty.value||'0',10)<=0){ errs.push('Qty stok awal harus > 0'); fieldErr('stok_awal_qty','> 0'); }
  }

  if(errs.length){ banner(errs[0]); return false; }
  return true;
}

document.getElementById('btnSku').addEventListener('click', ()=>{
  const base=(form.nama_produk.value||'PRD').toUpperCase().replace(/[^A-Z0-9]+/g,'').slice(0,6)||'PRD';
  form.sku.value=base+Date.now().toString().slice(-6);
});
form.isi_stok_awal.addEventListener('change', toggleStok);
form.is_jasa.addEventListener('change', toggleStok);
['harga_ecer','harga_grosir','harga_reseller','harga_member','pajak_persen'].forEach(k=>form[k].addEventListener('input',updatePreview));
form.harga_modal.addEventListener('input',updatePreview);
form.barcode.focus();
toggleStok();
updatePreview();

form.addEventListener('submit', async (e)=>{
  e.preventDefault();
  if(!validate()) return;
  const btn=document.getElementById('btnSave');
  btn.disabled=true; btn.textContent='Menyimpan...';
  const fd=new FormData(form);
  fd.set('aktif', form.aktif.checked ? 1 : 0);
  fd.set('is_jasa', form.is_jasa.checked ? 1 : 0);
  fd.set('is_konsinyasi', form.is_konsinyasi.checked ? 1 : 0);
  if(!form.isi_stok_awal.checked){ fd.set('isi_stok_awal',0); fd.delete('gudang_id'); fd.delete('stok_awal_qty'); fd.delete('stok_awal_harga_modal'); }
  try{
    const r=await fetch('../../../api/produk_save.php',{method:'POST',body:fd});
    const d=await r.json();
    if(!r.ok || !d.ok) throw new Error(d.msg||`HTTP ${r.status}`);
    location.href='master_barang.php';
  }catch(err){
    banner(err.message||'Gagal menyimpan');
  }finally{
    btn.disabled=false; btn.textContent='Simpan';
  }
});
</script>
</body>
</html>
