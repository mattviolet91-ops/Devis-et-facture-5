/* Visionneuse PDF : toutes les pages à la largeur de l'écran, partage par le menu du téléphone. */
const conteneur = document.getElementById('contenu');
const etat = document.getElementById('etat');
const adresse = conteneur.dataset.document;

const pdfjs = await import(conteneur.dataset.pdfjs);
pdfjs.GlobalWorkerOptions.workerSrc = conteneur.dataset.worker;

let donnees;
try {
    const reponse = await fetch(adresse, { credentials: 'same-origin' });
    if (!reponse.ok) {
        throw new Error(reponse.status === 403 ? 'Vous n\'avez pas accès à ce document.' : 'Document introuvable.');
    }
    donnees = new Uint8Array(await reponse.arrayBuffer());
} catch (e) {
    etat.textContent = e.message || 'Impossible de charger le document.';
    throw e;
}

const fichier = new File([donnees.slice()], (document.getElementById('partager').dataset.titre || 'document') + '.pdf', { type: 'application/pdf' });
const pdf = await pdfjs.getDocument({ data: donnees, isEvalSupported: false }).promise;
etat.remove();

const ratio = window.devicePixelRatio || 1;
for (let numero = 1; numero <= pdf.numPages; numero++) {
    const page = await pdf.getPage(numero);
    const largeur = conteneur.clientWidth;
    const echelle = largeur / page.getViewport({ scale: 1 }).width;
    const vue = page.getViewport({ scale: echelle * ratio });
    const toile = document.createElement('canvas');
    toile.width = Math.floor(vue.width);
    toile.height = Math.floor(vue.height);
    toile.className = 'page-pdf';
    toile.setAttribute('role', 'img');
    toile.setAttribute('aria-label', `Page ${numero} sur ${pdf.numPages}`);
    conteneur.appendChild(toile);
    await page.render({ canvasContext: toile.getContext('2d'), viewport: vue }).promise;
}

/* Partager : menu du téléphone si possible, sinon téléchargement. */
document.getElementById('partager').addEventListener('click', async () => {
    if (navigator.canShare && navigator.canShare({ files: [fichier] })) {
        try {
            await navigator.share({ files: [fichier], title: fichier.name });
        } catch (e) { /* partage annulé */ }
        return;
    }
    const lien = document.createElement('a');
    lien.href = URL.createObjectURL(fichier);
    lien.download = fichier.name;
    document.body.appendChild(lien);
    lien.click();
    lien.remove();
});
