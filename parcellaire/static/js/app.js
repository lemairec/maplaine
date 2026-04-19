// ============================================================
//  Gestion Parcellaire – Frontend Application
// ============================================================
const API = '';

// ---- State ----
let currentCompanyId = null;
let currentCampagneId = null;
let parcelles = [];
let cultures = [];
let ilots = [];
let produits = [];
let interventions = [];
let parcelleLayerMap = {};  // parcelle_id -> L.layer
let selectedParcelleId = null;
let mode = 'select';  // select | split | merge
let mergeSelection = new Set();

// ---- Leaflet ----
const map = L.map('map').setView([46.6, 2.5], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 20,
}).addTo(map);

const parcellesLayer = L.layerGroup().addTo(map);
const drawLayer = L.featureGroup().addTo(map);
const drawControl = new L.Control.Draw({
    draw: {
        polygon: { allowIntersection: false, shapeOptions: { color: '#28a745' } },
        polyline: false, rectangle: false, circle: false, marker: false, circlemarker: false,
    },
    edit: { featureGroup: drawLayer },
});

// ---- Helpers ----
async function api(url, opts = {}) {
    opts.headers = { 'Content-Type': 'application/json', ...(opts.headers || {}) };
    const resp = await fetch(API + url, opts);
    if (!resp.ok) throw new Error(`API ${resp.status}`);
    return resp.json();
}

