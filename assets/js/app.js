/* AKM İnovasyon - Panel JS */
(function () {
  'use strict';

  // -------------------------------------------------------------------
  // Sidebar toggle (mobile)
  // -------------------------------------------------------------------
  const sidebar = document.getElementById('sidebar');
  const burger  = document.getElementById('burger');
  const sbTog   = document.getElementById('sbToggle');

  let backdrop = document.querySelector('.sb-backdrop');
  if (!backdrop && sidebar) {
    backdrop = document.createElement('div');
    backdrop.className = 'sb-backdrop';
    document.body.appendChild(backdrop);
  }

  function openSb()  { sidebar?.classList.add('open'); backdrop?.classList.add('show'); }
  function closeSb() { sidebar?.classList.remove('open'); backdrop?.classList.remove('show'); }

  burger?.addEventListener('click', openSb);
  sbTog?.addEventListener('click', closeSb);
  backdrop?.addEventListener('click', closeSb);

  // -------------------------------------------------------------------
  // Confirm dialogs (data-confirm attr)
  // -------------------------------------------------------------------
  document.addEventListener('click', function (e) {
    const t = e.target.closest('[data-confirm]');
    if (!t) return;
    if (!confirm(t.dataset.confirm)) { e.preventDefault(); e.stopPropagation(); }
  });

  // -------------------------------------------------------------------
  // Teklif kalemleri - dinamik satır yönetimi
  // -------------------------------------------------------------------
  const kalemTbl = document.getElementById('kalemTbl');
  if (kalemTbl) {
    const tbody = kalemTbl.querySelector('tbody');

    function parseNum(v) {
      if (v === undefined || v === null) return 0;
      if (typeof v === 'number') return v;
      v = String(v).trim().replace(/\./g, '').replace(',', '.');
      const n = parseFloat(v);
      return isNaN(n) ? 0 : n;
    }
    function fmt(n, dec) {
      dec = dec || 2;
      return n.toLocaleString('tr-TR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    }

    function recalc() {
      let ara = 0;
      const kdvByRate = Object.create(null); // { '10.00': {orani:10, matrah:0, tutar:0}, ... }

      tbody.querySelectorAll('tr.kalem-row').forEach(function (tr) {
        const m = parseNum(tr.querySelector('[name="kalem_miktar[]"]')?.value);
        const f = parseNum(tr.querySelector('[name="kalem_fiyat[]"]')?.value);
        const i = parseNum(tr.querySelector('[name="kalem_iskonto[]"]')?.value);
        const k = parseNum(tr.querySelector('[name="kalem_kdv[]"]')?.value);
        const brut = m * f;
        const isk = brut * i / 100;
        const net = brut - isk;
        ara += net;

        if (k > 0) {
          const key = k.toFixed(2);
          if (!kdvByRate[key]) kdvByRate[key] = { orani: k, matrah: 0, tutar: 0 };
          kdvByRate[key].matrah += net;
          kdvByRate[key].tutar  += net * k / 100;
        }

        const tEl = tr.querySelector('.satir-toplam');
        if (tEl) tEl.textContent = fmt(net);
      });

      const iskG = parseNum(document.getElementById('iskonto_orani')?.value);
      const iskT = ara * iskG / 100;
      const araSon = ara - iskT;

      // Genel iskontoyu KDV dağılımına da uygula
      const iskFactor = iskG > 0 ? (1 - iskG / 100) : 1;
      let kdvTop = 0;
      const sortedRates = Object.keys(kdvByRate).sort((a, b) => parseFloat(a) - parseFloat(b));
      const dagilimEl = document.getElementById('kdvDagilim');
      const kdvLabelEl = document.getElementById('kdvTopLabel');

      // Oran -> etiket metni ("%20" gibi, tam sayıysa ondalıksız)
      function rateLabel(rate) {
        const isInt = Math.abs(rate - Math.round(rate)) < 0.01;
        return '%' + rate.toLocaleString('tr-TR', {
          minimumFractionDigits: isInt ? 0 : 2,
          maximumFractionDigits: 2
        });
      }

      if (dagilimEl) dagilimEl.innerHTML = '';

      if (sortedRates.length >= 2) {
        // Birden fazla KDV oranı: dağılımı göster, ana satır "Toplam KDV"
        sortedRates.forEach(function (key) {
          const r = kdvByRate[key];
          const matrah = r.matrah * iskFactor;
          const tutar  = r.tutar  * iskFactor;
          kdvTop += tutar;
          if (!dagilimEl) return;
          const row = document.createElement('div');
          row.className = 'row kdv-row';
          row.innerHTML =
            '<span>KDV ' + rateLabel(r.orani) + ' <small class="muted">(' + fmt(matrah) + ')</small></span>' +
            '<strong><span class="pb-sym">₺</span> ' + fmt(tutar) + '</strong>';
          dagilimEl.appendChild(row);
        });
        if (kdvLabelEl) kdvLabelEl.textContent = 'Toplam KDV';
      } else if (sortedRates.length === 1) {
        // Tek oran: dağılımı gösterme, etiket oranı içersin
        const r = kdvByRate[sortedRates[0]];
        kdvTop = r.tutar * iskFactor;
        if (kdvLabelEl) kdvLabelEl.textContent = 'KDV ' + rateLabel(r.orani);
      } else {
        if (kdvLabelEl) kdvLabelEl.textContent = 'KDV';
      }

      const genel = araSon + kdvTop;

      setText('sumAra',     fmt(ara));
      setText('sumIskonto', fmt(iskT));
      setText('sumKdv',     fmt(kdvTop));
      setText('sumGenel',   fmt(genel));

      const pb = document.getElementById('para_birimi')?.value || 'TRY';
      const sym = pb === 'USD' ? '$' : (pb === 'EUR' ? '€' : '₺');
      document.querySelectorAll('.pb-sym').forEach(el => { el.textContent = sym; });
    }
    function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }

    // Satır ekle
    document.getElementById('btnKalemEkle')?.addEventListener('click', function () {
      const tpl = document.getElementById('kalemTpl');
      if (!tpl) return;
      const node = tpl.content.cloneNode(true);
      tbody.appendChild(node);
      renumber();
      recalc();
    });

    // Satır sil
    tbody.addEventListener('click', function (e) {
      const btn = e.target.closest('.btn-kalem-sil');
      if (!btn) return;
      if (tbody.querySelectorAll('tr.kalem-row').length <= 1) {
        alert('En az bir kalem olmalı.');
        return;
      }
      btn.closest('tr')?.remove();
      renumber();
      recalc();
    });

    function renumber() {
      tbody.querySelectorAll('tr.kalem-row .kalem-sira').forEach(function (el, i) {
        el.textContent = (i + 1);
      });
    }

    // Input dinleyicileri (delegated)
    kalemTbl.addEventListener('input', recalc);
    document.getElementById('iskonto_orani')?.addEventListener('input', recalc);
    document.getElementById('para_birimi')?.addEventListener('change', recalc);

    renumber();
    recalc();
  }

  // -------------------------------------------------------------------
  // Auto-dismiss flash messages
  // -------------------------------------------------------------------
  document.querySelectorAll('.alert').forEach(function (el) {
    if (el.classList.contains('alert-success') || el.classList.contains('alert-info')) {
      setTimeout(function () {
        el.style.transition = 'opacity .4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
      }, 4500);
    }
  });

  // -------------------------------------------------------------------
  // Para birimi değişince kayıtlı kuru otomatik yükle, TRY ise 1'e sabitle
  // -------------------------------------------------------------------
  const kurInput   = document.getElementById('doviz_kuru');
  const kurInfoEl  = document.getElementById('kurInfo');
  const pbSelect   = document.getElementById('para_birimi');
  const btnKur     = document.getElementById('btnKurYenile');

  function paraBirimiDegisti() {
    if (!pbSelect || !kurInput) return;
    const pb = pbSelect.value;
    if (pb === 'TRY') {
      kurInput.value = '1';
      kurInput.setAttribute('readonly', 'readonly');
      if (btnKur) btnKur.disabled = true;
      if (kurInfoEl) kurInfoEl.textContent = 'TRY için kur: 1';
    } else {
      kurInput.removeAttribute('readonly');
      if (btnKur) btnKur.disabled = false;
      // Kaydedilmiş kuru yükle — yalnızca form yeni açılmışsa veya mevcut değer 1 ise
      const mevcut = parseFloat(kurInput.value.replace(',', '.')) || 0;
      if (mevcut <= 1 && window.__AKM_KUR && window.__AKM_KUR[pb] > 0) {
        kurInput.value = window.__AKM_KUR[pb].toString();
      }
      if (kurInfoEl && window.__AKM_KUR) {
        const src = window.__AKM_KUR.kaynak || 'Kayıtlı';
        const trh = window.__AKM_KUR.tarih ? ' (' + window.__AKM_KUR.tarih + ')' : '';
        kurInfoEl.textContent = src + trh;
      }
    }
  }

  pbSelect?.addEventListener('change', paraBirimiDegisti);
  // İlk yüklemede TRY ise kur alanını 1'e kilitle
  if (pbSelect && pbSelect.value === 'TRY') paraBirimiDegisti();

  // -------------------------------------------------------------------
  // TCMB Güncel Kur butonu
  // -------------------------------------------------------------------
  btnKur?.addEventListener('click', async function () {
    if (!pbSelect || !kurInput) return;
    const pb = pbSelect.value;
    if (pb === 'TRY') { alert('TRY için kur gerekmez.'); return; }

    const origTxt = btnKur.innerHTML;
    btnKur.disabled = true;
    btnKur.innerHTML = '⏳ Yükleniyor...';
    if (kurInfoEl) kurInfoEl.textContent = 'TCMB\'den alınıyor...';

    try {
      const res = await fetch('yonetim.php?sayfa=kur_api&tcmb=1&pb=' + encodeURIComponent(pb), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (data.ok && data.kur && data.kur > 0) {
        kurInput.value = data.kur.toString();
        // Cache'i de güncelle
        if (window.__AKM_KUR && data.kurlar) {
          if (data.kurlar.USD) window.__AKM_KUR.USD = data.kurlar.USD;
          if (data.kurlar.EUR) window.__AKM_KUR.EUR = data.kurlar.EUR;
          window.__AKM_KUR.tarih = data.tarih || '';
          window.__AKM_KUR.kaynak = 'TCMB Efektif Satış';
        }
        if (kurInfoEl) {
          const sf = data.cached ? ' (cache)' : '';
          kurInfoEl.textContent = 'TCMB Efektif Satış (' + (data.tarih || '-') + ')' + sf +
                                   ' · USD ' + (data.kurlar?.USD || '-') +
                                   ' · EUR ' + (data.kurlar?.EUR || '-');
          kurInfoEl.classList.add('kur-info-ok');
        }
        // Tutarları yeniden hesapla
        kurInput.dispatchEvent(new Event('input', { bubbles: true }));
      } else {
        const msg = data.msg || 'Kur alınamadı.';
        if (kurInfoEl) { kurInfoEl.textContent = '⚠ ' + msg; kurInfoEl.classList.add('kur-info-err'); }
        alert('TCMB kur hatası:\n' + msg);
      }
    } catch (err) {
      if (kurInfoEl) kurInfoEl.textContent = '⚠ İstek hatası: ' + err.message;
      alert('İstek hatası: ' + err.message);
    } finally {
      btnKur.disabled = (pbSelect.value === 'TRY');
      btnKur.innerHTML = origTxt;
    }
  });

  // -------------------------------------------------------------------
  // Cari seçiminde varsayılan para birimi otomatik doldur (teklif formu)
  // -------------------------------------------------------------------
  const cariSel = document.getElementById('cari_select');
  cariSel?.addEventListener('change', function () {
    const opt = cariSel.options[cariSel.selectedIndex];
    if (!opt) return;
    const pb = opt.dataset.paraBirimi;
    const pbSel = document.getElementById('para_birimi');
    if (pb && pbSel && !pbSel.dataset.locked) {
      pbSel.value = pb;
      pbSel.dispatchEvent(new Event('change'));
    }
  });
})();
