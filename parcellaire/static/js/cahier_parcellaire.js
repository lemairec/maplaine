const API = '';

let currentCompanyId = null;
let currentCampagneId = null;
let parcelles = [];
let interventions = [];

async function api(url, opts = {}) {
    opts.headers = { 'Content-Type': 'application/json', ...(opts.headers || {}) };
    const resp = await fetch(API + url, opts);
    if (!resp.ok) throw new Error(`API ${resp.status}`);
    return resp.json();
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function formatHa(v) {
    return v != null ? `${Number(v).toFixed(2)} ha` : '—';
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getParcelleInterventions(parcelleId) {
    return interventions
        .filter(i => i.parcelle_ids && i.parcelle_ids.includes(parcelleId))
        .sort((a, b) => new Date(b.datetime || 0) - new Date(a.datetime || 0));
}

function renderCahier() {
    const container = document.getElementById('cahier-container');
    const countEl = document.getElementById('cahier-count');
    const summaryEl = document.getElementById('cahier-summary');

    if (parcelles.length === 0) {
        countEl.textContent = '0 fiche';
        summaryEl.textContent = '';
        container.innerHTML = '<p class="text-muted mb-0">Aucune parcelle active pour cette campagne.</p>';
        return;
    }

    const totalSurface = parcelles.reduce((sum, p) => sum + (p.surface || 0), 0);
    countEl.textContent = `${parcelles.length} fiches`;
    summaryEl.textContent = `${totalSurface.toFixed(2)} ha cumulés`;

    // Trier les parcelles par nom de culture (culture_name)
    const sortedParcelles = [...parcelles].sort((a, b) => {
        const cA = (a.culture_name || '').toLowerCase();
        const cB = (b.culture_name || '').toLowerCase();
        if (cA < cB) return -1;
        if (cA > cB) return 1;
        return 0;
    });
    container.innerHTML = sortedParcelles.map(p => {
        const pInterventions = getParcelleInterventions(p.id);
        const interventionsHtml = pInterventions.length === 0
            ? '<p class="text-muted small mb-0">Aucune intervention</p>'
            : `<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Type</th><th>Nom</th><th>Produits (dose/ha)</th></tr></thead><tbody>${pInterventions.map(i => {
                const produits = (i.produits || []).map(pr => {
                    // Nettoyer le nom (retirer " (l)" ou " (kg)" ou " (t)" à la fin)
                    let nom = pr.name ? pr.name.replace(/\s*\([a-zA-Z]+\)$/, '') : '';
                    let doseHa = pr.dose_ha;
                    if ((doseHa === undefined || doseHa === null) && pr.quantity !== undefined && i.surface) {
                        const surfaceHa = Number(i.surface);
                        if (surfaceHa > 0) {
                            doseHa = (Number(pr.quantity) / surfaceHa).toFixed(2);
                        }
                    }
                    if (doseHa !== undefined && doseHa !== null) {
                        return `${escapeHtml(nom)}: ${escapeHtml(doseHa)}${pr.unity ? ' ' + escapeHtml(pr.unity) + '/ha' : ''}`;
                    } else {
                        return `${escapeHtml(nom)}: ${escapeHtml(pr.quantity)}${pr.unity ? ' ' + escapeHtml(pr.unity) : ''}`;
                    }
                }).join('<br/>') || '—';
                return `<tr><td>${escapeHtml(formatDate(i.datetime))}</td><td><span class="type-pill">${escapeHtml(i.type)}</span></td><td>${escapeHtml(i.name || '—')}</td><td class="small">${produits}</td></tr>`;
            }).join('')}</tbody></table></div>`;

        return `
            <section class="cahier-sheet">
                <div class="cahier-header d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1">${escapeHtml(p.complete_name || p.name || 'Parcelle')}</h5>
                        <div class="small text-muted">${escapeHtml(p.commune || 'Commune non renseignée')}</div>
                    </div>
                    <div class="text-end small">
                        <div><strong>Surface:</strong> ${escapeHtml(formatHa(p.surface))}</div>
                        <div><strong>Culture:</strong> ${escapeHtml(p.culture_name || '—')}</div>
                        <div><strong>Îlot:</strong> ${escapeHtml(p.ilot_name || '—')}</div>
                    </div>
                </div>
                <div class="mb-2 small"><strong>Commentaire:</strong> ${escapeHtml(p.comment || '—')}</div>
                <div>
                    <h6 class="mb-2">Interventions (${pInterventions.length})</h6>
                    ${interventionsHtml}
                </div>
            </section>
        `;
    }).join('');
}

async function loadAll() {
    [parcelles, interventions] = await Promise.all([
        api(`/api/campagnes/${currentCampagneId}/parcelles`),
        api(`/api/campagnes/${currentCampagneId}/interventions`),
    ]);
    renderCahier();
}

async function loadCampagnes() {
    const campagnes = await api(`/api/companies/${currentCompanyId}/campagnes`);
    const sel = document.getElementById('sel-campagne');
    sel.innerHTML = campagnes.map(c => `<option value="${c.id}">${c.name}</option>`).join('');

    if (campagnes.length > 0) {
        currentCampagneId = campagnes[0].id;
        sel.value = currentCampagneId;
        await loadAll();
    } else {
        currentCampagneId = null;
        parcelles = [];
        interventions = [];
        renderCahier();
    }
}

async function init() {
    const me = await api('/api/me');
    document.getElementById('nav-username').textContent = me.username;

    const allCompanies = await api('/api/companies');
    const companies = me.company_ids.length > 0
        ? allCompanies.filter(c => me.company_ids.includes(c.id))
        : allCompanies;

    const selCompany = document.getElementById('sel-company');
    selCompany.innerHTML = companies.map(c => `<option value="${c.id}">${c.name}</option>`).join('');

    if (companies.length > 0) {
        currentCompanyId = companies[0].id;
        selCompany.value = currentCompanyId;
        await loadCampagnes();
    }

    selCompany.addEventListener('change', async () => {
        currentCompanyId = selCompany.value;
        await loadCampagnes();
    });

    document.getElementById('sel-campagne').addEventListener('change', async (e) => {
        currentCampagneId = e.target.value;
        await loadAll();
    });

    document.getElementById('btn-refresh-cahier').addEventListener('click', async () => {
        if (currentCampagneId) await loadAll();
    });

    document.getElementById('btn-print-cahier').addEventListener('click', () => {
        window.print();
    });

    document.getElementById('btn-logout').addEventListener('click', async () => {
        await fetch('/api/logout', { method: 'POST' });
        window.location.href = '/login';
    });
}

init();