function formatDate(dt) {
    if (!dt) return '—';
    const d = new Date(dt);
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatHa(v) {
    return v != null ? v.toFixed(2) + ' ha' : '—';
}

// ---- Init ----
async function init() {
    const companies = await api('/api/companies');
    const sel = document.getElementById('sel-company');
    sel.innerHTML = companies.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    if (companies.length > 0) {
        currentCompanyId = companies[0].id;
        await loadCampagnes();
    }
    sel.addEventListener('change', async () => {
        currentCompanyId = sel.value;
        await loadCampagnes();
    });
}

async function loadCampagnes() {
    const campagnes = await api(`/api/companies/${currentCompanyId}/campagnes`);
    const sel = document.getElementById('sel-campagne');
    sel.innerHTML = campagnes.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    if (campagnes.length > 0) {
        currentCampagneId = campagnes[0].id;
        await loadAll();
    }
    sel.addEventListener('change', async () => {
        currentCampagneId = sel.value;
        await loadAll();
    });
}

async function loadAll() {
    [parcelles, cultures, ilots, produits, interventions] = await Promise.all([
        api(`/api/campagnes/${currentCampagneId}/parcelles`),
        api(`/api/companies/${currentCompanyId}/cultures`),
        api(`/api/companies/${currentCompanyId}/ilots`),
        api(`/api/companies/${currentCompanyId}/produits`),
        api(`/api/campagnes/${currentCampagneId}/interventions`),
    ]);
    renderParcelles();
    renderInterventions();
    renderMap();
}

// ---- Render Map ----
function renderMap() {
    parcellesLayer.clearLayers();
    parcelleLayerMap = {};
    const bounds = [];
    parcelles.forEach(p => {
        if (!p.geoJson) return;
        let geojson;
        try { geojson = JSON.parse(p.geoJson); } catch { return; }
        const layer = L.geoJSON(geojson, {
            style: {
                fillColor: p.culture_color || '#3388ff',
                color: '#333',
                weight: 2,
                fillOpacity: 0.45,
            },
        });
        layer.on('click', () => onParcelleClick(p.id));
        layer.bindTooltip(`<b>${p.complete_name || p.name || '?'}</b><br/>${formatHa(p.surface)}<br/>${p.culture_name || ''}`, {
            sticky: true,
        });
        parcelleLayerMap[p.id] = layer;
        parcellesLayer.addLayer(layer);
        bounds.push(...layer.getBounds().toBBoxString().split(',').map(Number));
    });
    if (Object.keys(parcelleLayerMap).length > 0) {
        const group = L.featureGroup(Object.values(parcelleLayerMap));
        map.fitBounds(group.getBounds(), { padding: [30, 30] });
    }
}

function highlightParcelle(id) {
    Object.entries(parcelleLayerMap).forEach(([pid, layer]) => {
        layer.setStyle({
            weight: pid === id ? 4 : 2,
            color: pid === id ? '#ff0' : '#333',
        });
        if (pid === id) layer.bringToFront();
    });
}

// ---- Render Sidebar: Parcelles ----
function renderParcelles() {
    const container = document.getElementById('parcelles-list');
    if (parcelles.length === 0) {
        container.innerHTML = '<p class="text-muted small">Aucune parcelle</p>';
        document.getElementById('parcelles-summary').textContent = '';
        return;
    }
    const totalSurface = parcelles.reduce((s, p) => s + (p.surface || 0), 0);
    document.getElementById('parcelles-summary').textContent =
        `${parcelles.length} parcelles · ${totalSurface.toFixed(2)} ha`;

    container.innerHTML = parcelles.map(p => `
        <div class="parcelle-card ${p.id === selectedParcelleId ? 'active' : ''}"
             data-id="${p.id}"
             style="border-left-color: ${p.culture_color || '#ccc'}">
            <div class="d-flex justify-content-between">
                <span>
                    <span class="culture-dot" style="background:${p.culture_color || '#ccc'}"></span>
                    <strong>${p.complete_name || p.name || '—'}</strong>
                </span>
                <span class="text-muted small">${formatHa(p.surface)}</span>
            </div>
            <div class="small text-muted">${p.culture_name || ''} ${p.commune ? '· ' + p.commune : ''}</div>
        </div>
    `).join('');

    container.querySelectorAll('.parcelle-card').forEach(el => {
        el.addEventListener('click', () => onParcelleClick(el.dataset.id));
    });
}

function onParcelleClick(id) {
    if (mode === 'merge') {
        if (mergeSelection.has(id)) mergeSelection.delete(id);
        else mergeSelection.add(id);
        renderParcelles();
        document.querySelectorAll(`.parcelle-card`).forEach(el => {
            if (mergeSelection.has(el.dataset.id)) el.classList.add('merge-selected');
        });
        // highlight on map
        Object.entries(parcelleLayerMap).forEach(([pid, layer]) => {
            layer.setStyle({
                weight: mergeSelection.has(pid) ? 4 : 2,
                color: mergeSelection.has(pid) ? '#0dcaf0' : '#333',
            });
        });
        return;
    }

    selectedParcelleId = id;
    highlightParcelle(id);
    showParcelleDetail(id);
}

function showParcelleDetail(id) {
    const p = parcelles.find(x => x.id === id);
    if (!p) return;

    document.getElementById('tab-parcelles').classList.add('d-none');
    document.getElementById('tab-interventions').classList.add('d-none');
    document.getElementById('panel-parcelle').classList.remove('d-none');

    // Get interventions for this parcelle
    const pInterventions = interventions.filter(i =>
        i.parcelle_ids && i.parcelle_ids.includes(id)
    );

    const detail = document.getElementById('parcelle-detail');
    detail.innerHTML = `
        <h5>${p.complete_name || p.name || '—'}</h5>
        <div class="fiche-section">
            <h6>Informations</h6>
            <table class="table table-sm">
                <tr><td>Surface</td><td>${formatHa(p.surface)}</td></tr>
                <tr><td>Culture</td><td><span class="culture-dot" style="background:${p.culture_color || '#ccc'}"></span>${p.culture_name || '—'}</td></tr>
                <tr><td>Îlot</td><td>${p.ilot_name || '—'}</td></tr>
                <tr><td>Commune</td><td>${p.commune || '—'}</td></tr>
                <tr><td>Commentaire</td><td>${p.comment || '—'}</td></tr>
            </table>
        </div>
        <div class="fiche-section">
            <div class="d-flex justify-content-between">
                <h6>Interventions (${pInterventions.length})</h6>
            </div>
            ${pInterventions.length === 0 ? '<p class="text-muted small">Aucune intervention</p>' :
            pInterventions.map(i => `
                <div class="intervention-card">
                    <div class="d-flex justify-content-between">
                        <span><span class="type-badge type-${i.type}">${i.type}</span> ${i.name || ''}</span>
                        <span class="small text-muted">${formatDate(i.datetime)}</span>
                    </div>
                    ${i.produits.length > 0 ? '<div class="small mt-1">' + i.produits.map(pr =>
                        `${pr.name} : ${pr.quantity} ${pr.unity || ''}`
                    ).join('<br/>') + '</div>' : ''}
                </div>
            `).join('')}
        </div>
        <div class="d-flex gap-1 mt-2">
            <button class="btn btn-sm btn-outline-primary" id="btn-edit-parcelle"><i class="bi bi-pencil"></i> Modifier</button>
            <button class="btn btn-sm btn-outline-danger" id="btn-delete-parcelle"><i class="bi bi-trash"></i> Supprimer</button>
            <button class="btn btn-sm btn-outline-secondary" id="btn-print-fiche"><i class="bi bi-printer"></i> Fiche</button>
        </div>
    `;

    document.getElementById('btn-edit-parcelle').addEventListener('click', () => openParcelleModal(p));
    document.getElementById('btn-delete-parcelle').addEventListener('click', () => deleteParcelle(p.id));
    document.getElementById('btn-print-fiche').addEventListener('click', () => printFiche(p, pInterventions));
}

// ---- Parcelle CRUD ----
function openParcelleModal(p = null) {
    const isEdit = !!p;
    document.querySelector('#modal-parcelle .modal-title').textContent = isEdit ? 'Modifier la parcelle' : 'Nouvelle parcelle';
    document.getElementById('fp-id').value = isEdit ? p.id : '';
    document.getElementById('fp-name').value = isEdit ? (p.name || '') : '';
    document.getElementById('fp-surface').value = isEdit ? (p.surface || '') : '';
    document.getElementById('fp-commune').value = isEdit ? (p.commune || '') : '';
    document.getElementById('fp-comment').value = isEdit ? (p.comment || '') : '';
    document.getElementById('fp-geojson').value = isEdit ? (p.geoJson || '') : '';

    // Cultures dropdown
    const cultSel = document.getElementById('fp-culture');
    cultSel.innerHTML = '<option value="">— Aucune —</option>' +
        cultures.map(c => `<option value="${c.id}" ${p && p.culture_id === c.id ? 'selected' : ''}>${c.name}</option>`).join('');

    // Ilots dropdown
    const ilotSel = document.getElementById('fp-ilot');
    ilotSel.innerHTML = '<option value="">— Aucun —</option>' +
        ilots.map(i => `<option value="${i.id}" ${p && p.ilot_id === i.id ? 'selected' : ''}>${i.name}</option>`).join('');

    new bootstrap.Modal(document.getElementById('modal-parcelle')).show();

    if (!isEdit) {
        // Enable drawing mode
        map.addControl(drawControl);
        map.once('draw:created', (e) => {
            const layer = e.layer;
            drawLayer.addLayer(layer);
            const gj = layer.toGeoJSON();
            document.getElementById('fp-geojson').value = JSON.stringify(gj.geometry);
            // Auto-calculate surface from geometry in hectares
            const area = turf.area(gj) / 10000;
            document.getElementById('fp-surface').value = area.toFixed(2);
        });
    }
}

document.getElementById('btn-save-parcelle').addEventListener('click', async () => {
    const id = document.getElementById('fp-id').value;
    const payload = {
        name: document.getElementById('fp-name').value,
        complete_name: document.getElementById('fp-name').value,
        surface: parseFloat(document.getElementById('fp-surface').value) || null,
        commune: document.getElementById('fp-commune').value || null,
        culture_id: document.getElementById('fp-culture').value || null,
        ilot_id: document.getElementById('fp-ilot').value || null,
        comment: document.getElementById('fp-comment').value || null,
        geoJson: document.getElementById('fp-geojson').value || null,
    };

    if (id) {
        await api(`/api/parcelles/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
    } else {
        payload.campagne_id = currentCampagneId;
        await api('/api/parcelles', { method: 'POST', body: JSON.stringify(payload) });
    }
    bootstrap.Modal.getInstance(document.getElementById('modal-parcelle')).hide();
    drawLayer.clearLayers();
    try { map.removeControl(drawControl); } catch {}
    await loadAll();
});

async function deleteParcelle(id) {
    if (!confirm('Supprimer cette parcelle ?')) return;
    await api(`/api/parcelles/${id}`, { method: 'DELETE' });
    selectedParcelleId = null;
    backToParcelles();
    await loadAll();
}

// ---- Modes ----
document.getElementById('btn-mode-select').addEventListener('click', () => setMode('select'));
document.getElementById('btn-mode-split').addEventListener('click', () => setMode('split'));
document.getElementById('btn-mode-merge').addEventListener('click', () => setMode('merge'));

function setMode(m) {
    mode = m;
    mergeSelection.clear();
    document.getElementById('btn-mode-select').classList.toggle('active', m === 'select');
    document.getElementById('btn-mode-split').classList.toggle('active', m === 'split');
    document.getElementById('btn-mode-merge').classList.toggle('active', m === 'merge');
    document.getElementById('merge-bar').classList.toggle('d-none', m !== 'merge');
    try { map.removeControl(drawControl); } catch {}
    drawLayer.clearLayers();
}

// ---- Split ----
document.getElementById('btn-mode-split').addEventListener('click', () => {
    if (mode !== 'split') return;
    // When in split mode, clicking a parcelle will open split dialog
});

// Override parcelle click in split mode
function onParcelleClickSplit(id) {
    selectedParcelleId = id;
    highlightParcelle(id);
    const p = parcelles.find(x => x.id === id);
    document.getElementById('fs-name-a').value = (p.name || '') + ' A';
    document.getElementById('fs-name-b').value = (p.name || '') + ' B';

    // Add line drawing tool
    const lineControl = new L.Control.Draw({
        draw: {
            polyline: { shapeOptions: { color: '#ff0000', weight: 3 } },
            polygon: false, rectangle: false, circle: false, marker: false, circlemarker: false,
        },
        edit: false,
    });
    map.addControl(lineControl);

    new bootstrap.Modal(document.getElementById('modal-split')).show();
}

document.getElementById('btn-do-split').addEventListener('click', async () => {
    const p = parcelles.find(x => x.id === selectedParcelleId);
    if (!p || !p.geoJson) return;

    let geojson;
    try { geojson = JSON.parse(p.geoJson); } catch { return alert('GeoJSON invalide'); }

    // For split, we need to create two halves. Simple approach: split by midpoint longitude
    const polygon = geojson.type === 'Polygon' ? geojson : (geojson.type === 'Feature' ? geojson.geometry : geojson);
    const bbox = turf.bbox(polygon);
    const midLon = (bbox[0] + bbox[2]) / 2;

    const clipA = turf.bboxPolygon([bbox[0], bbox[1], midLon, bbox[3]]);
    const clipB = turf.bboxPolygon([midLon, bbox[1], bbox[2], bbox[3]]);

    let partA, partB;
    try {
        partA = turf.intersect(turf.featureCollection([turf.feature(polygon), clipA]));
        partB = turf.intersect(turf.featureCollection([turf.feature(polygon), clipB]));
    } catch {
        return alert('Erreur de découpe géométrique');
    }

    if (!partA || !partB) return alert('Découpe impossible');

    const areaA = turf.area(partA) / 10000;
    const areaB = turf.area(partB) / 10000;

    const payload = {
        geoJson_a: JSON.stringify(partA.geometry),
        geoJson_b: JSON.stringify(partB.geometry),
        surface_a: parseFloat(areaA.toFixed(2)),
        surface_b: parseFloat(areaB.toFixed(2)),
        name_a: document.getElementById('fs-name-a').value,
        name_b: document.getElementById('fs-name-b').value,
    };

    await api(`/api/parcelles/${selectedParcelleId}/split`, {
        method: 'POST', body: JSON.stringify(payload),
    });

    bootstrap.Modal.getInstance(document.getElementById('modal-split')).hide();
    selectedParcelleId = null;
    setMode('select');
    await loadAll();
});

// Wrap original onParcelleClick to handle split mode
const _origParcelleClick = onParcelleClick;
function onParcelleClickDispatch(id) {
    if (mode === 'split') return onParcelleClickSplit(id);
    return _origParcelleClick(id);
}
// Re-bind
function rebindParcelleClicks() {
    Object.entries(parcelleLayerMap).forEach(([pid, layer]) => {
        layer.off('click');
        layer.on('click', () => onParcelleClickDispatch(pid));
    });
    document.querySelectorAll('.parcelle-card').forEach(el => {
        el.replaceWith(el.cloneNode(true));
    });
    document.querySelectorAll('.parcelle-card').forEach(el => {
        el.addEventListener('click', () => onParcelleClickDispatch(el.dataset.id));
    });
}

// Patch renderMap and renderParcelles to rebind
const _origRenderMap = renderMap;
const _origRenderParcelles = renderParcelles;
// We override via monkey-patch after declarations

// ---- Merge ----
document.getElementById('btn-do-merge').addEventListener('click', async () => {
    if (mergeSelection.size < 2) return alert('Sélectionnez au moins 2 parcelles');
    const ids = [...mergeSelection];
    const selected = parcelles.filter(p => ids.includes(p.id));

    // Merge geometries with turf
    let merged = null;
    for (const p of selected) {
        if (!p.geoJson) continue;
        let gj;
        try { gj = JSON.parse(p.geoJson); } catch { continue; }
        const feat = gj.type === 'Feature' ? gj : turf.feature(gj);
        if (!merged) { merged = feat; continue; }
        try {
            merged = turf.union(turf.featureCollection([merged, feat]));
        } catch {
            // fallback: just use geometry collection
            merged = feat;
        }
    }

    if (!merged) return alert('Impossible de fusionner les géométries');

    const totalSurface = selected.reduce((s, p) => s + (p.surface || 0), 0);
    const name = selected.map(p => p.name || '?').join(' + ');

    const payload = {
        parcelle_ids: ids,
        name: name,
        geoJson: JSON.stringify(merged.geometry),
        surface: parseFloat(totalSurface.toFixed(2)),
    };

    await api('/api/parcelles/merge', { method: 'POST', body: JSON.stringify(payload) });
    setMode('select');
    await loadAll();
});

document.getElementById('btn-cancel-merge').addEventListener('click', () => setMode('select'));

// ---- Interventions ----
function renderInterventions() {
    const container = document.getElementById('interventions-list');
    if (interventions.length === 0) {
        container.innerHTML = '<p class="text-muted small">Aucune intervention</p>';
        return;
    }
    container.innerHTML = interventions.map(i => `
        <div class="intervention-card" data-id="${i.id}">
            <div class="d-flex justify-content-between">
                <span><span class="type-badge type-${i.type}">${i.type}</span> ${i.name || ''}</span>
                <span class="small text-muted">${formatDate(i.datetime)}</span>
            </div>
            <div class="small text-muted">${formatHa(i.surface)} · ${i.parcelle_ids.length} parcelles</div>
            ${i.produits.length > 0 ? '<div class="small">' + i.produits.map(pr =>
                `${pr.name}: ${pr.quantity} ${pr.unity || ''}`
            ).join(', ') + '</div>' : ''}
        </div>
    `).join('');

    container.querySelectorAll('.intervention-card').forEach(el => {
        el.addEventListener('click', () => {
            const inter = interventions.find(i => i.id === el.dataset.id);
            if (inter && inter.parcelle_ids.length > 0) {
                // Highlight related parcelles on map
                Object.entries(parcelleLayerMap).forEach(([pid, layer]) => {
                    layer.setStyle({
                        weight: inter.parcelle_ids.includes(pid) ? 4 : 2,
                        color: inter.parcelle_ids.includes(pid) ? '#ff0' : '#333',
                    });
                });
            }
        });
    });
}

// ---- Intervention CRUD ----
document.getElementById('btn-add-intervention').addEventListener('click', () => openInterventionModal());

function openInterventionModal() {
    document.getElementById('fi-date').value = new Date().toISOString().slice(0, 16);
    document.getElementById('fi-name').value = '';
    document.getElementById('fi-surface').value = '';
    document.getElementById('fi-comment').value = '';

    // Parcelles checkboxes
    const parcDiv = document.getElementById('fi-parcelles');
    parcDiv.innerHTML = parcelles.map(p => `
        <div class="form-check">
            <input class="form-check-input fi-parc-check" type="checkbox" value="${p.id}" id="fip-${p.id}">
            <label class="form-check-label" for="fip-${p.id}">${p.complete_name || p.name || '—'} (${formatHa(p.surface)})</label>
        </div>
    `).join('');

    // Auto-compute surface from checked parcelles
    parcDiv.querySelectorAll('.fi-parc-check').forEach(cb => {
        cb.addEventListener('change', () => {
            const checked = [...parcDiv.querySelectorAll('.fi-parc-check:checked')];
            const totalS = checked.reduce((s, c) => {
                const p = parcelles.find(x => x.id === c.value);
                return s + (p?.surface || 0);
            }, 0);
            document.getElementById('fi-surface').value = totalS.toFixed(2);
        });
    });

    // Produits
    document.getElementById('fi-produits').innerHTML = '';

    new bootstrap.Modal(document.getElementById('modal-intervention')).show();
}

document.getElementById('btn-add-produit-row').addEventListener('click', () => {
    const container = document.getElementById('fi-produits');
    const row = document.createElement('div');
    row.className = 'produit-row';
    row.innerHTML = `
        <select class="form-select form-select-sm fi-prod-sel">
            <option value="">— Produit —</option>
            ${produits.map(p => `<option value="${p.id}" data-name="${p.name}">${p.name} (${p.price}€/${p.unity || 'u'})</option>`).join('')}
        </select>
        <input class="form-control form-control-sm fi-prod-qty" type="number" step="0.01" placeholder="Quantité" />
        <button class="btn btn-sm btn-outline-danger fi-prod-del"><i class="bi bi-x"></i></button>
    `;
    row.querySelector('.fi-prod-del').addEventListener('click', () => row.remove());
    container.appendChild(row);
});

document.getElementById('btn-save-intervention').addEventListener('click', async () => {
    const checkedParcelles = [...document.querySelectorAll('.fi-parc-check:checked')].map(c => ({
        parcelle_id: c.value,
    }));

    const produitRows = [...document.querySelectorAll('.produit-row')];
    const produitsData = produitRows.map(row => {
        const sel = row.querySelector('.fi-prod-sel');
        return {
            produit_id: sel.value,
            name: sel.options[sel.selectedIndex]?.dataset?.name || sel.options[sel.selectedIndex]?.text || '',
            quantity: parseFloat(row.querySelector('.fi-prod-qty').value) || 0,
        };
    }).filter(p => p.produit_id);

    const payload = {
        campagne_id: currentCampagneId,
        company_id: currentCompanyId,
        datetime: document.getElementById('fi-date').value,
        type: document.getElementById('fi-type').value,
        surface: parseFloat(document.getElementById('fi-surface').value) || 0,
        name: document.getElementById('fi-name').value || null,
        comment: document.getElementById('fi-comment').value || null,
        parcelles: checkedParcelles,
        produits: produitsData,
    };

    await api('/api/interventions', { method: 'POST', body: JSON.stringify(payload) });
    bootstrap.Modal.getInstance(document.getElementById('modal-intervention')).hide();
    await loadAll();
});

// ---- Navigation ----
document.getElementById('btn-add-parcelle').addEventListener('click', () => openParcelleModal());
document.getElementById('btn-back-parcelles').addEventListener('click', backToParcelles);

function backToParcelles() {
    document.getElementById('panel-parcelle').classList.add('d-none');
    document.getElementById('tab-parcelles').classList.remove('d-none');
    selectedParcelleId = null;
    highlightParcelle(null);
}

// Tabs
document.querySelectorAll('#sidebar-tabs .nav-link').forEach(tab => {
    tab.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('#sidebar-tabs .nav-link').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        document.querySelectorAll('.tab-content').forEach(c => c.classList.add('d-none'));
        document.getElementById(tab.dataset.tab).classList.remove('d-none');
    });
});

// ---- Print Fiche ----
function printFiche(p, interventionsList) {
    const w = window.open('', '_blank');
    w.document.write(`
        <html><head><title>Fiche parcellaire – ${p.complete_name || p.name}</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            h1 { color: #333; border-bottom: 2px solid #333; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { border: 1px solid #ddd; padding: 6px 10px; text-align: left; }
            th { background: #f0f0f0; }
            .section { margin-top: 20px; }
            @media print { button { display: none; } }
        </style></head><body>
        <button onclick="window.print()">Imprimer</button>
        <h1>Fiche Parcellaire</h1>
        <h2>${p.complete_name || p.name || '—'}</h2>
        <table>
            <tr><th>Surface</th><td>${formatHa(p.surface)}</td></tr>
            <tr><th>Culture</th><td>${p.culture_name || '—'}</td></tr>
            <tr><th>Îlot</th><td>${p.ilot_name || '—'}</td></tr>
            <tr><th>Commune</th><td>${p.commune || '—'}</td></tr>
            <tr><th>Commentaire</th><td>${p.comment || '—'}</td></tr>
        </table>
        <div class="section">
            <h3>Interventions (${interventionsList.length})</h3>
            <table>
                <tr><th>Date</th><th>Type</th><th>Description</th><th>Produits</th><th>Coût</th></tr>
                ${interventionsList.map(i => `
                    <tr>
                        <td>${formatDate(i.datetime)}</td>
                        <td>${i.type}</td>
                        <td>${i.name || '—'}</td>
                        <td>${i.produits.map(pr => `${pr.name}: ${pr.quantity} ${pr.unity || ''}`).join('<br/>')}</td>
                        <td>${i.produits.reduce((s, pr) => s + (pr.quantity * pr.price), 0).toFixed(2)} €</td>
                    </tr>
                `).join('')}
            </table>
        </div>
        </body></html>
    `);
    w.document.close();
}

// ---- Post-render rebind ----
const origRenderMap = renderMap;
const origRenderParcelles = renderParcelles;

// Override renderMap/renderParcelles to rebind clicks after render
const _renderMap = () => { origRenderMap(); rebindParcelleClicks(); };
const _renderParcelles = () => { origRenderParcelles(); rebindParcelleClicks(); };

// ---- Boot ----
init();
