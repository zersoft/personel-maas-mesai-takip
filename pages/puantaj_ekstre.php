<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Puantaj Ekstresi';

// Personel listesi
try { $personeller = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif=1 ORDER BY ad_soyad")->fetchAll(); } catch (Throwable $e) { $personeller = []; }

$defaultPersonel = !empty($personeller) ? (int)$personeller[0]['id'] : 0;
$personelId = isset($_GET['personel_id']) ? (int)$_GET['personel_id'] : $defaultPersonel;
$mode = (isset($_GET['mode']) && $_GET['mode']==='tarih') ? 'tarih' : 'donem';
$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : (int)date('n');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');
$baslangic = $_GET['baslangic'] ?? date('Y-m-01');
$bitis = $_GET['bitis'] ?? date('Y-m-t');

$rows = [];
try {
    if ($personelId > 0) {
        if ($mode === 'donem') {
            $st = $pdo->prepare("SELECT id, tarih, durum, saat, aciklama FROM puantaj WHERE personel_id=? AND MONTH(tarih)=? AND YEAR(tarih)=? ORDER BY tarih");
            $st->execute([$personelId, $ay, $yil]);
        } else {
            $st = $pdo->prepare("SELECT id, tarih, durum, saat, aciklama FROM puantaj WHERE personel_id=? AND tarih BETWEEN ? AND ? ORDER BY tarih");
            $st->execute([$personelId, $baslangic, $bitis]);
        }
        $rows = $st->fetchAll();
    }
} catch (Throwable $e) { $rows = []; }

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="bi bi-journal-text"></i> Puantaj Ekstresi</h1>
    <div class="d-flex gap-2">
        <a href="puantaj.php?ay=<?php echo $ay; ?>&yil=<?php echo $yil; ?>" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Puantaj'a Dön</a>
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> PDF / Yazdır</button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Personel</label>
                <select id="personel" class="form-select">
                    <?php foreach ($personeller as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $p['id']==$personelId?'selected':''; ?>><?php echo escape($p['ad_soyad']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <div class="d-flex gap-3 align-items-end flex-wrap">
                    <div>
                        <label class="form-label">Mod</label>
                        <select id="mode" class="form-select">
                            <option value="donem" <?php echo $mode==='donem'?'selected':''; ?>>Ay / Yıl</option>
                            <option value="tarih" <?php echo $mode==='tarih'?'selected':''; ?>>Tarih Aralığı</option>
                        </select>
                    </div>
                    <div id="donemInputs" class="d-flex gap-2" style="<?php echo $mode==='donem'?'':'display:none;'; ?>">
                        <div>
                            <label class="form-label">Ay</label>
                            <select id="ay" class="form-select">
                                <?php for ($i=1;$i<=12;$i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i==$ay?'selected':''; ?>><?php echo getTurkishMonthName($i); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Yıl</label>
                            <select id="yil" class="form-select">
                                <?php for ($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y==$yil?'selected':''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div id="tarihInputs" class="d-flex gap-2" style="<?php echo $mode==='tarih'?'':'display:none;'; ?>">
                        <div>
                            <label class="form-label">Başlangıç</label>
                            <input type="date" id="baslangic" class="form-control" value="<?php echo escape($baslangic); ?>">
                        </div>
                        <div>
                            <label class="form-label">Bitiş</label>
                            <input type="date" id="bitis" class="form-control" value="<?php echo escape($bitis); ?>">
                        </div>
                    </div>
                    <div>
                        <button id="uygula" class="btn btn-primary">Uygula</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive mt-3">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Durum</th>
                        <th class="text-end">Saat</th>
                        <th>Açıklama</th>
                        <th class="no-print">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="text-center text-muted">Kayıt bulunamadı.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo date('d.m.Y', strtotime($r['tarih'])); ?></td>
                            <td><?php echo escape($r['durum']); ?></td>
                            <td class="text-end"><?php echo number_format((float)$r['saat'], 2, ',', '.'); ?></td>
                            <td><?php echo escape($r['aciklama']); ?></td>
                            <td class="no-print">
                                <button class="btn btn-sm btn-warning" onclick="duzenle(<?php echo $r['id']; ?>)"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-danger" onclick="silPuantaj(<?php echo $r['id']; ?>)"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Düzenle Modal-->
<div class="modal fade" id="duzenleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Puantaj Düzenle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="puantaj_islem.php" method="POST">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="duz_id">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Tarih</label>
            <input type="date" class="form-control" name="tarih" id="duz_tarih" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Durum</label>
            <select class="form-select" name="durum" id="duz_durum" required>
              <option value="Calisti">Çalıştı</option>
              <option value="Izin">İzin</option>
              <option value="Rapor">Rapor</option>
              <option value="Devamsizlik">Devamsızlık</option>
              <option value="HTatil">Hafta Tatili</option>
              <option value="RTatil">Resmi Tatil</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Saat</label>
            <input type="number" step="0.25" min="0" max="24" class="form-control" name="saat" id="duz_saat" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Açıklama</label>
            <textarea class="form-control" name="aciklama" id="duz_aciklama" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
          <button type="submit" class="btn btn-primary">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function silPuantaj(id) {
    if (confirm('Bu puantaj kaydını silmek istediğinize emin misiniz?')) {
        postDeleteForm('puantaj_islem.php', id);
    }
}

function duzenle(id){
  // Basitçe tablo satırından değerleri toplayalım
  const tr = event.target.closest('tr');
  if (!tr) return;
  document.getElementById('duz_id').value = id;
  const tarihTxt = tr.children[0].textContent.trim();
  const [d,m,y] = tarihTxt.split('.');
  document.getElementById('duz_tarih').value = `${y}-${m}-${d}`;
  document.getElementById('duz_durum').value = tr.children[1].textContent.trim();
  const saatTxt = tr.children[2].textContent.replace(/\./g,'').replace(',','.');
  document.getElementById('duz_saat').value = parseFloat(saatTxt);
  document.getElementById('duz_aciklama').value = tr.children[3].textContent.trim();
  const modal = new bootstrap.Modal(document.getElementById('duzenleModal'));
  modal.show();
}

document.addEventListener('DOMContentLoaded', function(){
  const modeEl = document.getElementById('mode');
  const donemInputs = document.getElementById('donemInputs');
  const tarihInputs = document.getElementById('tarihInputs');
  const uygula = document.getElementById('uygula');
  function toggle(){ if (modeEl.value==='donem'){ donemInputs.style.display=''; tarihInputs.style.display='none'; } else { donemInputs.style.display='none'; tarihInputs.style.display=''; } }
  if (modeEl) modeEl.addEventListener('change', toggle);
  toggle();
  function go(){
    const sp = new URLSearchParams();
    const personel = document.getElementById('personel');
    if (personel && personel.value) sp.set('personel_id', personel.value);
    if (modeEl && modeEl.value) sp.set('mode', modeEl.value);
    if (modeEl.value==='donem') { const ay=document.getElementById('ay'); const yil=document.getElementById('yil'); if (ay) sp.set('ay', ay.value); if (yil) sp.set('yil', yil.value); }
    else { const bas=document.getElementById('baslangic'); const bit=document.getElementById('bitis'); if (bas) sp.set('baslangic', bas.value); if (bit) sp.set('bitis', bit.value); }
    window.location.href = 'puantaj_ekstre.php' + (sp.toString()?('?'+sp.toString()):'');
  }
  if (uygula) uygula.addEventListener('click', function(e){ e.preventDefault(); go(); });
});
</script>

<?php include '../includes/footer.php'; ?>


